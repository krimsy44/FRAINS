<x-layouts.app title="Commande reçue — FRAINS Agro">
<section class="commerce"><h1>Votre commande est enregistrée</h1>
<p>Merci {{ $order->customer->name }}. Conservez la référence <strong>{{ $order->order_number }}</strong>.</p>
<p>Nous vous contacterons au {{ $order->delivery->customer_phone }} pour confirmer l’adresse et la date de livraison dans la zone {{ $order->delivery->zone->name }}.</p>
<div class="panel">@foreach($order->items as $item)<p>{{ $item->product_name }} · {{ $item->quantity }} {{ $item->unit }} — {{ number_format($item->total, 0, ',', ' ') }} FCFA</p>@endforeach
<p>Livraison : {{ number_format($order->delivery_fee, 0, ',', ' ') }} FCFA</p><strong>Total : {{ number_format($order->total, 0, ',', ' ') }} FCFA</strong></div>
<p>Règlement à la livraison. Aucun paiement n’a été prélevé.</p>
<a class="button" href="{{ route('home') }}">Retour à l’accueil</a>
</section></x-layouts.app>
