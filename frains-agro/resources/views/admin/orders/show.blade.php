<x-layouts.admin title="Gestion de commande — FRAINS Agro"><section class="commerce"><a href="{{ route('admin.orders.index') }}">← Commandes</a><h1>{{ $order->order_number }}</h1><p>{{ $order->customer?->name }} · {{ $order->customer?->contact_phone }}</p>
@include('orders.details')
<x-delete-order :order="$order" />
@if(!in_array($order->status,['CANCELLED','REFUSED']) && $order->balance>0)
<form class="panel commerce-form" method="POST" action="{{ route('admin.payments.store',$order) }}">@csrf<input type="hidden" name="submission_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><h2>Enregistrer un règlement partiel ou intégral</h2><label>Montant reçu (FCFA)<input name="amount" type="number" min="0.01" step="0.01" max="{{ $order->balance }}" required></label><label>Mode<select name="method" required><option value="CASH">Espèces</option><option value="BANK_TRANSFER">Virement</option><option value="MOBILE_MONEY">Paiement mobile</option></select></label><label>Référence du versement<input name="external_reference" maxlength="120"></label><button class="button">Confirmer l’encaissement</button></form>
<form class="panel commerce-form" method="POST" action="{{ route('admin.orders.due',$order) }}">@csrf @method('PUT')<h2>Paiement différé professionnel</h2><p>Le client doit disposer d’un plafond de crédit suffisant dans sa fiche.</p><label>Échéance<input name="payment_due_date" type="date" min="{{ today()->toDateString() }}" required></label><button>Enregistrer l’échéance</button></form>
@endif
@if(!in_array($order->status, ['COMPLETED','CANCELLED','REFUSED']))
<form class="panel commerce-form" method="POST" action="{{ route('admin.orders.update', $order) }}">@csrf @method('PATCH')
<h2 id="traiter-commande">Traiter la commande</h2>
@php($nextStatuses = $order->nextStatuses())
<label>Étape
    <select name="status" required aria-describedby="order-stage-help">
        @foreach(\App\Models\Order::STATUSES as $status => $label)
            <option value="{{ $status }}" @selected(old('status', $order->status) === $status) @disabled($status !== $order->status && !in_array($status, $nextStatuses, true))>{{ $label }}{{ $status === $order->status ? ' (étape actuelle)' : '' }}</option>
        @endforeach
    </select>
</label>
<small id="order-stage-help">Toutes les étapes sont affichées. Les étapes grisées ne sont pas accessibles depuis l’étape actuelle. Une livraison partielle est enregistrée dans « Livraisons », en précisant les quantités reçues.</small>
<label>Nom du livreur<select name="driver_id" id="order-driver" data-required-when="status" data-required-value="SHIPPING">
<option value="">{{ $order->delivery?->driver_name ? 'Livreur actuel : '.$order->delivery->driver_name.' — choisir un livreur enregistré' : 'Choisir un livreur' }}</option>
@foreach($drivers as $driver)
<option value="{{ $driver->id }}" data-phone="{{ $driver->phone }}" @selected((string) old('driver_id', $order->delivery?->driver_id) === (string) $driver->id) @disabled(!$driver->is_available && $order->delivery?->driver_id != $driver->id)>{{ $driver->name }}{{ !$driver->is_available ? ' (indisponible)' : '' }}</option>
@endforeach
</select></label>
<label>Téléphone du livreur<input id="order-driver-phone" type="tel" value="{{ $order->delivery?->driver_phone }}" readonly></label>
<script>
(() => {
    const select = document.getElementById('order-driver');
    const phone = document.getElementById('order-driver-phone');
    const initialPhone = phone.value;
    const update = () => { phone.value = select.selectedOptions[0]?.dataset.phone ?? initialPhone; };
    select.addEventListener('change', update);
    update();
})();
</script>
<label>Date prévue<input name="scheduled_date" type="date" value="{{ old('scheduled_date', $order->delivery?->scheduled_date?->toDateString()) }}"></label>
<label>Message de suivi pour le client<textarea name="tracking_message" maxlength="1000" rows="4" placeholder="Ex. Votre commande est en preparation et sera prete demain matin.">{{ old('tracking_message') }}</textarea></label>
@if($order->payment_status !== 'PAID')<label class="check"><input name="mark_paid" type="checkbox" value="1"> Je confirme avoir reçu le règlement intégral de {{ number_format($order->total, 0, ',', ' ') }} FCFA.</label>@endif
<button class="button">Enregistrer</button></form>@endif</section></x-layouts.admin>
