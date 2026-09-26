@props(['product'])
<article class="wholesale-product">
    <div class="wholesale-product-photo">
        @if($product->image)
            <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" loading="lazy" width="600" height="450">
        @else
            <span class="wholesale-photo-placeholder" aria-hidden="true">{{ strtoupper(mb_substr($product->name, 0, 1)) }}</span>
        @endif
        <span class="wholesale-label">Vente en gros</span>
    </div>
    <div class="wholesale-product-content">
        <p class="wholesale-category">{{ $product->category?->name ?? 'Produit agricole' }}</p>
        <h3>{{ $product->name }}</h3>
        <x-product-availability :product="$product" />
        <div class="wholesale-price">
            <span>Prix de base</span>
            <strong>{{ number_format($product->base_price, 0, ',', ' ') }} <small>FCFA / {{ strtolower($product->unit) }}</small></strong>
            <p>Tarif de votre commande à confirmer sur devis.</p>
        </div>
        <dl class="wholesale-specs">
            <div><dt>Quantité minimum</dt><dd>{{ (float) $product->minimum_order_quantity }} {{ strtolower($product->unit) }}</dd></div>
            @if($product->packaging)<div><dt>Conditionnement</dt><dd>{{ $product->packaging }}</dd></div>@endif
            @if($product->production_zone)<div><dt>Origine</dt><dd>{{ $product->production_zone }}</dd></div>@endif
        </dl>
        <div class="wholesale-product-actions">
            <a class="wholesale-quote-button" href="{{ route('quotes.start', ['product' => $product->id]) }}">Demander un devis <span aria-hidden="true">&rarr;</span></a>
            <a class="wholesale-contact-button" href="{{ route('public.page', 'contact') }}">Contacter l’administration</a>
        </div>
    </div>
</article>
