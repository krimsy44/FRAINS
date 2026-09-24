<x-layouts.app title="Créer mon compte — FRAINS Agro">
<section class="commerce"><p class="eyebrow">ESPACE CLIENT</p><h1>Créer mon compte</h1>
<form class="panel commerce-form" method="POST" action="{{ route('register.store') }}">@csrf
<div class="form-grid">
@foreach(['first_name' => 'Prénom', 'last_name' => 'Nom', 'email' => 'E-mail', 'phone' => 'Téléphone', 'address' => 'Adresse', 'city' => 'Ville'] as $field => $label)
<label>{{ $label }}<input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field) }}" required maxlength="255"></label>
@endforeach
<label>Type de client<select name="customer_type" required>@foreach(['PARTICULIER'=>'Particulier','REVENDEUR'=>'Revendeur','GROSSISTE'=>'Grossiste','RESTAURANT'=>'Restaurant','HOTEL'=>'Hôtel','SUPERMARCHE'=>'Supermarché','ENTREPRISE'=>'Entreprise','DISTRIBUTEUR'=>'Distributeur'] as $value=>$label)<option value="{{ $value }}" @selected(old('customer_type') === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Entreprise (facultatif)<input name="company_name" value="{{ old('company_name') }}" maxlength="120"></label>
<label>Zone de livraison<select name="delivery_zone_id" required @disabled($zones->isEmpty())><option value="">Choisir une zone</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected(old('delivery_zone_id') == $zone->id)>{{ $zone->name }}</option>@endforeach</select></label>
@if($zones->isEmpty())<p role="alert">Aucune zone de livraison n’est disponible pour le moment. Veuillez <a href="{{ route('public.page', 'contact') }}">contacter FRAINS Agro</a> avant de créer votre compte.</p>@endif
<label>Mot de passe<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
<label>Confirmer le mot de passe<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
</div><button class="button" @disabled($zones->isEmpty())>Créer mon compte</button><p>Déjà client ? <a href="{{ route('login') }}">Se connecter</a></p>
</form></section></x-layouts.app>
