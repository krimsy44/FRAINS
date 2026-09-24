<x-layouts.app title="Valider ma commande — FRAINS Agro">
<section class="commerce"><p class="eyebrow">COMMANDE</p><h1>Livraison et règlement</h1>
<div class="panel">@foreach($cart as $line)<p>{{ $line['name'] }} · {{ $line['quantity'] }} {{ strtolower($line['unit']) }} — {{ number_format($line['total'], 0, ',', ' ') }} FCFA</p>@endforeach
<p><strong>Produits : {{ number_format($subtotal, 0, ',', ' ') }} FCFA</strong></p><a href="{{ route('cart.index') }}">Modifier le panier</a></div>
<form class="panel commerce-form" method="POST" action="{{ route('checkout.store') }}">@csrf
<input type="hidden" name="checkout_token" value="{{ session('checkout_token') }}">
<label>Zone de livraison<select id="delivery-zone" name="delivery_zone_id" required>
@foreach($zones as $zone)<option value="{{ $zone->id }}" data-fee="{{ $zone->base_fee }}" @selected(old('delivery_zone_id', $customer->delivery_zone_id) == $zone->id)>{{ $zone->name }} — {{ number_format($zone->base_fee, 0, ',', ' ') }} FCFA</option>@endforeach
</select></label>
<label>Adresse complète<textarea name="delivery_address" maxlength="500" required>{{ old('delivery_address', auth()->user()->address) }}</textarea></label>
<label>Date de livraison souhaitée<input name="scheduled_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('scheduled_date', now()->addDay()->toDateString()) }}" required></label>
<p>La date souhaitée sera confirmée par FRAINS lors de la préparation.</p>
<label>Mode de règlement<select name="payment_method" required>@foreach(['CASH'=>'À la livraison', 'BANK_TRANSFER'=>'Virement bancaire', 'MOBILE_MONEY'=>'Paiement mobile (confirmation manuelle)'] as $value=>$label)<option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>@endforeach</select></label>
<p>Le choix du mode de règlement ne déclenche aucun débit. FRAINS vous communiquera les instructions nécessaires.</p>
<label>Instructions (facultatif)<textarea name="notes" maxlength="1000">{{ old('notes') }}</textarea></label>
<p id="checkout-total" data-subtotal="{{ $subtotal }}" aria-live="polite"></p>
<button class="button" @disabled($zones->isEmpty())>Confirmer ma commande</button>
</form></section>
<script>
const zone = document.getElementById('delivery-zone'), total = document.getElementById('checkout-total');
function refreshTotal() { const fee = Number(zone.selectedOptions[0]?.dataset.fee || 0); total.textContent = 'Total avec livraison : ' + (Number(total.dataset.subtotal) + fee).toLocaleString('fr-FR') + ' FCFA'; }
zone.addEventListener('change', refreshTotal); refreshTotal();
</script></x-layouts.app>
