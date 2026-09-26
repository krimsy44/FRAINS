<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function prepareCart(): array
    {
        $this->seed();
        $product = Product::where('sku', 'TOM-001')->firstOrFail();
        $this->post(route('cart.store', $product), ['quantity' => 2]);
        $this->get(route('cart.index'))->assertOk()->assertSee('Commandez sans créer de compte.')->assertSee('name="first_name"', false);
        return ['first_name' => 'Awa', 'last_name' => 'Diop', 'phone' => '771234567', 'delivery_zone_id' => DeliveryZone::firstOrFail()->id, 'checkout_token' => session('checkout_token')];
    }

    public function test_admin_account_creation_reuses_guest_customer_and_preserves_order_access(): void
    {
        $data = $this->prepareCart();
        $this->post(route('guest.checkout'), $data)->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertMatchesRegularExpression('/^LIV-'.now()->format('Y').'-[0-9]{6}$/', $order->delivery->delivery_number);
        $customerId = $order->customer_id;
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail(), 'admin');
        $account = [
            'first_name' => 'awa', 'last_name' => 'DIOP', 'phone' => '77 123 45 67',
            'address' => 'Dakar', 'city' => 'Dakar', 'delivery_zone_id' => $data['delivery_zone_id'],
            'customer_type' => 'PARTICULIER',
        ];
        $this->post(route('admin.clients.store'), $account)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customers', 1);
        $customer = Customer::findOrFail($customerId);
        $this->assertNotNull($customer->user_id);
        $this->assertEquals($customerId, $order->fresh()->customer_id);
        $this->post(route('admin.clients.store'), $account)->assertSessionHasErrors('phone');
        $this->post(route('login.store'), ['full_name' => 'Awa Diop', 'phone' => '771234567'])->assertRedirect(route('account.orders.index'));
        $this->get(route('account.orders.index'))->assertOk()->assertSee($order->order_number);
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee($order->order_number);
    }

    public function test_guest_orders_without_creating_a_user_and_confirmation_is_private(): void
    {
        $data = $this->prepareCart();
        $users = User::count();
        $this->post(route('guest.checkout'), $data)->assertSessionHasNoErrors()->assertRedirect(route('guest.confirmation'));
        $this->assertGuest();
        $this->assertSame($users, User::count());
        $order = Order::firstOrFail();
        $this->assertNull($order->customer->user_id);
        $this->assertSame('Awa Diop', $order->customer->name);
        $this->assertSame('771234567', $order->delivery->customer_phone);
        $this->assertNull($order->delivery->scheduled_date);
        $this->assertEquals(1400 + DeliveryZone::first()->base_fee, $order->total);
        $this->assertSame('Awa Diop', $order->invoice->snapshot['customer']['name']);
        $this->get(route('guest.confirmation'))->assertOk()->assertSee($order->order_number);
        $this->post(route('guest.checkout'), $data);
        $this->assertDatabaseCount('orders', 1);
        $this->flushSession();
        $this->get(route('guest.confirmation'))->assertNotFound();
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Awa Diop')->assertSee('771234567');
        $this->get(route('admin.clients.show', $order->customer))->assertOk()->assertSee('Awa Diop');
    }

    public function test_guest_validation_and_stock_failures_do_not_create_records(): void
    {
        $data = $this->prepareCart();
        $customers = Customer::count();
        $this->post(route('guest.checkout'), array_replace($data, ['first_name' => '', 'phone' => 'abc']))->assertSessionHasErrors(['first_name', 'phone']);
        DeliveryZone::whereKey($data['delivery_zone_id'])->update(['is_active' => false]);
        $this->post(route('guest.checkout'), $data)->assertSessionHasErrors('delivery_zone_id');
        DeliveryZone::whereKey($data['delivery_zone_id'])->update(['is_active' => true]);
        Product::where('sku', 'TOM-001')->first()->stock->update(['quantity' => 0]);
        $this->post(route('guest.checkout'), $data)->assertSessionHasErrors('cart');
        $this->assertSame($customers, Customer::count());
        $this->assertDatabaseCount('orders', 0);
    }
}
