<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    public function test_client_cancellation_releases_stock_once_and_preserves_history(): void
    {
        $order = $this->placeOrder();
        $quantity = $this->product->stock->fresh()->quantity;
        $this->get(route('account.orders.show', $order))->assertSee('Annuler ma commande');
        $this->post(route('account.orders.cancel', $order))->assertRedirect(route('account.orders.show', $order));
        $this->post(route('account.orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame('CANCELLED', $order->delivery->fresh()->status);
        $this->assertEquals(0, $this->product->stock->fresh()->reserved_quantity);
        $this->assertEquals($quantity, $this->product->stock->fresh()->quantity);
        $this->assertSame(1, StockMovement::where('type', 'RELEASE')->where('reason', $order->order_number)->count());
        $this->assertSame(1, $order->statusUpdates()->where('status', 'CANCELLED')->count());
        $this->assertNotNull($order->invoice);
        $this->get(route('account.orders.show', $order))->assertOk()->assertDontSee('Annuler ma commande');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Commande annulée par le client.');
    }

    public function test_client_cannot_cancel_another_clients_order_or_a_paid_or_shipped_order(): void
    {
        $order = $this->placeOrder();
        $other = User::create(['role_id' => $this->client->role_id, 'first_name' => 'Autre', 'last_name' => 'Client', 'email' => 'cancel-other@example.test', 'password' => 'password123', 'status' => 'active']);
        Customer::create(['user_id' => $other->id, 'status' => 'active']);
        $this->actingAs($other)->post(route('account.orders.cancel', $order))->assertForbidden();
        $this->actingAs($this->client);
        foreach (['SHIPPING', 'PARTIALLY_DELIVERED', 'DELIVERED', 'COMPLETED', 'REFUSED'] as $status) {
            $order->update(['status' => $status]);
            $this->post(route('account.orders.cancel', $order))->assertSessionHasErrors('cancellation');
            $this->assertSame($status, $order->fresh()->status);
        }
        $order->update(['status' => 'CONFIRMED', 'payment_status' => 'PARTIAL']);
        $this->post(route('account.orders.cancel', $order))->assertSessionHasErrors('cancellation');
        $this->assertEquals(10, $this->product->stock->fresh()->reserved_quantity);
        $this->assertSame(0, StockMovement::where('type', 'RELEASE')->count());
    }

    public function test_client_cancellation_rolls_back_when_reserved_stock_is_inconsistent(): void
    {
        $order = $this->placeOrder();
        $this->product->stock->update(['reserved_quantity' => 0]);
        $this->post(route('account.orders.cancel', $order))->assertSessionHasErrors('cancellation');
        $this->assertSame('NEW', $order->fresh()->status);
        $this->assertSame('PENDING', $order->delivery->fresh()->status);
        $this->assertSame(0, $order->statusUpdates()->where('status', 'CANCELLED')->count());
    }

    public function test_connected_client_cart_order_appears_in_account_and_tracking(): void
    {
        $other = Customer::create(['first_name' => 'Autre', 'last_name' => 'Personne', 'phone' => '771234567', 'status' => 'active']);
        $this->actingAs($this->admin);
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 2]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Votre commande sera enregistrée dans votre espace client.');
        $customers = Customer::count();
        $this->post(route('guest.checkout'), [
            'first_name' => 'Autre', 'last_name' => 'Personne', 'phone' => '771234567',
            'delivery_zone_id' => $this->zone->id, 'checkout_token' => session('checkout_token'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('account.orders.show', Order::firstOrFail()));
        $order = Order::firstOrFail();
        $this->assertSame($this->client->customer->id, $order->customer_id);
        $this->assertNotSame($other->id, $order->customer_id);
        $this->assertSame($customers, Customer::count());
        $this->assertNull($order->delivery->scheduled_date);
        $this->get(route('account.section'))->assertOk()->assertSee($order->order_number);
        $this->get(route('account.orders.index'))->assertOk()->assertSee($order->order_number);
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Votre commande a été reçue.');
        $this->patch(route('admin.orders.update', $order), ['status' => 'CONFIRMED'])->assertSessionHasNoErrors();
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Commande confirmee');
        $this->assertSame('CONFIRMED', $order->fresh()->status);
    }

    public function test_client_login_discards_a_previous_admin_destination(): void
    {
        $this->withSession(['auth.intended.admin' => route('admin.products.index')])
            ->post(route('login.store'), ['full_name' => $this->client->name, 'phone' => $this->client->phone])
            ->assertRedirect(route('account.orders.index'));
        $this->assertAuthenticatedAs($this->client);
    }

    private User $admin;

    private Product $product;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = DeliveryZone::create(['name' => 'Dakar', 'base_fee' => 2000, 'is_active' => true]);
        foreach (['CUSTOMER', 'ADMIN'] as $name) {
            $role = Role::create(['name' => $name, 'label' => $name]);
            $user = User::create(['role_id' => $role->id, 'first_name' => $name, 'last_name' => 'Test', 'email' => strtolower($name).'@example.test', 'password' => 'password123', 'status' => 'active', 'phone' => '770000000']);
            if ($name === 'CUSTOMER') {
                $this->client = $user;
            } else {
                $this->admin = $user;
            }
        }
        Customer::create(['user_id' => $this->client->id, 'delivery_zone_id' => $this->zone->id, 'status' => 'active']);
        $this->product = Product::create(['name' => 'Tomate', 'slug' => 'tomate', 'sku' => 'TOM', 'unit' => 'KG', 'base_price' => 700, 'minimum_order_quantity' => 1, 'is_active' => true]);
        Stock::create(['product_id' => $this->product->id, 'quantity' => 100, 'reserved_quantity' => 0]);
    }

    private function placeOrder(): Order
    {
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 10])->assertRedirect(route('cart.index'));
        $this->get(route('checkout.create'))->assertOk()->assertSee('Livraison et règlement');
        $this->post(route('checkout.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function payload(): array
    {
        return ['checkout_token' => session('checkout_token'), 'delivery_zone_id' => $this->zone->id, 'delivery_address' => 'Dakar Plateau', 'scheduled_date' => now()->addDay()->toDateString(), 'payment_method' => 'CASH'];
    }

    public function test_complete_commercial_journey(): void
    {
        $order = $this->placeOrder();
        $this->assertEquals(9000, $order->total);
        $this->assertEquals(10, $this->product->stock->fresh()->reserved_quantity);
        $this->get(route('account.orders.index'))->assertOk();
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Nouvelle');
        $this->actingAs($this->admin)->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.orders.show', $order))->assertOk();
        foreach (['CONFIRMED', 'PREPARING', 'READY', 'SHIPPING', 'DELIVERED'] as $status) {
            $this->patch(route('admin.orders.update', $order), ['status' => $status, 'driver_name' => 'Moussa', 'driver_phone' => '771234567'])->assertSessionHasNoErrors();
        }
        $this->assertEquals(90, $this->product->stock->fresh()->quantity);
        $this->assertEquals(0, $this->product->stock->fresh()->reserved_quantity);
        $this->patch(route('admin.orders.update', $order), ['status' => 'COMPLETED'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'COMPLETED', 'mark_paid' => 1])->assertSessionHasNoErrors();
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame('PAID', $order->fresh()->payment_status);
        $this->assertNotNull($order->delivery->delivered_at);
    }

    public function test_cancellation_releases_stock_once(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), ['status' => 'CANCELLED'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $order), ['status' => 'CANCELLED'])->assertSessionHasErrors('status');
        $this->assertEquals(100, $this->product->stock->fresh()->quantity);
        $this->assertEquals(0, $this->product->stock->fresh()->reserved_quantity);
        $this->assertEquals(1, StockMovement::where('type', 'RELEASE')->count());
    }

    public function test_access_is_scoped_to_role_and_customer(): void
    {
        $order = $this->placeOrder();
        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.orders.update', $order), ['status' => 'CONFIRMED'])->assertRedirect(route('admin.login'));
        $other = User::create(['role_id' => $this->client->role_id, 'first_name' => 'Autre', 'last_name' => 'Client', 'email' => 'other@example.test', 'password' => 'password123']);
        Customer::create(['user_id' => $other->id, 'status' => 'active']);
        $this->actingAs($other)->get(route('account.orders.show', $order))->assertForbidden();
        $this->post(route('logout'));
        $this->actingAs($this->admin)->get(route('checkout.create'))->assertRedirect(route('login'));
        $this->client->update(['status' => 'inactive']);
        $this->actingAs($this->client->fresh())->get(route('account.orders.index'))->assertForbidden();
    }

    public function test_stock_change_rolls_back_checkout_and_preserves_cart(): void
    {
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 10]);
        $this->get(route('checkout.create'));
        $this->product->stock->update(['quantity' => 5]);
        $this->post(route('checkout.store'), $this->payload())->assertSessionHasErrors('cart')->assertSessionHas('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertEquals(0, $this->product->stock->fresh()->reserved_quantity);
    }

    public function test_replayed_submission_does_not_duplicate_order(): void
    {
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 10]);
        $this->get(route('checkout.create'));
        $payload = $this->payload();
        $cart = session('cart');
        $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();
        $this->withSession(['cart' => $cart, 'checkout_token' => $payload['checkout_token']])->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(10, $this->product->stock->fresh()->reserved_quantity);
    }

    public function test_invalid_transition_and_inactive_zone_are_rejected(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), ['status' => 'DELIVERED'])->assertSessionHasErrors('status');
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 10]);
        $this->get(route('checkout.create'));
        $this->zone->update(['is_active' => false]);
        $this->post(route('checkout.store'), $this->payload())->assertSessionHasErrors('delivery_zone_id');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_admin_creates_client_and_client_login_preserves_cart(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.clients.store'), [
                'first_name' => 'Awa',
                'last_name' => 'Diop',
                'email' => 'awa@example.test',
                'phone' => '771234567',
                'address' => 'Plateau',
                'city' => 'Dakar',
                'delivery_zone_id' => $this->zone->id,
                'customer_type' => 'GROSSISTE',
                'company_name' => 'Awa Services',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.clients.index'));

        $this->assertDatabaseHas('customers', ['customer_type' => 'GROSSISTE', 'company_name' => 'Awa Services']);

        auth()->logout();

        $this->withSession(['cart' => ['example']])
            ->post(route('login.store'), ['full_name' => 'Awa Diop', 'phone' => '771234567'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHas('cart');

        $this->assertSame('CUSTOMER', auth()->user()->role->name);
    }

    public function test_cart_minimum_and_product_availability_are_enforced(): void
    {
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 10]);
        $this->product->update(['minimum_order_quantity' => 5]);
        $this->patch(route('cart.update', $this->product->id), ['quantity' => 1])->assertSessionHasErrors('quantity');
        $this->post(route('cart.store', $this->product), ['quantity' => 5.001])->assertSessionHasErrors('quantity');
        $this->product->update(['is_active' => false]);
        $this->post(route('cart.store', $this->product), ['quantity' => 5])->assertNotFound();
        $this->patch(route('cart.update', $this->product->id), ['quantity' => 0])->assertRedirect();
        $this->assertEmpty(session('cart'));
    }

    public function test_paid_cancellation_and_shipping_without_driver_are_rejected(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), ['status' => 'CONFIRMED', 'mark_paid' => 1])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $order), ['status' => 'CANCELLED'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'PREPARING'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $order), ['status' => 'READY'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $order), ['status' => 'SHIPPING'])->assertSessionHasErrors('status');
        $this->assertEquals(100, $this->product->stock->fresh()->quantity);
        $this->assertEquals(10, $this->product->stock->fresh()->reserved_quantity);
    }
}
