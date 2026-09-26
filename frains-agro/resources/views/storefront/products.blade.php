<x-layouts.app :title="$wholesaleOnly ? 'Vente en gros - FRAINS Agro' : 'Catalogue - FRAINS Agro'">
    <section class="page-head catalog-head">
        <p class="catalog-back-link"><a href="{{ url()->previous() }}">&larr; Retour</a></p>
        <div class="catalog-title">
            <p class="eyebrow">CATALOGUE</p>
            <h1>{{ $wholesaleOnly ? 'Nos produits disponibles en gros' : 'Nos produits agricoles' }}</h1>
            @if($wholesaleOnly)<p>Découvrez nos produits disponibles en gros. Pour préparer votre achat, demandez un devis ou contactez directement notre administration.</p>@endif
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

    <section class="section compact">
        <div class="grid products">
            @forelse($products as $product)
                <article class="card">
                    <div class="product-image">
                        @if($product->image)
                            <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" style="width:100%;height:130px;object-fit:cover">
                        @else
                            {{ strtoupper(mb_substr($product->name, 0, 1)) }}
                        @endif
                    </div>
                    <p>{{ $product->category?->name ?? 'Produit agricole' }}</p>
                    <h3>{{ $product->name }}</h3>
                    <strong>{{ number_format($product->base_price, 0, ',', ' ') }} FCFA <small>/ {{ strtolower($product->unit) }}</small></strong>
                    <x-product-availability :product="$product" />
                    @unless($wholesaleOnly)<a href="{{ route('products.show', $product) }}">Details</a>@endunless
                    @if($wholesaleOnly)
                        <p>Minimum : {{ (float) $product->minimum_order_quantity }} {{ strtolower($product->unit) }}@if($product->packaging) · {{ $product->packaging }}@endif</p>
                        <a class="button" href="{{ route('quotes.start', ['product' => $product->id]) }}">Demander un devis</a>
                        <a href="{{ route('public.page', 'contact') }}">Contacter l’administration</a>
                    @endif
                </article>
            @empty
                <p>Aucun produit ne correspond a votre recherche.</p>
            @endforelse
        </div>

        {{ $products->links() }}
    </section>
</x-layouts.app>
