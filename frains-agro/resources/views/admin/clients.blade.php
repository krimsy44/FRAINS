<x-layouts.admin title="Clients">
<section class="commerce">
    <div class="panel-title">
        <div>
            <p class="eyebrow">CLIENTS</p>
            <h1>Comptes clients</h1>
        </div>
        <a class="button" href="#nouveau-client">Nouveau client</a>
    </div>

    <form id="nouveau-client" class="panel commerce-form" method="POST" action="{{ route('admin.clients.store') }}">
        @csrf
        <h2>Nouveau compte client</h2>
        <p>Le client se connectera avec son nom complet et son numero de telephone.</p>
        <div class="form-grid">
            <label>Prenom<input name="first_name" value="{{ old('first_name') }}" required maxlength="80"></label>
            <label>Nom<input name="last_name" value="{{ old('last_name') }}" required maxlength="80"></label>
            <label>Telephone<input name="phone" value="{{ old('phone') }}" required maxlength="30"></label>
            <label>Ville<input name="city" value="{{ old('city') }}" required maxlength="80"></label>
            <label class="full">Adresse<input name="address" value="{{ old('address') }}" required maxlength="255"></label>
            <label>Type de client<select name="customer_type" required>@foreach(['PARTICULIER'=>'Particulier','PROFESSIONNEL'=>'Professionnel','REVENDEUR'=>'Revendeur','GROSSISTE'=>'Grossiste','RESTAURANT'=>'Restaurant','HOTEL'=>'Hotel','SUPERMARCHE'=>'Supermarche','ENTREPRISE'=>'Entreprise','DISTRIBUTEUR'=>'Distributeur'] as $value=>$label)<option value="{{ $value }}" @selected(old('customer_type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Entreprise<input name="company_name" value="{{ old('company_name') }}" maxlength="120"></label>
            <label class="full">Zone de livraison<select name="delivery_zone_id" required @disabled($zones->isEmpty())><option value="">Choisir une zone</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected(old('delivery_zone_id') == $zone->id)>{{ $zone->name }}</option>@endforeach</select></label>
        </div>
        @if($zones->isEmpty())<p class="flash error">Aucune zone de livraison active. Activez une zone avant de creer un client.</p>@endif
        <button class="button" @disabled($zones->isEmpty())>Creer le compte client</button>
    </form>

    <h2>Clients existants</h2>
    @php($canDelete = in_array(auth()->user()->role?->name, ['ADMIN', 'MANAGER'], true))
    @if($canDelete && $clients->isNotEmpty())
        <form id="delete-clients" method="POST" action="{{ route('admin.clients.destroyMany') }}" onsubmit="return confirm('Supprimer les comptes sélectionnés ? Leur accès sera retiré. Les commandes et paiements seront conservés.');">
            @csrf @method('DELETE')
            <p><label><input type="checkbox" id="select-all-clients"> Tout sélectionner sur cette page</label></p>
            <button class="button" id="delete-selected-clients" disabled>Supprimer la sélection</button>
        </form>
    @endif
    @forelse($clients as $client)
        <article class="panel">
            @if($canDelete)<label><input type="checkbox" name="clients[]" value="{{ $client->id }}" form="delete-clients" class="client-selection" aria-label="Sélectionner {{ $client->name }}"> Sélectionner</label>@endif
            <p><a href="{{ route('admin.clients.show',$client) }}">{{ $client->name }} · {{ $client->company_name }}</a></p>
            <p>{{ $client->customer_type }} · {{ $client->orders_count }} commandes · {{ $client->status }}</p>
            @if($canDelete)
                <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" onsubmit="return confirm('Supprimer ce compte client ? Son accès sera retiré. Ses commandes et paiements seront conservés.');">
                    @csrf @method('DELETE')
                    <button class="button">Supprimer le compte</button>
                </form>
            @endif
        </article>
    @empty
        <p>Aucun client enregistre.</p>
    @endforelse
    {{ $clients->links() }}
    @if($canDelete && $clients->isNotEmpty())
        <script>
        (() => {
            const all = document.getElementById('select-all-clients');
            const boxes = [...document.querySelectorAll('.client-selection')];
            const button = document.getElementById('delete-selected-clients');
            const refresh = () => {
                const count = boxes.filter(box => box.checked).length;
                button.disabled = count === 0;
                button.textContent = count ? `Supprimer la sélection (${count})` : 'Supprimer la sélection';
                all.checked = count === boxes.length;
                all.indeterminate = count > 0 && count < boxes.length;
            };
            all.addEventListener('change', () => { boxes.forEach(box => box.checked = all.checked); refresh(); });
            boxes.forEach(box => box.addEventListener('change', refresh));
            refresh();
        })();
        </script>
    @endif
</section>
</x-layouts.admin>
