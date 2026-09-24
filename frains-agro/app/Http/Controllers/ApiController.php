<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;

class ApiController extends Controller
{
    public function products()
    {
        $products = Product::with('stock')->where('is_active', true)->orderBy('name')->paginate(30);

        return response()->json($products->through(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'unit' => $p->unit, 'base_price' => $p->base_price, 'minimum_quantity' => $p->minimum_order_quantity, 'available_quantity' => $p->stock?->available_quantity ?? 0, 'packaging' => $p->packaging, 'production_zone' => $p->production_zone, 'wholesale' => $p->wholesale_available, 'quote_threshold' => $p->quote_threshold]));
    }

    public function orders()
    {
        return response()->json(auth()->user()->customer->orders()->latest()->paginate(20)->through(fn ($o) => ['id' => $o->id, 'number' => $o->order_number, 'status' => $o->status, 'total' => $o->total, 'paid' => $o->paid_amount, 'balance' => $o->balance]));
    }

    public function order(Order $order)
    {
        abort_unless($order->customer_id === auth()->user()->customer?->id, 403);

        return response()->json(['id' => $order->id, 'number' => $order->order_number, 'status' => $order->status, 'items' => $order->items->map(fn ($i) => $i->only(['product_name', 'quantity', 'delivered_quantity', 'unit', 'unit_price', 'total'])), 'total' => $order->total, 'paid' => $order->paid_amount, 'balance' => $order->balance, 'delivery' => ['status' => $order->delivery?->status, 'date' => $order->delivery?->scheduled_date?->toDateString()]]);
    }
}
