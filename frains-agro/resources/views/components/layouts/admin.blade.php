<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Administration — FRAINS Agro' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/commerce.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body class="admin-area">
<header class="admin-header">
    <a class="brand" href="{{ route('admin.dashboard') }}">@if($logo=\App\Models\Setting::where('key','logo_path')->value('value'))<img src="{{ asset('storage/'.$logo) }}" alt="Logo FRAINS" class="brand-logo" width="126" height="84">@else<span>F</span>@endif FRAINS <em>Agro</em></a>
    <strong class="admin-label">Administration</strong>
    <nav aria-label="Compte et accès au site">
        <span>{{ auth()->user()->first_name }} · {{ auth()->user()->role?->name }}</span>
        <a href="{{ route('home') }}">Accueil du site</a>
        <form class="logout" method="POST" action="{{ route('admin.logout') }}">@csrf<button>Déconnexion</button></form>
    </nav>
</header>
<div class="admin-shell">
    <x-admin-nav />
    <main id="admin-content">
        @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="validation-errors" role="alert"><strong>Veuillez vérifier les informations suivantes :</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(!request()->routeIs('admin.dashboard'))
            <p class="admin-back-link"><a href="{{ url()->previous() }}">&larr; Retour</a></p>
        @endif
        {{ $slot }}
    </main>
</div>
<script>
(() => {
    const header = document.querySelector('.admin-header');
    const updateHeaderHeight = () => document.documentElement.style.setProperty('--admin-header-height', `${header.getBoundingClientRect().height}px`);
    updateHeaderHeight();
    new ResizeObserver(updateHeaderHeight).observe(header);
    const navigation = document.querySelector('.workspace-nav');
    const updateNavigationHeight = () => document.documentElement.style.setProperty('--admin-nav-height', `${navigation.getBoundingClientRect().height}px`);
    updateNavigationHeight();
    new ResizeObserver(updateNavigationHeight).observe(navigation);
})();
</script>
<x-required-fields />
</body>
</html>
