<x-layouts.admin title="Production et récoltes"><section class="commerce"><h1>Production et récoltes</h1><a href="{{ route('admin.resources.index','cultivations') }}">Gérer les cultures</a><div class="panel table-scroll"><table><tr><th>Culture / parcelle</th><th>Prévu</th><th>Récolté brut</th><th>Pertes</th><th>Entrée nette en stock</th></tr>@foreach($cultures as $culture)<tr><td>{{ $culture->product->name }} · {{ $culture->parcel->reference }} · {{ $culture->parcel->producer->name }}</td><td>{{ $culture->expected_quantity }} {{ $culture->product->unit }}</td><td>{{ $culture->harvests_sum_quantity??0 }}</td><td>{{ $culture->harvests_sum_loss_quantity??0 }}</td><td>{{ ($culture->harvests_sum_quantity??0)-($culture->harvests_sum_loss_quantity??0) }}</td></tr>@endforeach</table></div>
<form class="panel commerce-form" method="POST" action="{{ route('admin.harvests.store') }}">@csrf<input type="hidden" name="submission_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><h2>Enregistrer une récolte</h2>
@php($availableCultures = $cultures->where('status', '!=', 'CLOSED'))
<label>Culture
    <select id="harvest-cultivation" name="cultivation_id" required>
        <option value="">Choisir une culture</option>
        @foreach($availableCultures->sortBy('product.name') as $culture)
            <option value="{{ $culture->id }}" data-planted-at="{{ \Illuminate\Support\Carbon::parse($culture->planted_at)->toDateString() }}" @selected((string) old('cultivation_id') === (string) $culture->id)>{{ $culture->product->name }} · {{ $culture->parcel->reference }} ({{ $culture->product->unit }}) — Plantation : {{ \Illuminate\Support\Carbon::parse($culture->planted_at)->format('d/m/Y') }}</option>
        @endforeach
    </select>
</label>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const culture = document.getElementById('harvest-cultivation');
    const date = culture.form.elements.harvested_at;
    const update = () => {
        const planted = culture.selectedOptions[0]?.dataset.plantedAt || '';
        date.min = planted;
    };
    culture.addEventListener('change', update);
    update();
});
</script>
@if($availableCultures->isEmpty())
    <p>Aucune culture en cours. Ajoutez une culture dans « Gérer les cultures » pour enregistrer une récolte.</p>
@endif
<label>Date<input type="date" name="harvested_at" value="{{ old('harvested_at') }}" max="{{ today()->toDateString() }}" required></label><label>Quantité brute récoltée<input type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required></label><label>Pertes à la récolte<input type="number" name="loss_quantity" min="0" step="0.01" value="{{ old('loss_quantity', 0) }}" required></label><x-harvest-net /><label>Observations<textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label><button class="button">Enregistrer et alimenter le stock</button></form>
<h2>Historique</h2>@foreach($harvests as $harvest)<p>{{ $harvest->harvested_at }} · {{ $harvest->cultivation->product->name }} : Brut : {{ $harvest->quantity }} {{ $harvest->cultivation->product->unit }} &middot; Pertes : {{ $harvest->loss_quantity }} {{ $harvest->cultivation->product->unit }} &middot; Entr&eacute;e nette en stock : {{ number_format($harvest->quantity - $harvest->loss_quantity, 2, ',', ' ') }} {{ $harvest->cultivation->product->unit }} · Vendu : {{ $harvest->sold ?? 'Origine non tracée' }} · Restant du lot : {{ $harvest->remaining ?? 'Origine non tracée' }} <a href="{{ route('admin.harvests.edit', $harvest) }}">Modifier</a></p>@endforeach{{ $harvests->links() }}</section></x-layouts.admin>
