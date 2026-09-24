<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ClientDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Customer
    {
        $user = $this->user('CUSTOMER');
        return Customer::create(['user_id' => $user->id, 'status' => 'active']);
    }

    private function user(string $role): User
    {
        return User::create(['first_name' => 'Test', 'last_name' => 'Suppression', 'email' => \Illuminate\Support\Str::uuid().'@example.test', 'phone' => '771234567', 'password' => 'password123', 'role_id' => Role::where('name', $role)->firstOrFail()->id, 'status' => 'active']);
    }

    public function test_deletion_removes_access_but_preserves_order_and_invoice(): void
    {
        $this->seed();
        $client = $this->client();
        $this->actingAs($client->user)->post(route('cart.store', Product::where('sku', 'TOM-001')->firstOrFail()), ['quantity' => 2]);
        $this->get(route('cart.index'));
        $this->post(route('guest.checkout'), ['first_name' => 'Test', 'last_name' => 'Client', 'phone' => '771234567', 'delivery_zone_id' => DeliveryZone::firstOrFail()->id, 'checkout_token' => session('checkout_token')])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        $this->delete(route('admin.clients.destroy', $client))->assertRedirect(route('admin.clients.index'));
        $this->assertNotNull($client->fresh()->account_deleted_at);
        $this->assertSame('inactive', $client->user->fresh()->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $client->id]);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id]);
        $this->get(route('admin.clients.index'))->assertDontSee('value="'.$client->id.'" form="delete-clients"', false);
        $this->get(route('admin.orders.show', $order))->assertOk();
        Auth::forgetGuards();
        $this->get(route('account.orders.index'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['full_name' => $client->user->name, 'phone' => $client->user->phone ?? '771234567'])->assertSessionHasErrors('full_name');
    }

    public function test_bulk_deletion_only_deletes_selected_clients_and_validates_selection(): void
    {
        $this->seed();
        [$first, $second, $kept] = [$this->client(), $this->client(), $this->client()];
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        $this->delete(route('admin.clients.destroyMany'), ['clients' => []])->assertSessionHasErrors('clients');
        $this->delete(route('admin.clients.destroyMany'), ['clients' => [$first->id, 999999]])->assertSessionHasErrors('clients.1');
        $this->assertNull($first->fresh()->account_deleted_at);
        $this->delete(route('admin.clients.destroyMany'), ['clients' => [$first->id, $second->id]])->assertRedirect(route('admin.clients.index'));
        $this->assertNotNull($first->fresh()->account_deleted_at);
        $this->assertNotNull($second->fresh()->account_deleted_at);
        $this->assertNull($kept->fresh()->account_deleted_at);
        $this->assertSame('active', $kept->user->fresh()->status);
        $this->put(route('admin.clients.update', $first), [])->assertStatus(410);
        $this->put(route('admin.users.update', $first->user), [])->assertStatus(410);
    }

    public function test_customer_and_commercial_cannot_delete_accounts(): void
    {
        $this->seed();
        $client = $this->client();
        $this->actingAs($client->user)->delete(route('admin.clients.destroy', $client))->assertRedirect(route('admin.login'));
        $commercial = $this->user('COMMERCIAL');
        $this->actingAs($commercial)->delete(route('admin.clients.destroy', $client))->assertForbidden();
        $this->delete(route('admin.clients.destroyMany'), ['clients' => [$client->id]])->assertForbidden();
        $this->assertNull($client->fresh()->account_deleted_at);
    }
}
