<x-layouts.app :title="$product->name.' - FRAINS Agro'">
    <section class="product-page">
        <div class="product-shell">
            <aside class="product-media-panel" aria-label="Image du produit">
                <div class="product-media-frame">
                    @if($product->image)
                        <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
                    @else
                        <span>{{ strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                    @endif
                </div>
            </aside>

            <article class="product-info-panel">
                <div class="product-kicker">
                    <span class="product-badge">{{ $product->category?->name ?? 'Produit agricole' }}</span>
                    <span class="product-stock-badge {{ ($product->stock?->available_quantity ?? 0) > 0 ? 'available' : 'unavailable' }}">
                        {{ ($product->stock?->available_quantity ?? 0) > 0 ? 'Disponible' : 'Indisponible' }}
                    </span>
                </div>

                <h1>{{ $product->name }}</h1>
                <p class="product-description">
                    {{ $product->description ?: 'Produit frais issu des exploitations partenaires FRAINS Agro.' }}
                </p>

                <div class="product-price-box">
                    <span>Prix de vente</span>
                    <strong>{{ number_format($product->base_price, 0, ',', ' ') }} FCFA</strong>
                    <small>/ {{ strtolower($product->unit) }}</small>
                </div>

                <div class="product-facts" aria-label="Details produit">
                    <div>
                        <span>Stock disponible</span>
                        <strong>{{ number_format($product->stock?->available_quantity ?? 0, 0, ',', ' ') }} {{ strtolower($product->unit) }}</strong>
                    </div>
                    <div>
                        <span>Commande minimum</span>
                        <strong>{{ $product->minimum_order_quantity }} {{ strtolower($product->unit) }}</strong>
                    </div>
                    <div>
                        <span>Conditionnement</span>
                        <strong>{{ $product->packaging ?? 'A preciser' }}</strong>
                    </div>
                    <div>
                        <span>Origine</span>
                        <strong>{{ $product->production_zone ?? 'A preciser' }}</strong>
                    </div>
                </div>

                @if($product->wholesale_available || $product->quote_threshold)
                    <div class="product-quote-box">
                        <div>
                            <strong>Vente en gros</strong>
                            <p>
                                @if($product->quote_threshold)
                                    A partir de {{ $product->quote_threshold }} {{ strtolower($product->unit) }}, le tarif est traite sur devis.
                                @else
                                    Tarifs professionnels disponibles selon les quantites.
                                @endif
                            </p>
                        </div>
                        <a class="button" href="{{ route('quotes.start', ['product' => $product->id]) }}">Demander un devis</a>
                    </div>
                @endif

                <form class="product-cart-form" method="POST" action="{{ route('cart.store', $product) }}">
                    @csrf
                    <label>
                        <span>Quantite</span>
                        <input type="number" step="0.01" min="{{ $product->minimum_order_quantity }}" name="quantity" value="{{ $product->minimum_order_quantity }}" required>
                    </label>
                    <button class="button" @disabled(($product->stock?->available_quantity ?? 0) <= 0)>Ajouter au panier</button>
                </form>
            </article>
        </div>
    </section>
</x-layouts.app>
