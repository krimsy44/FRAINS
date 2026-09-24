<x-layouts.admin title="Modifier une récolte">
    <section class="commerce">
        <p><a href="{{ route('admin.agriculture.index') }}">&larr; Production et récoltes</a></p>
        <h1>Modifier une récolte</h1>
        <p>{{ $culture->product->name }} · {{ $culture->parcel->reference }} ({{ $culture->product->unit }})</p>
        <form class="panel commerce-form" method="POST" action="{{ route('admin.harvests.update', $harvest) }}">
            @csrf
            @method('PUT')
            <label>Date<input type="date" name="harvested_at" value="{{ old('harvested_at', \Illuminate\Support\Carbon::parse($harvest->harvested_at)->toDateString()) }}" min="{{ \Illuminate\Support\Carbon::parse($culture->planted_at)->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
            <label>Quantité brute récoltée<input type="number" name="quantity" value="{{ old('quantity', $harvest->quantity) }}" min="0.01" step="0.01" required></label>
            <label>Pertes à la récolte<input type="number" name="loss_quantity" value="{{ old('loss_quantity', $harvest->loss_quantity) }}" min="0" step="0.01" required></label>
            <label>Observations<textarea name="notes" maxlength="2000">{{ old('notes', $harvest->notes) }}</textarea></label>
            <button class="button" type="submit">Enregistrer les modifications</button>
            <a href="{{ route('admin.agriculture.index') }}">Annuler</a>
        </form>
    </section>
</x-layouts.admin>
