<x-layouts.app title="Mon espace client">
<section class="commerce account-space">
    @php($company = $customer?->company_name ?: 'Client particulier')
    <header class="account-header">
        <p class="eyebrow">ESPACE CLIENT</p>
        <h1>{{ auth()->user()->name }}</h1>
        <p class="account-company">{{ $company }}</p>
        <p>{{ auth()->user()->phone }} &middot; {{ auth()->user()->city }}</p>
    </header>

    <nav class="account-nav" aria-label="Navigation espace client">
        @foreach(['tableau-de-bord'=>'Tableau de bord','profil'=>'Mon profil','adresses'=>'Mes adresses','paiements'=>'Mes paiements','factures'=>'Mes factures','livraisons'=>'Mes livraisons'] as $key=>$label)
            <a href="{{ route('account.section',$key) }}" @class(['active' => $section === $key])>{{ $label }}</a>
        @endforeach
        <a href="{{ route('account.orders.index') }}">Suivi commandes</a>
        <a href="{{ route('quotes.index') }}">Mes devis</a>
    </nav>

    @if($section==='profil')
        <form class="panel commerce-form" method="POST" action="{{ route('account.profile.update') }}">
            @csrf @method('PUT')
            <h2>Mes informations</h2>
            @foreach(['first_name'=>'Prenom','last_name'=>'Nom','phone'=>'Telephone','address'=>'Adresse','city'=>'Ville'] as $field=>$label)
                <label>{{ $label }}<input name="{{ $field }}" value="{{ old($field,auth()->user()->$field) }}" required></label>
            @endforeach
            <label>Entreprise<input name="company_name" value="{{ old('company_name',$customer->company_name) }}"></label>
            <button class="button">Enregistrer</button>
        </form>
    @elseif($section==='adresses')
        @foreach($addresses as $address)
            <article class="panel"><strong>{{ $address->label }}</strong><p>{{ $address->address }} &middot; {{ $address->city }}</p><form method="POST" action="{{ route('account.addresses.destroy',$address) }}">@csrf @method('DELETE')<button>Retirer du carnet</button></form></article>
        @endforeach
        <form class="panel commerce-form" method="POST" action="{{ route('account.addresses.store') }}">
            @csrf
            <h2>Ajouter une adresse</h2>
            @foreach(['label'=>'Libelle','address'=>'Adresse','city'=>'Ville'] as $name=>$label)
                <label>{{ $label }}<input name="{{ $name }}" required></label>
            @endforeach
            <label>Zone<select name="delivery_zone_id" required>@foreach($zones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach</select></label>
            <button class="button">Ajouter</button>
        </form>
    @else
        @if($section==='tableau-de-bord')
            <section class="account-summary">
                <article><span>Client</span><strong>{{ auth()->user()->name }}</strong></article>
                <article><span>Entreprise</span><strong>{{ $company }}</strong></article>
                <article><span>Telephone</span><strong>{{ auth()->user()->phone }}</strong></article>
            </section>
            <p>Retrouvez vos commandes, devis, factures, paiements et livraisons dans cet espace.</p><p><a class="button" href="{{ route('account.orders.index') }}">Suivre mes commandes</a></p>
        @endif

        @forelse($orders as $order)
            <article class="panel"><a href="{{ route('account.orders.show',$order) }}">{{ $order->order_number }}</a><p>{{ \App\Models\Order::STATUSES[$order->status] }} &middot; {{ number_format($order->total,2,',',' ') }} FCFA</p><p><a class="button" href="{{ route('account.orders.show',$order) }}">Voir le suivi</a></p>
            @if($section==='factures' && !in_array($order->status,['CANCELLED','REFUSED']))
                <a class="button" href="{{ route('invoices.show',$order) }}" target="_blank" rel="noopener">Facture PDF</a>
            @elseif($section==='paiements')
                <p>Paye {{ number_format($order->paid_amount,2,',',' ') }} FCFA &middot; Reste {{ number_format($order->balance,2,',',' ') }} FCFA</p>@foreach($order->receipts as $receipt)<p>{{ $receipt->reference }} &middot; {{ $receipt->received_at->format('d/m/Y') }} &middot; {{ $receipt->amount }} FCFA</p>@endforeach
            @elseif($section==='livraisons')
                <p>{{ $order->delivery?->delivery_number }} &middot; {{ $order->delivery?->scheduled_date?->format('d/m/Y') }} &middot; {{ $order->delivery_address }}</p><p>{{ $order->delivery?->driver_name }} &middot; {{ $order->delivery?->driver_phone }}</p>
            @endif</article>
        @empty
            <p>Aucune commande pour le moment.</p>
        @endforelse
        {{ $orders->links() }}
    @endif
</section>
</x-layouts.app>