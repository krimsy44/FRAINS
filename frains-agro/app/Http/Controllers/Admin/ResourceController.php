<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cultivation;
use App\Models\DeliveryZone;
use App\Models\Parcel;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResourceController extends Controller
{
    private function definition(string $resource): array
    {
        $definition = config('frains.resources.'.$resource);
        abort_unless($definition, 404);
        abort_unless(in_array(request()->user()->role?->name, $definition['roles'], true), 403);

        return $definition;
    }

    private function query(array $definition)
    {
        $query = $definition['model']::query();
        if (request()->user()->role?->name === 'PRODUCER') {
            $query->whereHas('parcel.producer', fn ($q) => $q->where('user_id', auth()->id()));
        }

        return $query;
    }

    public function index(string $resource)
    {
        $definition = $this->definition($resource);

        return view('admin.resources.index', [
            'resource' => $resource,
            'definition' => $definition,
            'options' => $this->options($definition),
            'records' => $this->query($definition)->latest()->paginate(20),
            'parcelChoices' => $resource === 'parcels'
                ? $this->query($definition)->with('producer')->orderBy('reference')->get()
                : collect(),
        ]);
    }

    public function create(string $resource)
    {
        $definition = $this->definition($resource);

        return $this->form($resource, $definition, new $definition['model']);
    }

    public function removeGallery(int $record)
    {
        $definition = $this->definition('gallery');
        DB::transaction(function () use ($definition, $record) {
            $photo = $this->query($definition)->whereKey($record)->lockForUpdate()->firstOrFail();
            AuditLog::record('gallery.removed', 'gallery:'.$photo->id, ['title' => $photo->title, 'image' => $photo->image]);
            $photo->delete();
        });

        return to_route('admin.resources.index', 'gallery')->with('success', 'Le média a été retiré de la galerie.');
    }

    public function edit(string $resource, int $record)
    {
        $definition = $this->definition($resource);

        return $this->form($resource, $definition, $this->query($definition)->findOrFail($record));
    }

    private function form($resource, $definition, $record)
    {
        $options = $this->options($definition);

        return view('admin.resources.form', compact('resource', 'definition', 'record', 'options'));
    }

    private function options(array $definition): array
    {
        $options = [];
        foreach ($definition['fields'] as $name => $field) {
            if ($field[1] !== 'select') {
                continue;
            }
            if (is_array($field[3])) {
                $options[$name] = $field[3];

                continue;
            }
            $options[$name] = match ($field[3]) {
                'zones' => DeliveryZone::orderBy('name')->pluck('name', 'id')->all(),
                'products' => Product::orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' ('.$p->unit.')'])->all(),
                'producer_records' => $this->producerOptions(),
                'parcels' => Parcel::when(auth()->user()->role?->name === 'PRODUCER', fn ($q) => $q->whereHas('producer', fn ($q) => $q->where('user_id', auth()->id())))->pluck('reference', 'id')->all(),
                'drivers','producers' => User::whereHas('role', fn ($q) => $q->where('name', $field[3] === 'drivers' ? 'DRIVER' : 'PRODUCER'))->get()->mapWithKeys(fn ($u) => [$u->id => $u->name])->all(),
            };
        }

        return $options;
    }

    public function store(Request $request, string $resource)
    {
        return $this->save($request, $resource);
    }

    private function producerOptions(): array
    {
        $choices = Producer::orderBy('name')->pluck('name', 'id')->all();
        $accounts = User::whereHas('role', fn ($q) => $q->where('name', 'PRODUCER'))
            ->whereNotIn('id', Producer::whereNotNull('user_id')->select('user_id'))->get();
        foreach ($accounts as $account) {
            $choices['user:'.$account->id] = $account->name;
        }
        asort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }

    public function update(Request $request, string $resource, int $record)
    {
        return $this->save($request, $resource, $record);
    }

    private function save(Request $request, string $resource, ?int $id = null)
    {
        $definition = $this->definition($resource);
        $record = $id ? $this->query($definition)->findOrFail($id) : new $definition['model'];
        $rules = collect($definition['fields'])->map(fn ($f) => explode('|', $f[2]))->all();
        if ($resource === 'parcels') {
            $rules['reference'][] = Rule::unique('parcels')->ignore($record->id);
            $rules['producer_id'] = ['required', Rule::in(array_keys($this->producerOptions()))];
        }
        if (in_array($resource, ['drivers', 'producers'])) {
            $rules['user_id'][] = Rule::unique($record->getTable())->ignore($record->id);
        }
        $data = $request->validate($rules);
        foreach ($definition['fields'] as $name => $field) {
            if ($field[1] === 'checkbox') {
                $data[$name] = $request->boolean($name);
            }
        }
        if ($resource === 'gallery' && ! $record->exists && ! $request->hasFile('image')) {
            throw ValidationException::withMessages(['image' => 'Ajoutez une photo.']);
        }
        if ($resource === 'categories') {
            $data['slug'] = Str::slug($data['name']).'-'.($record->id ?? Str::lower(Str::random(6)));
        }
        if ($resource === 'publications') {
            $data['user_id'] = auth()->id();
            if ($data['status'] === 'PUBLISHED' && empty($data['published_at'])) {
                $data['published_at'] = now();
            }
        }
        if (in_array($resource, ['drivers', 'producers']) && ! empty($data['user_id'])) {
            abort_unless(User::whereKey($data['user_id'])->whereHas('role', fn ($q) => $q->where('name', $resource === 'drivers' ? 'DRIVER' : 'PRODUCER'))->exists(), 422);
        }
        if ($resource === 'cultivations') {
            $parcel = Parcel::with('producer')->findOrFail($data['parcel_id']);
            if (auth()->user()->role?->name === 'PRODUCER') {
                abort_unless($parcel->producer->user_id === auth()->id(), 403);
            }
            if ($data['area'] > $parcel->area) {
                throw ValidationException::withMessages(['area' => 'La superficie dépasse celle de la parcelle.']);
            }
            $occupied = Cultivation::where('parcel_id', $parcel->id)->where('status', '!=', 'CLOSED')->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->sum('area');
            if ($data['status'] !== 'CLOSED' && $occupied + $data['area'] > $parcel->area) {
                throw ValidationException::withMessages(['area' => 'Les cultures actives dépasseraient la superficie disponible.']);
            }
            if ($record->exists && $record->harvests()->exists() && ($record->product_id != $data['product_id'] || $record->parcel_id != $data['parcel_id'])) {
                throw ValidationException::withMessages(['product_id' => 'Une culture déjà récoltée conserve son produit et sa parcelle.']);
            }
        }
        if ($resource === 'parcels' && $record->exists && $record->cultivations()->where('status', '!=', 'CLOSED')->sum('area') > $data['area']) {
            throw ValidationException::withMessages(['area' => 'La superficie est inférieure à celle des cultures actives.']);
        }
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $data['image'] = $file->store('media', 'public');
            if (in_array($resource, ['gallery', 'publications'], true)) {
                $data['media_type'] = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
            }
        } else {
            unset($data['image']);
            if (in_array($resource, ['gallery', 'publications'], true) && $record->exists) {
                unset($data['media_type']);
            }
        }
        DB::transaction(function () use ($record, $data, $resource) {
            if ($resource === 'parcels' && str_starts_with((string) $data['producer_id'], 'user:')) {
                $account = User::whereHas('role', fn ($q) => $q->where('name', 'PRODUCER'))
                    ->lockForUpdate()->findOrFail(substr($data['producer_id'], 5));
                $producer = Producer::firstOrCreate(['user_id' => $account->id], [
                    'name' => $account->name,
                    'phone' => $account->phone ?? '',
                    'address' => $account->address,
                    'zone' => $account->city ?? '',
                    'joined_at' => $account->created_at->toDateString(),
                    'status' => $account->status,
                ]);
                $data['producer_id'] = $producer->id;
            }
            $record->fill($data)->save();
            AuditLog::record('save',$resource.':'.$record->id);
        });

        return to_route('admin.resources.index',$resource)->with('success','Enregistrement sauvegardé.');
    }
}
