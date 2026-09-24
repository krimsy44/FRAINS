@php($connectedCustomer = auth()->user()?->customer)
<section class="commerce"><h2>Vos coordonnées</h2>
@if($connectedCustomer)
<p>Votre commande sera enregistrée dans votre espace client. Vous pourrez y suivre sa préparation et sa livraison.</p>
@else
<p>Commandez sans créer de compte. Vous avez déjà un compte ? <a href="{{ route('login') }}">Connectez-vous pour retrouver votre commande dans votre espace client.</a></p>
@endif
<form method="POST" action="{{ route('guest.checkout') }}" class="panel commerce-form">
@csrf
<input type="hidden" name="checkout_token" value="{{ session('checkout_token') }}">
<div class="form-grid">
<label>Prénom<input name="first_name" value="{{ old('first_name', auth()->user()?->first_name) }}" maxlength="80" autocomplete="given-name" required></label>
<label>Nom<input name="last_name" value="{{ old('last_name', auth()->user()?->last_name) }}" maxlength="80" autocomplete="family-name" required></label>
<label>Téléphone<input type="tel" name="phone" value="{{ old('phone', $connectedCustomer?->contact_phone) }}" maxlength="30" autocomplete="tel" required></label>
<label>Zone de livraison<select id="cart-zone" name="delivery_zone_id" required><option value="">Choisir une zone</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" data-fee="{{ $zone->base_fee }}" @selected(old('delivery_zone_id') == $zone->id)>{{ $zone->name }} — {{ number_format($zone->base_fee, 0, ',', ' ') }} FCFA de livraison</option>@endforeach</select></label>
</div>
@if($zones->isEmpty())<p role="alert">Aucune zone disponible. Veuillez contacter FRAINS Agro.</p>@endif
<p id="cart-final-total" data-subtotal="{{ $total }}" aria-live="polite">Choisissez une zone pour connaître le total avec livraison.</p>
<p>Règlement à la livraison. Nous vous appellerons pour confirmer l’adresse précise et la date.</p>
<button class="button" @disabled($zones->isEmpty())>Confirmer ma commande</button>
</form></section>
<script>
const cartZone = document.getElementById('cart-zone'), cartTotal = document.getElementById('cart-final-total');
function refreshCartTotal() {
    cartTotal.textContent = cartZone.value ? 'Total avec livraison : ' + (Number(cartTotal.dataset.subtotal) + Number(cartZone.selectedOptions[0].dataset.fee)).toLocaleString('fr-FR') + ' FCFA' : 'Choisissez une zone pour connaître le total avec livraison.';
}
cartZone.addEventListener('change', refreshCartTotal); refreshCartTotal();
</script>
