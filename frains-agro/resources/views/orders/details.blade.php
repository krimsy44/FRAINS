@php
    $steps = [
        'NEW' => ['label' => 'Commande recue', 'description' => 'La commande est enregistree.'],
        'CONFIRMED' => ['label' => 'Commande confirmee', 'description' => 'La commande a ete validee.'],
        'PREPARING' => ['label' => 'En preparation', 'description' => 'Les produits sont en cours de preparation.'],
        'READY' => ['label' => 'Prete a livrer', 'description' => 'La commande est prete pour la livraison.'],
        'SHIPPING' => ['label' => 'En livraison', 'description' => 'La commande est en route.'],
        'DELIVERED' => ['label' => 'Livree', 'description' => 'La livraison est terminee.'],
    ];
    $statusOrder = array_keys($steps);
    $currentStatus = $order->status === 'COMPLETED' ? 'DELIVERED' : $order->status;
    $currentIndex = array_search($currentStatus, $statusOrder, true);
    $currentIndex = $currentIndex === false ? -1 : $currentIndex;
    $cancelled = in_array($order->status, ['CANCELLED', 'REFUSED'], true);
    $trackingUpdates = $order->statusUpdates->sortByDesc('created_at')->groupBy('status');
    $deliveryLabel = $cancelled ? 'Annulée' : ($order->delivery?->status === 'DELIVERED' ? 'Livree' : ($order->delivery?->status === 'SHIPPING' ? 'En route' : 'A confirmer'));
    $paymentLabel = $order->payment_status === 'PAID' ? 'Paye' : ($order->payment_status === 'PARTIAL' ? 'Partiellement paye' : 'En attente');
@endphp

<section class="tracking-page">
    @if(request()->routeIs('account.orders.show'))
        @if($order->canBeCancelledByCustomer())
            <form class="panel" method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm('Confirmer l’annulation de votre commande ?');">
                @csrf
                <p>Vous pouvez annuler votre commande avant son expédition si aucun paiement n’a été encaissé.</p>
                <button class="button" type="submit">Annuler ma commande</button>
            </form>
        @elseif(!in_array($order->status, ['CANCELLED', 'REFUSED', 'COMPLETED', 'DELIVERED'], true))
            <p>L’annulation en ligne n’est plus disponible. <a href="{{ route('public.page', 'contact') }}">Contactez FRAINS Agro</a> pour toute demande.</p>
        @endif
    @endif
    <div class="tracking-title">
        <a href="{{ route('account.orders.index') }}">Retour aux commandes</a>
        <h2>Suivi de la commande</h2>
        <p>{{ $order->order_number }} - {{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}</p>
    </div>

    <div class="tracking-summary-simple">
        <p><span>Livraison :</span> <strong>{{ $deliveryLabel }}</strong></p>
        <p><span>Date prevue :</span> <strong>{{ $order->delivery?->scheduled_date?->format('d/m/Y') ?? 'A confirmer' }}</strong></p>
        <p><span>Paiement :</span> <strong>{{ $paymentLabel }}</strong></p>
    </div>

    @if($cancelled)
        <div class="tracking-cancelled">Cette commande est {{ strtolower(\App\Models\Order::STATUSES[$order->status] ?? $order->status) }}.</div>
        @if($lastUpdate = $trackingUpdates->get($order->status)?->first())
            <p>{{ $lastUpdate->message }} <small>{{ $lastUpdate->created_at->format('d/m/Y H:i') }}</small></p>
        @endif
    @else
        <div class="tracking-steps-simple">
            @foreach($steps as $key => $step)
                @php
                    $index = array_search($key, $statusOrder, true);
                    $update = $trackingUpdates->get($key)?->first();
                    $state = $index < $currentIndex ? 'Terminee' : ($index === $currentIndex ? 'En cours' : 'A venir');
                @endphp
                <div @class(['step-item', 'done' => $index < $currentIndex, 'current' => $index === $currentIndex, 'upcoming' => $index > $currentIndex])>
                    <div class="step-number">{{ $loop->iteration }}</div>
                    <div class="step-text">
                        <div class="step-line"><strong>{{ $step['label'] }}</strong><span>&nbsp;&nbsp; Statut : {{ $state }}</span></div>
                        <p>{{ $step['description'] }}</p>
                        @if($update)
                            <div class="step-message">
                                <b>Message FRAINS Agro</b>
                                <p>{{ $update->message ?: 'Etape mise a jour.' }}</p>
                                <small>{{ $update->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="tracking-info-simple">
        <h3>Informations de livraison</h3>
        <p><strong>Adresse :</strong> {{ $order->delivery?->zone?->name }} - {{ $order->delivery_address }}</p>
        @if($order->delivery?->driver_name)<p><strong>Livreur :</strong> {{ $order->delivery->driver_name }} - {{ $order->delivery->driver_phone }}</p>@endif
        @if($order->delivery?->delivered_at)<p><strong>Reception :</strong> Livree le {{ $order->delivery->delivered_at->format('d/m/Y H:i') }}</p>@endif
    </div>
</section>

<section class="panel order-detail-card">
    <div class="section-heading"><h2>Produits commandes</h2><strong>{{ number_format($order->total, 0, ',', ' ') }} FCFA</strong></div>
    <div class="table-scroll"><table><thead><tr><th>Produit</th><th>Quantite</th><th>Prix unitaire</th><th>Montant</th><th>Livre</th></tr></thead><tbody>
    @foreach($order->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->quantity }} {{ strtolower($item->unit) }}</td><td>{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td><td>{{ number_format($item->total, 0, ',', ' ') }} FCFA</td><td>{{ $item->delivered_quantity }} / {{ $item->quantity }} {{ strtolower($item->unit) }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="order-money"><span>Produits : {{ number_format($order->subtotal, 0, ',', ' ') }} FCFA</span><span>Livraison : {{ number_format($order->delivery_fee, 0, ',', ' ') }} FCFA</span><span>Reste a payer : {{ number_format($order->balance,2,',',' ') }} FCFA</span></div>
    @if(!in_array($order->status,['CANCELLED','REFUSED']))<p><a class="button" href="{{ route((request()->is('admin', 'admin/*') ? 'admin.' : '').'invoices.show',$order) }}" target="_blank" rel="noopener">Consulter la facture PDF</a></p>@endif
    @if($order->payment_due_date)<p>Echeance : {{ $order->payment_due_date->format('d/m/Y') }}</p>@endif
    @foreach($order->receipts as $receipt)<p>Reglement {{ $receipt->reference }} : {{ number_format($receipt->amount,2,',',' ') }} FCFA - {{ $receipt->received_at->format('d/m/Y') }}</p>@endforeach
    @if($order->notes)<p>Instructions : {{ $order->notes }}</p>@endif
</section>
