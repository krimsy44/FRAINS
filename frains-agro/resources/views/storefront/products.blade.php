<x-layouts.app :title="$wholesaleOnly ? 'Vente en gros - FRAINS Agro' : 'Catalogue - FRAINS Agro'">
    <section @class(['page-head catalog-head', 'wholesale-heading' => $wholesaleOnly])>
        <p class="catalog-back-link"><a href="{{ url()->previous() }}">&larr; Retour</a></p>
        <div class="catalog-title">
            <p class="eyebrow">{{ $wholesaleOnly ? 'FRAINS AGRO · VENTE EN GROS' : 'CATALOGUE' }}</p>
            <h1>{{ $wholesaleOnly ? 'Nos produits disponibles en gros' : 'Nos produits agricoles' }}</h1>
            @if($wholesaleOnly)<p>Découvrez nos produits disponibles en gros. Cliquez sur « Demander un devis » pour échanger directement avec le GIE sur WhatsApp.</p>@endif
        </div>

        <form class="filters product-search-card" method="GET">
            @if($wholesaleOnly)<input type="hidden" name="wholesale" value="1">@endif
            <input name="q" value="{{ request('q') }}" placeholder="Recherche produits" aria-label="Recherche produits">

            <select name="category" aria-label="Catégories">
                <option value="">Toutes les categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                @endforeach
            </select>

            <button type="submit">Rechercher</button>
        </form>
    </section>

    <section @class(['section compact', 'wholesale-catalogue' => $wholesaleOnly, 'retail-catalogue' => !$wholesaleOnly])>
        @if($wholesaleOnly)<div class="wholesale-results"><h2>Notre sélection en gros</h2><span>{{ $products->total() }} produit(s)</span></div>@endif
        <div @class(['grid products', 'wholesale-grid' => $wholesaleOnly])>
            @forelse($products as $product)
                @if($wholesaleOnly)
                    <x-wholesale-product :product="$product" :whatsapp-phone="$whatsappPhone" />
                @else
                <article class="card">
                    <a class="product-image catalog-product-photo" href="{{ route('products.show', $product) }}" aria-label="Voir {{ $product->name }}">
                        @if($product->image)
                            <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" data-product-sku="{{ $product->sku }}" width="600" height="450" loading="lazy">
                        @else
                            {{ strtoupper(mb_substr($product->name, 0, 1)) }}
                        @endif
                    </a>
                    <div class="catalog-product-info">
                    <p>{{ $product->category?->name ?? 'Produit agricole' }}</p>
                    <h3>{{ $product->name }}</h3>
                    <strong>{{ number_format($product->base_price, 0, ',', ' ') }} FCFA <small>/ {{ strtolower($product->unit) }}</small></strong>
                    <x-product-availability :product="$product" />
                    <a href="{{ route('products.show', $product) }}">Voir le produit &rarr;</a>
                    </div>
                </article>
                @endif
            @empty
                <p>Aucun produit ne correspond a votre recherche.</p>
            @endforelse
        </div>

        {{ $products->links() }}
    </section>
</x-layouts.app>
