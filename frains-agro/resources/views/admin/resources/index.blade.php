<x-layouts.admin :title="$definition['title']"><section class="commerce">
<p class="eyebrow">ADMINISTRATION</p><div class="section-heading"><h1>{{ $definition['title'] }}</h1><a class="button" href="{{ route('admin.resources.create',$resource) }}">Ajouter</a></div>
@if($resource === 'parcels')
    <form id="parcel-choice-form" class="panel commerce-form">
        <label for="parcel-choice">Parcelle</label>
        <select id="parcel-choice" required @disabled($parcelChoices->isEmpty())>
            <option value="">Choisir une parcelle</option>
            @foreach($parcelChoices as $parcel)
                <option value="{{ route('admin.resources.edit', ['parcels', $parcel->id]) }}">{{ $parcel->reference }} · {{ $parcel->producer?->name }} · {{ $parcel->location }}</option>
            @endforeach
        </select>
        @if($parcelChoices->isEmpty())
            <p>Aucune parcelle enregistrée. Cliquez sur « Ajouter » pour créer une parcelle.</p>
        @else
            <button class="button" type="submit">Modifier la parcelle</button>
        @endif
    </form>
    <script>
        document.getElementById('parcel-choice-form').addEventListener('submit', function (event) {
            event.preventDefault();
            const destination = document.getElementById('parcel-choice').value;
            if (destination) window.location.assign(destination);
        });
    </script>
@endif
<div class="panel table-scroll"><table><thead><tr>@foreach(array_slice($definition['fields'],0,5,true) as $field)<th>{{ $field[0] }}</th>@endforeach<th></th></tr></thead><tbody>
@forelse($records as $record)<tr>@foreach(array_slice($definition['fields'],0,5,true) as $key=>$field)<td>@if($field[1]==='file')@if($record->$key)@if(($record->media_type ?? 'image') === 'video')<video src="{{ asset('storage/'.$record->$key) }}" width="90" height="64" muted style="object-fit:cover;border-radius:8px"></video>@else<img src="{{ asset('storage/'.$record->$key) }}" width="70" alt="" style="border-radius:8px">@endif @endif @elseif($field[1]==='checkbox'){{ $record->$key?'Oui':'Non' }}@elseif($field[1]==='select'){{ $options[$key][$record->$key] ?? $record->$key ?? 'Non renseigne' }}@else{{ \Illuminate\Support\Str::limit((string)$record->$key,100) }}@endif</td>@endforeach<td><a href="{{ route('admin.resources.edit',[$resource,$record->id]) }}">Modifier</a>
@if($resource === 'gallery')
<form class="inline" method="POST" action="{{ route('admin.gallery.destroy', $record->id) }}" onsubmit="return confirm('Retirer ce média de la galerie ?');">
@csrf @method('DELETE')
<button type="submit" aria-label="Retirer {{ $record->title }} de la galerie">Retirer</button>
</form>
@endif
</td></tr>@empty<tr><td colspan="6">Aucun enregistrement.</td></tr>@endforelse</tbody></table></div>{{ $records->links() }}</section></x-layouts.admin>
