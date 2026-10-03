<x-layouts.admin :title="$definition['title']">
<section class="commerce">
    <a href="{{ route('admin.resources.index', $resource) }}">&larr; {{ $definition['title'] }}</a>
    <h1>{{ $record->exists ? 'Modifier' : 'Ajouter' }}</h1>

    <form class="panel commerce-form" method="POST" enctype="multipart/form-data" action="{{ $record->exists ? route('admin.resources.update', [$resource, $record->id]) : route('admin.resources.store', $resource) }}">
        @csrf
        @if($record->exists)
            @method('PUT')
        @endif

        @foreach($definition['fields'] as $name => $field)
            @php($value = old($name, $record->{$name} ?? null))
            @php($isRequired = in_array('required', explode('|', $field[2]), true) || ($resource === 'gallery' && $name === 'image' && !$record->exists))
            <label>{{ $field[0] }}
                @if($field[1] === 'textarea')
                    <textarea name="{{ $name }}" rows="5" @required($isRequired)>{{ $value }}</textarea>
                @elseif($field[1] === 'select')
                    <select name="{{ $name }}" @required($isRequired)>
                        <option value="">{{ $resource === 'cultivations' && $name === 'product_id' ? 'Choisir une culture' : ($resource === 'parcels' && $name === 'producer_id' ? 'Choisir un producteur' : 'Choisir') }}</option>
                        @foreach($options[$name] as $optionValue => $label)
                            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($resource === 'cultivations' && $name === 'product_id')
                        <small>Choisissez le produit cultivé sur cette parcelle. L’unité de récolte est indiquée entre parenthèses.</small>
                        @if(empty($options[$name]))
                            <small>Aucune culture disponible : un administrateur doit d’abord ajouter un produit dans le catalogue.</small>
                        @endif
                    @endif
                @elseif($field[1] === 'checkbox')
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $record->{$name} ?? true))>
                @elseif($field[1] === 'file')
                    <input type="file" name="{{ $name }}" @required($isRequired) accept="{{ $resource === 'gallery' ? 'image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm' : ($resource === 'publications' ? 'image/jpeg,image/png,image/webp,video/mp4,video/webm' : 'image/jpeg,image/png,image/webp') }}">
                    @if($resource === 'publications')<small>Ajoutez une photo ou une vidéo MP4/WebM. Sans nouveau fichier, le média actuel est conservé. Privilégiez MP4 (H.264) pour la lecture sur mobile.</small>@endif
                    @if($record->{$name} ?? null)
                        @if(($record->media_type ?? 'image') === 'video')
                            <video src="{{ asset('storage/'.$record->{$name}) }}" controls width="220" style="display:block;margin-top:10px;max-width:100%;border-radius:8px"></video>
                        @else
                            <img src="{{ asset('storage/'.$record->{$name}) }}" width="140" alt="" style="display:block;margin-top:10px;max-width:100%;height:auto">
                        @endif
                    @endif
                @else
                    <input type="{{ $field[1] }}" name="{{ $name }}" @required($isRequired) @if($field[1] === 'number') step="0.01" @endif value="{{ $value instanceof \Carbon\CarbonInterface ? $value->format($field[1] === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i') : $value }}">
                @endif
            </label>
        @endforeach

        <button class="button">Enregistrer</button>
    </form>
</section>
</x-layouts.admin>
