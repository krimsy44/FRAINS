<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\DocumentNumber;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    private function manager(): bool
    {
        return in_array(auth()->user()->role?->name, ['ADMIN', 'MANAGER', 'COMMERCIAL'], true);
    }

    private function authorizeQuote(Quote $quote): void
    {
        abort_unless($this->manager() || $quote->customer_id === auth()->user()->customer?->id, 403);
    }


    public function start(Request $request)
    {
        if (! auth()->check()) {
            return view('quotes.public-contact', ['settings' => Setting::values()]);
        }

        if (auth()->user()->role?->name !== 'CUSTOMER') {
            return to_route('home')->with('error', 'Connectez-vous avec un compte client pour demander un devis.');
        }

        return redirect()->route('quotes.create', $request->only('product'));
    }
    public function index()
    {
        return view('quotes.index', ['quotes' => Quote::with('customer.user')->when(! $this->manager(), fn ($q) => $q->where('customer_id', auth()->user()->customer->id))->latest()->paginate(20)]);
    }

    public function create()
    {
        return view('quotes.create', [
            'products' => Product::where('is_active', true)->where('wholesale_available', true)->get(),
            'zones' => DeliveryZone::where('is_active', true)->get(),
            'settings' => Setting::values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['delivery_zone_id' => 'required|exists:delivery_zones,id', 'delivery_address' => 'required|string|max:500', 'requested_date' => 'required|date|after_or_equal:today', 'notes' => 'nullable|string|max:3000', 'items' => 'required|array|min:1|max:20', 'items.*.product_id' => 'nullable|exists:products,id', 'items.*.quantity' => 'nullable|numeric|min:0.01|max:999999999|decimal:0,2', 'items.*.packaging' => 'nullable|string|max:120']);
        $lines = collect($data['items'])->filter(fn ($i) => ! empty($i['product_id']));
        if ($lines->isEmpty() || $lines->contains(fn ($i) => empty($i['quantity']))) {
            throw ValidationException::withMessages(['items' => 'Choisissez au moins un produit et sa quantitÃƒÂ©.']);
        }
        $quote = DB::transaction(function () use ($data, $lines) {
            $zone = DeliveryZone::whereKey($data['delivery_zone_id'])->where('is_active', true)->firstOrFail();
            $quote = Quote::create(collect($data)->except('items')->all() + ['customer_id' => auth()->user()->customer->id, 'number' => DocumentNumber::next('DEV'), 'status' => 'REQUESTED']);
            foreach ($lines as $line) {
                $product = Product::whereKey($line['product_id'])->where('is_active', true)->where('wholesale_available', true)->firstOrFail();
                if ($line['quantity'] < $product->minimum_order_quantity) {
                    throw ValidationException::withMessages(['items' => 'QuantitÃƒÂ© minimale non respectÃƒÂ©e pour '.$product->name]);
                }
                $quote->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit' => $product->unit, 'quantity' => $line['quantity'], 'packaging' => $line['packaging'] ?? $product->packaging]);
            }
            AuditLog::record('quote.requested', 'quote:'.$quote->id);
            User::where('status', 'active')->whereHas('role', fn ($q) => $q->whereIn('name', ['ADMIN', 'MANAGER', 'COMMERCIAL']))->each(fn ($u) => $u->notify(new PlatformNotification('Nouvelle demande '.$quote->number, route('admin.quotes.show', $quote))));

            return $quote;
        });

        return to_route('quotes.show', $quote)->with('success', 'Demande de devis enregistrée.');
    }

    public function show(Quote $quote)
    {
        $this->authorizeQuote($quote);

        return view('quotes.show', ['quote' => $quote->load(['items', 'customer.user', 'zone']), 'manager' => $this->manager()]);
    }

    public function offer(Request $request, Quote $quote)
    {
        abort_unless($this->manager(), 403);
        $data = $request->validate(['status' => 'required|in:REVIEWING,OFFERED,REJECTED', 'delivery_fee' => 'required_if:status,OFFERED|nullable|numeric|min:0|max:99999999|decimal:0,2', 'valid_until' => 'required_if:status,OFFERED|nullable|date|after_or_equal:today', 'proposal_notes' => 'nullable|string|max:3000', 'prices' => 'nullable|array', 'prices.*' => 'nullable|numeric|min:0|max:99999999|decimal:0,2']);
        DB::transaction(function () use ($quote, $data) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            if (! in_array($quote->status, ['REQUESTED', 'REVIEWING', 'OFFERED'])) {
                throw ValidationException::withMessages(['status' => 'Ce devis est clÃƒÂ´turÃƒÂ©.']);
            }
            if ($data['status'] === 'OFFERED') {
                foreach ($quote->items as $item) {
                    if (! isset($data['prices'][$item->id])) {
                        throw ValidationException::withMessages(['prices' => 'Renseignez le prix de chaque ligne.']);
                    }
                    $item->update(['unit_price' => $data['prices'][$item->id]]);
                }
            }
            $quote->update(collect($data)->except('prices')->all());
            AuditLog::record('quote.'.$data['status'], 'quote:'.$quote->id);
            $quote->customer->user->notify(new PlatformNotification('Votre devis '.$quote->number.' a ÃƒÂ©tÃƒÂ© mis ÃƒÂ  jour.', route('quotes.show', $quote)));
        });

        return back()->with('success', 'Devis mis à jour.');
    }

    public function accept(Request $request, Quote $quote)
    {
        abort_unless(auth()->user()->role?->name === 'CUSTOMER' && $quote->customer_id === auth()->user()->customer?->id, 403);
        $data = $request->validate(['decision' => 'required|in:accept,decline']);
        $order = DB::transaction(function () use ($quote, $data) {
            Customer::whereKey($quote->customer_id)->lockForUpdate()->firstOrFail();
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            if ($quote->status === 'ACCEPTED') {
                return $quote->order;
            }
            if ($quote->status !== 'OFFERED' || ! $quote->valid_until || $quote->valid_until->endOfDay()->isPast()) {
                throw ValidationException::withMessages(['decision' => 'Cette proposition est expirÃƒÂ©e ou indisponible.']);
            }
            if ($data['decision'] === 'decline') {
                $quote->update(['status' => 'DECLINED']);

                return null;
            }
            $zone = DeliveryZone::whereKey($quote->delivery_zone_id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $stocks = [];
            $total = 0;
            foreach ($quote->items()->orderBy('product_id')->get() as $line) {
                $product = Product::whereKey($line->product_id)->lockForUpdate()->firstOrFail();
                $stock = $product->stock()->lockForUpdate()->firstOrFail();
                $required = ($stocks[$product->id]['quantity'] ?? 0) + (float) $line->quantity;
                if (! $product->is_active || $stock->available_quantity < $required) {
                    throw ValidationException::withMessages(['decision' => 'Stock insuffisant pour '.$product->name.'. Contactez FRAINS pour une nouvelle proposition.']);
                }
                $stocks[$product->id] = ['stock' => $stock, 'quantity' => $required];
                $total += round($line->quantity * $line->unit_price, 2);
            }
            $order = Order::create(['order_number' => DocumentNumber::next('CMD'), 'customer_id' => $quote->customer_id, 'delivery_zone_id' => $zone->id, 'status' => 'NEW', 'subtotal' => $total, 'delivery_fee' => $quote->delivery_fee, 'total' => $total + $quote->delivery_fee, 'delivery_address' => $quote->delivery_address, 'notes' => $quote->notes, 'ordered_at' => now()]);
            foreach ($quote->items as $line) {
                $order->items()->create($line->only(['product_id', 'product_name', 'unit', 'quantity', 'unit_price', 'packaging']) + ['total' => round($line->quantity * $line->unit_price, 2)]);
            }
            foreach ($stocks as $productId => $line) {
                $line['stock']->increment('reserved_quantity', $line['quantity']);
                StockMovement::create(['product_id' => $productId, 'user_id' => auth()->id(), 'type' => 'RESERVATION', 'quantity' => $line['quantity'], 'reason' => $order->order_number]);
            }
            Delivery::create(['order_id' => $order->id, 'delivery_zone_id' => $zone->id, 'delivery_number' => DocumentNumber::next('LIV'), 'delivery_address' => $quote->delivery_address, 'customer_phone' => auth()->user()->phone, 'scheduled_date' => $quote->requested_date->isPast() ? today() : $quote->requested_date]);
            Payment::create(['order_id' => $order->id, 'customer_id' => $quote->customer_id, 'reference' => DocumentNumber::next('PAY'), 'amount' => $order->total, 'method' => 'BANK_TRANSFER']);
            $quote->update(['status' => 'ACCEPTED', 'order_id' => $order->id]);
            app(InvoiceService::class)->issue($order);
            AuditLog::record('quote.accepted', 'quote:'.$quote->id, ['order_id' => $order->id]);

            return $order;
        });

        return $order ? to_route('account.orders.show',$order)->with('success','Devis acceptÃƒÂ© et transformÃƒÂ© en commande.') : back()->with('success','Proposition dÃƒÂ©clinÃƒÂ©e.');
    }
}

