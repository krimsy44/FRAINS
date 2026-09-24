<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'FRAINS Agro' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"><link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/commerce.css') }}?v={{ filemtime(public_path('css/commerce.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/gie.css') }}?v={{ filemtime(public_path('css/gie.css')) }}">
</head>
<body>
@php($role = auth()->user()?->role?->name)
<header class="site-header">
    <a class="brand" href="{{ route('home') }}">@if($logo=\App\Models\Setting::where('key','logo_path')->value('value'))<img src="{{ asset('storage/'.$logo) }}" alt="Logo FRAINS" class="brand-logo" width="118" height="78">@else<span>F</span>@endif FRAINS <em>Agro</em></a>
    <button class="mobile-menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Ouvrir le menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path class="menu-bars" d="M4 6h16M4 12h16M4 18h16"/><path class="menu-close" d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
    <nav id="main-navigation" aria-label="Navigation principale">
        @if($role === 'CUSTOMER')
            <a href="{{ route('home') }}">Accueil</a>
            <a href="{{ route('products.index') }}">Produits</a>
            <a href="{{ route('cart.index') }}">Panier <b>{{ count(session('cart', [])) }}</b></a>
            <a href="{{ route('account.section') }}">Espace client</a>
            <a href="{{ route('account.orders.index') }}">Suivi commandes</a>
            <a href="{{ route('quotes.index') }}">Mes devis</a>
            <a href="{{ route('notifications.index') }}">Notifications</a>
            <form class="logout" method="POST" action="{{ route('logout') }}">@csrf<button>Deconnexion</button></form>
        @else
            <a href="{{ route('home') }}">Accueil</a>
            <a href="{{ route('products.index') }}">Produits</a>
            <a href="{{ route('public.page','a-propos') }}">Le GIE</a>
            <a href="{{ route('public.page','vente-en-gros') }}">Vente en gros</a>
            <a href="{{ route('public.page','actualites') }}">Actualites</a>
            <a href="{{ route('public.page','galerie') }}">Galerie</a>
            <a href="{{ route('public.page','contact') }}">Contact</a>
            <a href="{{ route('cart.index') }}">Panier <b>{{ count(session('cart', [])) }}</b></a>
            @guest<a href="{{ route('login') }}">Espace client</a>@endguest
            @auth<form class="logout" method="POST" action="{{ route('logout') }}">@csrf<button>Deconnexion</button></form>@endauth
        @endif
    </nav>
</header>
@if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif
@if($errors->any())<div class="validation-errors" role="alert"><strong>Veuillez verifier les informations suivantes :</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<main @class(['client-image-background' => request()->routeIs('login', 'account.*')])>{{ $slot }}</main>
<footer><strong>FRAINS Agro</strong><span>Fraternite Agricole Les Inseparables - Senegal</span><span>{{ \App\Models\Setting::where('key', 'address')->value('value') ?? 'Dakar/Diogo' }}</span><span>Contact : {{ \App\Models\Setting::where('key', 'phone')->value('value') ?? '779890101' }}</span>@if(request()->routeIs('home'))<x-footer-socials />@endif</footer>
<script>
(() => {
    const header = document.querySelector('.site-header');
    const toggle = header.querySelector('.mobile-menu-toggle');
    const navigation = document.getElementById('main-navigation');
    const setOpen = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
        header.classList.toggle('menu-open', open);
    };
    header.classList.add('menu-ready');
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!header.contains(event.target)) setOpen(false);
    });
    navigation.addEventListener('click', (event) => {
        if (event.target.closest('a')) setOpen(false);
    });
    window.matchMedia('(max-width: 800px)').addEventListener('change', () => setOpen(false));
})();
</script>
@if(request()->routeIs('home', 'products.index', 'products.show', 'public.page', 'public.publication'))
    <script src="{{ asset('js/visitor-updates.js') }}?v={{ filemtime(public_path('js/visitor-updates.js')) }}"></script>
@endif
<x-home-whatsapp />
<x-required-fields />
</body></html>
