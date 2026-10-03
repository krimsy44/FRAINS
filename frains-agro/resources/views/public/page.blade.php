<x-layouts.app :title="['a-propos'=>'A propos du GIE','activites'=>'Nos activites','vente-en-gros'=>'Vente en gros','actualites'=>'Actualites','galerie'=>'Galerie','contact'=>'Contact'][$page]"><section class="commerce">
@if($page==='vente-en-gros')
<p class="catalog-back-link"><a href="{{ url()->previous() }}"><span aria-hidden="true">&larr;</span> Retour</a></p>
@endif
<h1>{{ ['a-propos'=>'Fraternite Agricole Les Inseparables','activites'=>'Nos activites agricoles','vente-en-gros'=>'Approvisionnez votre activite','actualites'=>'Actualites FRAINS','galerie'=>'Notre galerie','contact'=>'Contactez FRAINS'][$page] }}</h1>
@if($page==='a-propos')
@include('public.about')
@elseif($page==='vente-en-gros')
<p>Grossistes, revendeurs, restaurants, hotels, supermarches et entreprises : selectionnez vos produits et quantites dans le catalogue, ou demandez une proposition avec le conditionnement, le lieu et la date souhaites.</p>
<div class="wholesale-actions">
    <a class="button" href="{{ route('products.index',['wholesale'=>1]) }}">Produits disponibles en gros</a>
    <a class="button" href="{{ route('quotes.start') }}">Demander un devis</a>
</div>
@elseif(in_array($page,['activites','actualites']))<div class="grid">@forelse($publications as $publication)<article class="card"><x-publication-media :publication="$publication" :compact="true" /><h2>{{ $publication->title }}</h2><p>{{ $publication->published_at->format('d/m/Y') }}</p><p>{{ \Illuminate\Support\Str::limit($publication->content,180) }}</p><a href="{{ route('public.publication',$publication) }}">Lire</a></article>@empty<p>Aucune publication pour le moment.</p>@endforelse</div>{{ $publications->links() }}
@elseif($page==='galerie')
<div class="gallery-card-grid">
    @forelse($images as $photo)
        <figure class="gallery-card-item">
            <div class="gallery-card-media">
                @if(($photo->media_type ?? 'image') === 'video')
                    <video src="{{ asset('storage/'.$photo->image) }}" controls preload="metadata"></video>
                @else
                    <img src="{{ asset('storage/'.$photo->image) }}" alt="{{ $photo->title }}" loading="lazy">
                @endif
            </div>
            <figcaption class="gallery-card-caption">
                <strong>{{ $photo->title }}</strong>
                @if($photo->description)<p>{{ $photo->description }}</p>@endif
            </figcaption>
        </figure>
    @empty
        <p>Les photos du GIE seront publiees ici.</p>
    @endforelse
</div>
{{ $images->links() }}
@elseif($page==='contact')
<p>{{ $settings['address']??'' }} &middot; {{ $settings['phone']??'' }} &middot; {{ $settings['email']??'' }}</p>
<form class="panel commerce-form" method="POST" action="{{ route('public.contact') }}">
    @csrf
    <p>Une question sur nos produits ou votre commande ? Écrivez-nous à l’aide du formulaire ci-dessous. Tous les champs sont obligatoires, sauf le téléphone.</p>
    @foreach(['name'=>'Votre nom','email'=>'Votre adresse e-mail','phone'=>'Téléphone (facultatif)','subject'=>'Objet de votre message'] as $name=>$label)
        <label>{{ $label }}<input name="{{ $name }}" type="{{ $name==='email'?'email':($name==='phone'?'tel':'text') }}" value="{{ old($name) }}" @required($name!=='phone')></label>
    @endforeach
    <label>Votre message<textarea name="message" rows="6" maxlength="5000" required>{{ old('message') }}</textarea></label>
    <button class="button" type="submit">Envoyer le message</button>
</form>
@endif
</section></x-layouts.app>





