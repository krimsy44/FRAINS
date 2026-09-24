<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderStatusUpdate;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Notifications\PlatformNotification;
use App\Services\PaymentService;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index()
    {
        return view('admin.orders.index', ['orders' => Order::with('customer.user')->latest()->paginate(20)]);
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', ['order' => $order->load(['items', 'customer.user', 'delivery.zone', 'payment', 'statusUpdates.user'])]);
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'mark_paid' => ['nullable', 'boolean'],
            'driver_name' => ['nullable', 'string', 'max:120'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'scheduled_date' => ['nullable', 'date'],
            'tracking_message' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($data, $order, $request) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $fail = fn ($message) => throw ValidationException::withMessages(['status' => $message]);
            $changed = $data['status'] !== $order->status;
            if ($changed && ! in_array($data['status'], $order->nextStatuses(), true)) {
                $fail('Cette transition de commande est impossible.');
            }
            if (in_array($order->status, ['CANCELLED', 'REFUSED', 'COMPLETED'], true)) {
                $fail('Cette commande est clôturée.');
            }
            $delivery = $order->delivery;
            if ($data['status'] === 'SHIPPING' && (! ($data['driver_name'] ?? $delivery->driver_name) || ! ($data['driver_phone'] ?? $delivery->driver_phone))) {
                $fail('Indiquez le nom et le téléphone du livreur avant l’expédition.');
            }
            if ($request->boolean('mark_paid') && $order->payment_status !== 'PAID') {
                if (in_array($data['status'], ['CANCELLED', 'REFUSED'], true)) {
                    $fail('Impossible d’encaisser une commande annulée ou refusée.');
                }
                app(PaymentService::class)->receive($order, ['amount' => $order->balance, 'method' => $order->payment?->method ?? 'CASH']);
                $order->refresh();
            }
            if ($data['status'] === 'COMPLETED' && $order->payment_status !== 'PAID') {
                $fail('Enregistrez le règlement avant de terminer la commande.');
            }
            if ($changed && in_array($data['status'], ['CANCELLED', 'REFUSED'], true) && $order->paid_amount > 0) {
                $fail('Cette commande est payée : un remboursement doit être traité avant toute annulation.');
            }
            if ($changed && in_array($data['status'], ['CANCELLED', 'REFUSED', 'SHIPPING'], true)) {
                foreach ($order->items()->orderBy('product_id')->get() as $item) {
                    $stock = Stock::where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();
                    if ($stock->reserved_quantity < $item->quantity || ($data['status'] === 'SHIPPING' && $stock->quantity < $item->quantity)) {
                        $fail('Le stock doit être régularisé avant cette opération.');
                    }
                    $stock->reserved_quantity -= $item->quantity;
                    if ($data['status'] === 'SHIPPING') {
                        $movement = StockMovement::create(['product_id' => $item->product_id, 'user_id' => auth()->id(), 'type' => 'SALE', 'quantity' => $item->quantity, 'reason' => $order->order_number]);
                        app(StockLedger::class)->consume($stock, $movement);
                        $stock->quantity -= $item->quantity;
                    }
                    $stock->save();
                    if ($data['status'] !== 'SHIPPING') {
                        StockMovement::create(['product_id' => $item->product_id, 'user_id' => $request->user()->id, 'type' => 'RELEASE', 'quantity' => $item->quantity, 'reason' => $order->order_number]);
                    }
                }
            }
            $deliveryStatus = match ($data['status']) {
                'SHIPPING' => 'SHIPPING', 'PARTIALLY_DELIVERED' => 'PARTIALLY_DELIVERED', 'DELIVERED', 'COMPLETED' => 'DELIVERED', 'CANCELLED', 'REFUSED' => 'CANCELLED', default => 'PENDING'
            };
            $delivery->fill(collect($data)->only(['driver_name', 'driver_phone', 'scheduled_date'])->all());
            if ($delivery->isDirty('driver_name')) {
                $delivery->assigned_at = now();
            }
            $delivery->status = $deliveryStatus;
            if ($changed && $data['status'] === 'DELIVERED') {
                $delivery->delivered_at = now();
                foreach ($order->items as $item) {
                    $item->update(['delivered_quantity' => $item->quantity]);
                }
            }
            $delivery->save();
            $order->status = $data['status'];
            $order->delivery_status = $deliveryStatus;
            $order->save();
            if ($changed || filled($data['tracking_message'] ?? null)) {
                OrderStatusUpdate::create(['order_id' => $order->id, 'user_id' => auth()->id(), 'status' => $order->status, 'message' => $data['tracking_message'] ?? null]);
            }
            AuditLog::record('order.updated', 'order:'.$order->id, ['status' => $order->status, 'tracking_message' => $data['tracking_message'] ?? null]);
            if ($changed) {
                $order->customer?->user?->notify(new PlatformNotification('Commande '.$order->order_number.' : '.Order::STATUSES[$order->status], route('account.orders.show', $order)));
            }
        });

        return back()->with('success', 'Commande mise à jour.');
    }
}
