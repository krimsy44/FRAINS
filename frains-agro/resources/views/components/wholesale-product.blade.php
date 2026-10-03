@props(['product', 'whatsappPhone'])
@php($message = rawurlencode('Bonjour FRAINS Agro, je souhaite un devis pour le produit '.$product->name.' (référence '.$product->sku.') disponible en gros. Merci de me communiquer vos tarifs et conditions.'))
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
        <p class="wholesale-minimum">Minimum : {{ (float) $product->minimum_order_quantity }} {{ strtolower($product->unit) }} · Prix sur devis</p>
        <div class="wholesale-product-actions">
            <a class="wholesale-quote-button" href="https://wa.me/{{ $whatsappPhone }}?text={{ $message }}" target="_blank" rel="noopener noreferrer" aria-label="Demander un devis pour {{ $product->name }} sur WhatsApp (nouvel onglet)">Demander un devis <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</article>
