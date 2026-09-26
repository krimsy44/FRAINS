<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Notifications\PlatformNotification;
use App\Services\DocumentNumber;
use App\Services\InvoiceService;
use App\Services\PlatformNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function create()
    {
        if (! session()->has('checkout_token')) {
            session(['checkout_token' => (string) Str::uuid()]);
        }
        $cart = collect(session('cart', []))->map(function ($line) {
            $product = Product::where('is_active', true)->findOrFail($line['id']);
            $line['unit_price'] = $product->priceFor((float) $line['quantity']);
            $line['total'] = round($line['unit_price'] * $line['quantity'], 2);

            $line['packaging'] = $product->packaging;

            return $line;
        });
        if ($cart->isEmpty()) {
            return to_route('products.index')->with('error', 'Votre panier est vide.');
        }

        return view('checkout.create', ['cart' => $cart, 'subtotal' => $cart->sum('total'), 'zones' => DeliveryZone::where('is_active', true)->orderBy('name')->get(), 'customer' => request()->user()->customer]);
    }

    public function confirmation()
    {
        abort_unless(session('guest_order_id'), 404);
        return view('checkout.confirmation', ['order' => Order::with(['items', 'customer', 'delivery.zone'])->findOrFail(session('guest_order_id'))]);
    }

    public function store(Request $request)
    {
        $publicCheckout = $request->routeIs('guest.checkout');
        $customer = $request->user()?->customer;
        $guest = ! $request->user();
        abort_unless($guest || ($request->user()->role?->name === 'CUSTOMER' && $request->user()->status === 'active' && $customer?->status === 'active'), 403);
        $cart = session('cart', []);
        if (empty($cart)) {
            return to_route('products.index')->with('error', 'Votre panier est vide.');
        }
        if ($publicCheckout) {
            $request->validate(['first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'phone' => ['required', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{7,30}$/'], 'delivery_zone_id' => 'required|exists:delivery_zones,id']);
            $request->merge(['delivery_address' => 'Zone : '.DeliveryZone::findOrFail($request->input('delivery_zone_id'))->name.' — adresse à confirmer par téléphone', 'payment_method' => 'CASH', 'scheduled_date' => today()->toDateString()]);
        }
        $data = $request->validate(['delivery_zone_id' => ['required', 'exists:delivery_zones,id'], 'delivery_address' => ['required', 'string', 'max:500'], 'notes' => ['nullable', 'string', 'max:1000'], 'payment_method' => ['required', 'in:CASH,BANK_TRANSFER,MOBILE_MONEY']]);
        abort_unless($guest || $customer, 403);

        $request->validate(['scheduled_date' => ['required', 'date', 'after_or_equal:today'], 'checkout_token' => ['required', 'string']]);
        if (! hash_equals((string) session('checkout_token'), $request->string('checkout_token')->toString())) {
            return to_route($guest ? 'cart.index' : 'account.orders.index')->with('error', 'Cette validation a expiré. Consultez vos commandes avant de réessayer.');
        }
        $order = DB::transaction(function () use ($cart, $data, $customer, $request, $guest, $publicCheckout) {
            // Serialize submissions for this customer, including double clicks.
            if ($customer) {
                Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            }
            $sequence = strtoupper(substr(hash('sha256', ($customer?->id ?? 'guest').':'.$request->input('checkout_token')), 0, 24));
            if ($existing = Order::where('checkout_key', $sequence)->first()) {
                return $existing;
            }
            $zone = DeliveryZone::lockForUpdate()->findOrFail($data['delivery_zone_id']);
            if (! $zone->is_active) {
                throw ValidationException::withMessages(['delivery_zone_id' => 'Cette zone est indisponible.']);
            }
            if ($guest) {
                if ($existing = Order::where('checkout_key', $sequence)->first()) {
                    return $existing;
                }
                $guestName = Str::of($request->input('first_name').' '.$request->input('last_name'))->squish()->lower()->toString();
                $guestPhone = preg_replace('/\D+/', '', $request->input('phone'));
                $customer = Customer::with('user')->where('status', 'active')->whereNull('account_deleted_at')->orderByRaw('user_id IS NULL')->get()->first(function (Customer $candidate) use ($guestName, $guestPhone) {
                    $candidateNames = array_filter([$candidate->name, trim(($candidate->last_name ?? '').' '.($candidate->first_name ?? ''))]);
                    $nameMatches = collect($candidateNames)->contains(fn ($name) => Str::of($name)->squish()->lower()->toString() === $guestName);
                    $phoneMatches = preg_replace('/\D+/', '', $candidate->contact_phone ?? '') === $guestPhone;

                    return $nameMatches && $phoneMatches;
                });
                if (! $customer) {
                    $customer = Customer::create($request->only(['first_name', 'last_name', 'phone']) + ['delivery_zone_id' => $zone->id, 'customer_type' => 'PARTICULIER', 'status' => 'active']);
                }
            }
            $lines = [];
            $subtotal = 0;
            ksort($cart);
            foreach ($cart as $line) {
                $product = Product::with('stock')->lockForUpdate()->findOrFail($line['id']);
                $stock = $product->stock()->lockForUpdate()->firstOrFail();
                if (! $product->is_active || $line['quantity'] < $product->minimum_order_quantity) {
                    throw ValidationException::withMessages(['cart' => 'Un produit est indisponible ou sa quantité minimale a changé.']);
                }
                if ($product->quote_threshold && $line['quantity'] >= $product->quote_threshold) {
                    throw ValidationException::withMessages(['cart' => 'Cette quantité de '.$product->name.' nécessite un devis.']);
                }
                if ($stock->available_quantity < $line['quantity']) {
                    throw ValidationException::withMessages(['cart' => "Le stock de {$product->name} a changé. Veuillez adapter votre panier."]);
                }
                $price = $product->priceFor((float) $line['quantity']);
                $lines[] = compact('product', 'stock', 'price') + ['quantity' => (float) $line['quantity']];
                $subtotal += round($price * $line['quantity'], 2);
            }
            $order = Order::create(['order_number' => DocumentNumber::next('CMD'), 'checkout_key' => $sequence, 'customer_id' => $customer->id, 'delivery_zone_id' => $zone->id, 'status' => 'NEW', 'payment_status' => 'PENDING', 'delivery_status' => 'PENDING', 'subtotal' => round($subtotal, 2), 'delivery_fee' => $zone->base_fee, 'total' => round($subtotal + $zone->base_fee, 2), 'delivery_address' => $data['delivery_address'], 'notes' => $data['notes'] ?? null, 'ordered_at' => now()]);
            foreach ($lines as $line) {
                $order->items()->create(['product_id' => $line['product']->id, 'product_name' => $line['product']->name, 'quantity' => $line['quantity'], 'unit' => $line['product']->unit, 'unit_price' => $line['price'], 'packaging' => $line['product']->packaging, 'total' => round($line['price'] * $line['quantity'], 2)]);
                $line['stock']->increment('reserved_quantity', $line['quantity']);
                StockMovement::create(['product_id' => $line['product']->id, 'user_id' => $request->user()?->id, 'type' => 'RESERVATION', 'quantity' => $line['quantity'], 'reason' => "Réservation {$order->order_number}"]);
            }
            Delivery::create(['order_id' => $order->id, 'delivery_zone_id' => $zone->id, 'delivery_number' => DocumentNumber::next('LIV'), 'delivery_address' => $data['delivery_address'], 'customer_phone' => $customer->contact_phone]);
            Payment::create(['order_id' => $order->id, 'customer_id' => $customer->id, 'reference' => 'PAY-'.now()->format('Ymd').'-'.$sequence, 'amount' => $order->total, 'method' => $data['payment_method']]);
            $order->delivery->update(['scheduled_date' => $publicCheckout ? null : $request->input('scheduled_date')]);
            $order->statusUpdates()->create(['status' => 'NEW', 'message' => 'Votre commande a été reçue. Nous vous contacterons pour confirmer la livraison.', 'user_id' => $request->user()?->id]);

            app(InvoiceService::class)->issue($order);
            AuditLog::record('order.created', 'order:'.$order->id);
            PlatformNotice::staff('Nouvelle commande : '.$order->order_number, route('admin.orders.show', $order));
            $customer->user?->notify(new PlatformNotification('Commande reçue : '.$order->order_number, route('account.orders.show', $order)));

            return $order;
        });
        session()->forget(['cart', 'checkout_token']);

        if ($guest) {
            session(['guest_order_id' => $order->id]);
            return to_route('guest.confirmation');
        }
        return to_route('account.orders.show', $order)->with('success', 'Votre commande '.$order->order_number.' a été enregistrée.');
    }
}
