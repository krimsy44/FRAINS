<x-layouts.app title="Suivi commandes - FRAINS Agro">
<section class="commerce">
    <div class="section-heading">
        <div>
            <p class="eyebrow">ESPACE CLIENT</p>
            <h1>Suivi de mes commandes</h1>
        </div>
        <a class="button" href="{{ route('products.index') }}">Commander</a>
    </div>

    @forelse($orders as $order)
        <article class="panel order-card">
            <div>
                <span class="status-pill">{{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}</span>
                <h2>{{ $order->order_number }}</h2>
                <p>{{ $order->ordered_at?->format('d/m/Y H:i') ?? 'Date a confirmer' }} &middot; {{ number_format($order->total, 0, ',', ' ') }} FCFA</p>
                <p>Livraison : {{ $order->delivery?->scheduled_date?->format('d/m/Y') ?? 'A confirmer' }}</p>
            </div>
            <a class="button" href="{{ route('account.orders.show', $order) }}">Voir le suivi</a>
        </article>
    @empty
        <p>Aucune commande pour le moment.</p>
        <a class="button" href="{{ route('products.index') }}">Decouvrir les produits</a>
    @endforelse
    {{ $orders->links() }}
</section>
</x-layouts.app>