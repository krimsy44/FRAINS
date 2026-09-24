<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion administration — FRAINS Agro</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/commerce.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-area">
<header class="admin-header">
    <a class="brand" href="{{ route('home') }}"><span>F</span> FRAINS <em>Agro</em></a>
    <strong class="admin-label">Administration</strong>
    <nav aria-label="Retour au site"><a href="{{ route('home') }}">Accueil du site</a></nav>
</header>
<main>
    <section class="login">
        <div><p class="eyebrow">ESPACE DE GESTION SÉCURISÉ</p><h1>Connexion administration</h1><p>Accès réservé aux membres autorisés de l’équipe FRAINS Agro.</p><p>Vous êtes client ? <a href="{{ route('login') }}">Connexion à l’espace client</a></p></div>
        <form method="POST" action="{{ route('admin.login.store') }}" class="panel login-form">
            @csrf
            @if($errors->any())<div class="validation-errors" role="alert">{{ $errors->first() }}</div>@endif
            <label>Adresse e-mail professionnelle<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
            <label>Mot de passe<input type="password" name="password" autocomplete="current-password" required></label>
            <label class="remember"><input type="checkbox" name="remember" value="1"> Rester connecté</label>
            <button class="button">Se connecter</button>
        </form>
    </section>
</main>
<x-required-fields />
</body>
</html>
