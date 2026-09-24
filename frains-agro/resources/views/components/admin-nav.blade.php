@php($role=auth()->user()->role?->name)
<nav class="workspace-nav" aria-label="Navigation de gestion">
<a href="{{ route('admin.dashboard') }}">Tableau de bord</a>
@if(in_array($role,['ADMIN','MANAGER','COMMERCIAL']))<a href="{{ route('admin.finance','factures') }}">Factures</a><a href="{{ route('admin.finance','paiements') }}">Paiements</a>@endif
@if(in_array($role,['ADMIN','MANAGER','COMMERCIAL']))<a href="{{ route('admin.products.index') }}">Produits</a><a href="{{ route('admin.orders.index') }}">Commandes</a><a href="{{ route('admin.quotes.index') }}">Devis</a><a href="{{ route('admin.clients.index') }}">Clients</a>@endif
@if(in_array($role,['ADMIN','MANAGER','STOCK_MANAGER']))<a href="{{ route('admin.stocks.index') }}">Stocks</a>@endif
@if(in_array($role,['ADMIN','MANAGER','DELIVERY_MANAGER','DRIVER']))<a href="{{ route('admin.deliveries.index') }}">Livraisons</a>@endif
@if(in_array($role,['ADMIN','MANAGER','PRODUCER']))<a href="{{ route('admin.agriculture.index') }}">Productions et récoltes</a>@endif
@foreach(config('frains.resources') as $key=>$definition)@if(in_array($role,$definition['roles']))<a href="{{ route('admin.resources.index',$key) }}">{{ $definition['title'] }}</a>@endif
@endforeach
@if(in_array($role,['ADMIN','MANAGER','COMMERCIAL','STOCK_MANAGER','DELIVERY_MANAGER']))<a href="{{ route('admin.reports') }}">Rapports et alertes</a>@endif
@if($role==='ADMIN')<a href="{{ route('admin.users.index') }}">Utilisateurs</a><a href="{{ route('admin.settings.index') }}">Paramètres du GIE</a>@endif
<a href="{{ route('admin.notifications.index') }}">Notifications</a>
</nav>
