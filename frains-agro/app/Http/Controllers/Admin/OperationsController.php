<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\PaymentService;
use App\Services\PlatformNotice;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    public function stocks()
    {
        return view('admin.stocks', ['stocks' => Stock::with('product')->paginate(20), 'products' => Product::orderBy('name')->get(), 'movements' => StockMovement::with(['product', 'user'])->latest()->limit(100)->get()]);
    }

    public function finance(string $section)
    {
        abort_unless(in_array($section, ['factures', 'paiements']), 404);

        return view('admin.finance', ['section' => $section, 'orders' => Order::with(['customer.user', 'receipts', 'payment', 'invoice'])->latest()->paginate(20)]);
    }

    public function movement(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|exists:products,id', 'type' => 'required|in:SUPPLY,RETURN,LOSS,DAMAGE,OTHER_OUT', 'quantity' => 'required|numeric|min:0.01|max:999999999|decimal:0,2', 'reason' => 'required|string|max:255']);
        DB::transaction(function () use ($data) {
            $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $stock = $product->stock()->firstOrCreate([], ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0]);
            $stock = Stock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $incoming = in_array($data['type'], ['SUPPLY', 'RETURN']);
            if (! $incoming && $stock->available_quantity < $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Cette sortie empiéterait sur le stock réservé.']);
            }
            $movement = StockMovement::create($data + ['user_id' => auth()->id()]);
            if ($incoming) {
                app(StockLedger::class)->enter($stock, (float) $data['quantity']);
            } else {
                app(StockLedger::class)->consume($stock, $movement);
            }
            $stock->quantity = round($stock->quantity + ($incoming ? 1 : -1) * $data['quantity'], 2);
            $stock->save();
            AuditLog::record('stock.movement', 'movement:'.$movement->id, $data);
            PlatformNotice::stock($stock);
        });

        return back()->with('success', 'Mouvement de stock enregistré.');
    }

    public function clients()
    {
        return view('admin.clients', ['clients' => Customer::whereNull('account_deleted_at')->with('user')->withCount('orders')->paginate(20), 'zones' => DeliveryZone::where('is_active', true)->orderBy('name')->get()]);
    }


    public function storeClient(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:80'],
            'delivery_zone_id' => ['required', 'exists:delivery_zones,id'],
            'customer_type' => ['required', 'in:PARTICULIER,PROFESSIONNEL,REVENDEUR,GROSSISTE,RESTAURANT,HOTEL,SUPERMARCHE,ENTREPRISE,DISTRIBUTEUR'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);

        DB::transaction(function () use ($data) {
            $role = Role::firstOrCreate(['name' => 'CUSTOMER'], ['label' => 'Client']);
            $zone = DeliveryZone::whereKey($data['delivery_zone_id'])->where('is_active', true)->firstOrFail();

            $name = Str::of($data['first_name'].' '.$data['last_name'])->squish()->lower()->toString();
            $phone = preg_replace('/\D+/', '', $data['phone']);
            $matches = Customer::with('user')->where('status', 'active')->whereNull('account_deleted_at')
                ->lockForUpdate()->get()->filter(function (Customer $candidate) use ($name, $phone) {
                    return Str::of($candidate->name)->squish()->lower()->toString() === $name
                        && preg_replace('/\D+/', '', $candidate->contact_phone ?? '') === $phone;
                });
            if ($matches->count() > 1 || $matches->contains(fn ($candidate) => $candidate->user_id !== null)) {
                throw ValidationException::withMessages(['phone' => 'Une fiche avec ces coordonnées existe déjà. Modifiez le compte existant plutôt que de créer un doublon.']);
            }
            $customer = $matches->first() ?? new Customer;

            $user = User::create([
                'role_id' => $role->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? 'client-'.Str::uuid().'@frains.local',
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'status' => 'active',
                'password' => $data['password'] ?? Str::random(32),
            ]);

            $customer->fill([
                'user_id' => $user->id,
                'delivery_zone_id' => $zone->id,
                'customer_type' => $data['customer_type'],
                'company_name' => $data['company_name'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'status' => 'active',
            ])->save();

            AuditLog::record('customer.created', 'customer:'.$customer->id, ['user_id' => $user->id]);
        });

        return to_route('admin.clients.index')->with('success', 'Compte client cree. Le client peut se connecter avec son nom complet et son telephone.');
    }
    public function client(Customer $customer)
    {
        return view('admin.client', ['customer' => $customer->load('user'), 'orders' => $customer->orders()->latest()->paginate(20)]);
    }

    public function updateClient(Request $request, Customer $customer)
    {
        abort_if($customer->account_deleted_at, 410, 'Ce compte client a été supprimé.');
        $data = $request->validate(['status' => 'required|in:active,inactive', 'customer_type' => 'required|in:PARTICULIER,PROFESSIONNEL,REVENDEUR,GROSSISTE,RESTAURANT,HOTEL,SUPERMARCHE,ENTREPRISE,DISTRIBUTEUR', 'credit_limit' => 'required|numeric|min:0|max:999999999|decimal:0,2']);
        $customer->update($data);
        AuditLog::record('customer.updated', 'customer:'.$customer->id, $data);

        return back()->with('success', 'Fiche client mise à jour.');
    }

    public function destroyClient(Customer $customer)
    {
        $this->deleteClientAccounts([$customer->id]);
        return to_route('admin.clients.index')->with('success', 'Compte client supprimé. Son historique est conservé.');
    }

    public function destroyClients(Request $request)
    {
        $data = $request->validate(['clients' => 'required|array|min:1|max:100', 'clients.*' => 'required|integer|distinct|exists:customers,id']);
        $count = $this->deleteClientAccounts($data['clients']);
        return to_route('admin.clients.index')->with('success', $count.' compte(s) client(s) supprimé(s). Les historiques sont conservés.');
    }

    private function deleteClientAccounts(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $customers = Customer::whereIn('id', $ids)->whereNull('account_deleted_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($customers as $customer) {
                $user = $customer->user_id ? User::whereKey($customer->user_id)->lockForUpdate()->firstOrFail() : null;
                abort_if($user && $user->role?->name !== 'CUSTOMER', 422, 'Ce compte ne correspond pas à un client.');
                $customer->status = 'inactive';
                $customer->account_deleted_at = now();
                $customer->save();
                if ($user) {
                    $user->status = 'inactive';
                    $user->remember_token = null;
                    $user->save();
                }
                AuditLog::record('customer.account_deleted', 'customer:'.$customer->id, ['user_id' => $user?->id, 'history_preserved' => true]);
            }
            return $customers->count();
        });
    }

    public function payment(Request $request, Order $order, PaymentService $service)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|decimal:0,2', 'method' => 'required|in:CASH,BANK_TRANSFER,MOBILE_MONEY', 'external_reference' => 'nullable|string|max:120']);
        $data += $request->validate(['submission_token' => 'nullable|uuid']);
        $service->receive($order, $data);

        return back()->with('success', 'Règlement enregistré.');
    }

    public function dueDate(Request $request, Order $order)
    {
        $data = $request->validate(['payment_due_date' => 'required|date|after_or_equal:today']);
        DB::transaction(function () use ($order, $data) {
            $customer = Customer::whereKey($order->customer_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $outstanding = $customer->orders()->whereNotIn('status', ['CANCELLED', 'REFUSED'])->get()->sum(fn ($o) => $o->balance);
            if ($customer->customer_type === 'PARTICULIER' || $customer->credit_limit <= 0 || $outstanding > $customer->credit_limit) {
                throw ValidationException::withMessages(['payment_due_date' => 'Crédit professionnel non autorisé ou plafond dépassé.']);
            }
            $order->update($data);
            AuditLog::record('order.credit', 'order:'.$order->id, $data);
        });

        return back()->with('success', 'Échéance enregistrée.');
    }

    public function deliveries()
    {
        $query = Delivery::with(['order.customer.user', 'zone']);
        if (auth()->user()->role?->name === 'DRIVER') {
            $query->whereIn('driver_id', Driver::where('user_id', auth()->id())->select('id'));
        }

        return view('admin.deliveries', ['deliveries' => $query->latest()->paginate(20), 'drivers' => Driver::where('is_available', true)->get()]);
    }

    public function assign(Request $request, Delivery $delivery)
    {
        $data = $request->validate(['driver_id' => 'required|exists:drivers,id', 'scheduled_date' => 'required|date|after_or_equal:today']);
        DB::transaction(function () use ($delivery, $data) {
            $order = Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['DELIVERED', 'COMPLETED', 'CANCELLED', 'REFUSED'])) {
                throw ValidationException::withMessages(['driver_id' => 'La livraison est clôturée.']);
            }
            $driver = Driver::whereKey($data['driver_id'])->where('is_available', true)->lockForUpdate()->firstOrFail();
            $delivery->update($data + ['driver_name' => $driver->name, 'driver_phone' => $driver->phone, 'assigned_at' => now()]);
            AuditLog::record('delivery.assigned', 'delivery:'.$delivery->id, ['driver_id' => $driver->id]);
        });

        return back()->with('success', 'Livreur affecté.');
    }

    public function dispatch(Request $request, Delivery $delivery)
    {
        return DB::transaction(function () use ($request, $delivery) {
            $order = Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
            $delivery->refresh();
            if (auth()->user()->role?->name === 'DRIVER') {
                abort_unless(Driver::whereKey($delivery->driver_id)->where('user_id', auth()->id())->exists(), 403);
            }
            if ($order->status !== 'READY') {
                throw ValidationException::withMessages(['status' => 'La commande doit être prête avant le départ.']);
            }
            // Reuse the same validated transition and stock accounting, with no financial input.
            $request->query->replace([]);
            $request->replace(['status' => 'SHIPPING', 'mark_paid' => false]);

            return app(OrderController::class)->update($request, $order);
        });
    }

    public function deliver(Request $request, Delivery $delivery)
    {
        if (auth()->user()->role?->name === 'DRIVER') {
            abort_unless(Driver::whereKey($delivery->driver_id)->where('user_id', auth()->id())->exists(), 403);
        }
        $data = $request->validate(['quantities' => 'required|array', 'quantities.*' => 'required|numeric|min:0|decimal:0,2']);
        DB::transaction(function () use ($delivery, $data) {
            $order = Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
            $delivery->refresh();
            if (auth()->user()->role?->name === 'DRIVER') {
                abort_unless(Driver::whereKey($delivery->driver_id)->where('user_id', auth()->id())->exists(), 403);
            }
            if (! in_array($order->status, ['SHIPPING', 'PARTIALLY_DELIVERED'])) {
                throw ValidationException::withMessages(['quantities' => 'La commande doit être en livraison.']);
            }
            $complete = true;
            $changed = false;
            foreach ($order->items as $item) {
                $quantity = $data['quantities'][$item->id] ?? $item->delivered_quantity;
                if ($quantity < $item->delivered_quantity || $quantity > $item->quantity) {
                    throw ValidationException::withMessages(['quantities' => 'La quantité cumulée doit être comprise entre le déjà livré et le commandé.']);
                }
                $changed = $changed || $quantity > $item->delivered_quantity;
                $complete = $complete && $quantity == $item->quantity;
                $item->update(['delivered_quantity' => $quantity]);
            }
            if (! $changed) {
                throw ValidationException::withMessages(['quantities' => 'Aucune nouvelle quantité livrée.']);
            }
            $status = $complete ? 'DELIVERED' : 'PARTIALLY_DELIVERED';
            $order->update(['status' => $status, 'delivery_status' => $status]);
            $delivery->update(['status' => $status, 'delivered_at' => $complete ? now() : null]);
            AuditLog::record('delivery.'.$status, 'order:'.$order->id, $data);
            $order->customer?->user?->notify(new PlatformNotification('Livraison mise à jour : '.$order->order_number, route('account.orders.show', $order)));
        });

        return back()->with('success', 'Réception enregistrée.');
    }
}
