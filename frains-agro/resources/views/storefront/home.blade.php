<x-layouts.app title="FRAINS Agro - Produits agricoles du Senegal">
    <section class="hero hero-with-image">
        <div>
            <p class="eyebrow">PRODUCTEURS - QUALITE - PROXIMITE</p>
            <h1>De nos champs<br>a vos <i>tables.</i></h1>
            <p>FRAINS Agro relie les productions maraicheres du GIE aux professionnels et aux familles du Senegal.</p>
            <a class="button" href="{{ route('products.index') }}">Decouvrir le catalogue</a>
        </div>
    </section>

    <section class="section">
        <div class="section-heading">
            <p class="eyebrow">NOS PRODUITS</p>
            <h2>La fraicheur au rythme des saisons.</h2>
            <a href="{{ route('products.index') }}">Voir tout</a>
        </div>
        <div class="grid products">
            @forelse($featuredProducts as $product)
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
                    <a href="{{ route('products.show', $product) }}">Voir le produit</a>
                </article>
            @empty
                <p>Le catalogue arrive bientot.</p>
            @endforelse
        </div>
    </section>

    <section id="apropos" class="impact">
        <div>
            <p class="eyebrow">NOTRE ENGAGEMENT</p>
            <h2>Une agriculture locale, organisee et durable.</h2>
        </div>
        <div>
            <p>FRAINS Agro centralise la production, le stock et la commercialisation du GIE pour proposer une offre fiable a ses clients.</p>
        </div>
    </section>

    <section class="commerce">
        <h2>Pour les professionnels</h2>
        <p>Des commandes adaptees aux quantites et au conditionnement de votre activite.</p>
        <div class="grid">
            @foreach($wholesaleProducts as $product)
                <article class="card">
                    <h3>{{ $product->name }}</h3>
                    <p>{{ $product->packaging }}</p>
                    <a href="{{ route('products.show', $product) }}">Voir l'offre</a>
                </article>
            @endforeach
        </div>
        <p><a class="button" href="{{ route('quotes.start') }}">Demander un devis</a></p>
    </section>


    @if(!empty($settings['testimonials']))
        <section class="commerce panel">
            <h2>Ils temoignent</h2>
            <p style="white-space:pre-line">{{ $settings['testimonials'] }}</p>
        </section>
    @endif

    <section class="commerce">
        <h2>Parlons de vos besoins</h2>
        <p>{{ $settings['address'] ?? '' }} - {{ $settings['phone'] ?? '' }} - {{ $settings['email'] ?? '' }}</p>
        <form class="panel commerce-form" method="POST" action="{{ route('public.contact') }}">
            @csrf
            <label>Votre nom<input name="name" required maxlength="120"></label>
            <label>E-mail<input name="email" type="email" required></label>
            <label>Objet<input name="subject" required maxlength="255"></label>
            <label>Message<textarea name="message" required maxlength="5000"></textarea></label>
            <button class="button">Envoyer votre message</button>
        </form>
    </section>
</x-layouts.app>
