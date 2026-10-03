<x-layouts.admin title="Utilisateurs"><section class="commerce"><h1>Utilisateurs et permissions</h1><p>Modifiez les informations du compte souhaité, puis cliquez sur « Enregistrer les modifications ».</p><form class="panel commerce-form" method="GET" action="{{ route('admin.users.index') }}" role="search">
<h2>Rechercher un utilisateur</h2>
<label for="user-search">Nom, prénom, e-mail ou téléphone</label>
<input id="user-search" type="search" name="q" value="{{ $search }}" maxlength="255" placeholder="Ex. : Awa Diop, awa@exemple.sn ou 771234567">
<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><button class="button" type="submit">Rechercher</button>@if($search !== '')<a href="{{ route('admin.users.index') }}">Réinitialiser</a>@endif</div>
</form>
<p>{{ $users->total() }} utilisateur(s){{ $search !== '' ? ' trouvé(s)' : '' }}.</p>
@if($users->isEmpty())<p>Aucun utilisateur trouvé. Modifiez votre recherche.</p>@endif
@foreach($users as $user)<h2>Modifier les informations de {{ $user->name }}</h2><form class="panel commerce-form" method="POST" action="{{ route('admin.users.update',$user) }}">@csrf @method('PUT')<input type="hidden" name="editing_user_id" value="{{ $user->id }}">
@foreach(['first_name'=>'Prénom','last_name'=>'Nom','email'=>'E-mail','phone'=>'Téléphone','address'=>'Adresse','city'=>'Ville'] as $field=>$label)
<label>{{ $label }}<input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" value="{{ (string) old('editing_user_id') === (string) $user->id ? old($field, $user->$field) : $user->$field }}" @required(in_array($field, ['first_name','last_name','email']))></label>
@endforeach
<label>Rôle<select name="role_id" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string) $role->id === (string) ((string) old('editing_user_id') === (string) $user->id ? old('role_id', $user->role_id) : $user->role_id))>{{ $role->label }}</option>@endforeach</select></label><label>Statut<select name="status" required><option value="active" @selected(((string) old('editing_user_id') === (string) $user->id ? old('status', $user->status) : $user->status) === 'active')>Actif</option><option value="inactive" @selected(((string) old('editing_user_id') === (string) $user->id ? old('status', $user->status) : $user->status) === 'inactive')>Inactif</option></select></label><button class="button">Enregistrer les modifications</button></form>
@if($user->id !== auth()->id())
<form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Supprimer cet utilisateur ? Son accès sera retiré.');">
@csrf @method('DELETE')
<button class="button" type="submit">Supprimer {{ $user->name }}</button>
</form>
@endif
@endforeach{{ $users->links() }}
<h2>Créer un compte interne</h2><form class="panel commerce-form" method="POST" action="{{ route('admin.users.store') }}">@csrf @foreach(['first_name'=>'Prénom','last_name'=>'Nom','email'=>'E-mail','phone'=>'Téléphone'] as $name=>$label)<label>{{ $label }}<input name="{{ $name }}" value="{{ old($name) }}" @required($name !== 'phone')></label>@endforeach<label>Rôle<select name="role_id" required>@foreach($roles->where('name','!=','CUSTOMER') as $role)<option value="{{ $role->id }}">{{ $role->label }}</option>@endforeach</select></label><label>Mot de passe (12 caractères minimum)<input type="password" name="password" required minlength="12"></label><label>Confirmation<input type="password" name="password_confirmation" required></label><button class="button">Créer le compte</button></form></section></x-layouts.admin>
