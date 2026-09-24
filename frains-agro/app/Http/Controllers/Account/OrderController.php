<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\AuditLog;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\PlatformNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index()
    {
        return view('account.orders.index', ['orders' => Order::with('delivery')->where('customer_id', request()->user()->customer?->id)->latest()->paginate(12)]);
    }

    public function show(Order $order)
    {
        abort_unless($order->customer_id === request()->user()->customer?->id, 403);

        return view('account.orders.show', ['order' => $order->load(['items', 'delivery.zone', 'payment', 'statusUpdates.user'])]);
    }

    public function cancel(Request $request, Order $order)
    {
        DB::transaction(function () use ($request, $order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->customer_id === $request->user()->customer?->id, 403);
            if ($order->status === 'CANCELLED') {
                return;
            }
            if (! $order->canBeCancelledByCustomer()) {
                throw ValidationException::withMessages(['cancellation' => 'Cette commande ne peut plus être annulée en ligne. Contactez FRAINS Agro.']);
            }
            foreach ($order->items()->orderBy('product_id')->get() as $item) {
                $stock = Stock::where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();
                if ($stock->reserved_quantity < $item->quantity) {
                    throw ValidationException::withMessages(['cancellation' => 'Le stock doit être vérifié par FRAINS Agro avant l’annulation.']);
                }
                $stock->reserved_quantity = round($stock->reserved_quantity - $item->quantity, 2);
                $stock->save();
                StockMovement::create(['product_id' => $item->product_id, 'user_id' => $request->user()->id, 'type' => 'RELEASE', 'quantity' => $item->quantity, 'reason' => $order->order_number]);
            }
            $order->update(['status' => 'CANCELLED', 'delivery_status' => 'CANCELLED']);
            $order->delivery()->update(['status' => 'CANCELLED']);
            $order->statusUpdates()->create(['user_id' => $request->user()->id, 'status' => 'CANCELLED', 'message' => 'Commande annulée par le client.']);
            AuditLog::record('order.cancelled_by_customer', 'order:'.$order->id);
            PlatformNotice::staff('Commande annulée par le client : '.$order->order_number, route('admin.orders.show', $order));
        });

        return to_route('account.orders.show', $order)->with('success', 'Votre commande a été annulée.');
    }
}
