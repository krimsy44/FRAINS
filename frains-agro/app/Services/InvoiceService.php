<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function issue(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($invoice = $order->invoice) {
                return $invoice;
            }
            $invoice = Invoice::create(['order_id' => $order->id, 'number' => DocumentNumber::next('FAC'), 'issued_at' => now(), 'snapshot' => [
                'gie' => Setting::values(),
                'customer' => ['name' => $order->customer?->name, 'company' => $order->customer?->company_name, 'email' => $order->customer?->user?->email, 'address' => $order->delivery_address],
                'order_number' => $order->order_number,
                'items' => $order->items->map(fn ($i) => $i->only(['product_name', 'quantity', 'unit', 'unit_price', 'total', 'packaging']))->all(),
                'subtotal' => $order->subtotal, 'delivery_fee' => $order->delivery_fee, 'discount' => $order->discount, 'total' => $order->total,
            ]]);
            $order->customer?->user?->notify(new PlatformNotification('Facture disponible : '.$invoice->number, route('invoices.show', $order)));

            return $invoice;
        });
    }
}
