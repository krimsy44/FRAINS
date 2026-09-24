<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBoundariesTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsRole(string $role): void
    {
        $user = User::create(['first_name' => $role, 'last_name' => 'Test', 'email' => strtolower($role).'@roles.test',
            'role_id' => Role::where('name', $role)->firstOrFail()->id, 'password' => 'password123456', 'status' => 'active']);
        $this->actingAs($user, 'admin');
    }

    public function test_each_role_only_accesses_its_business_pages_and_resource_actions(): void
    {
        $this->seed();
        $routes = [
            'admin.products.index' => ['ADMIN', 'MANAGER', 'COMMERCIAL'],
            'admin.orders.index' => ['ADMIN', 'MANAGER', 'COMMERCIAL'],
            'admin.clients.index' => ['ADMIN', 'MANAGER', 'COMMERCIAL'],
            'admin.quotes.index' => ['ADMIN', 'MANAGER', 'COMMERCIAL'],
            'admin.stocks.index' => ['ADMIN', 'MANAGER', 'STOCK_MANAGER'],
            'admin.deliveries.index' => ['ADMIN', 'MANAGER', 'DELIVERY_MANAGER', 'DRIVER'],
            'admin.agriculture.index' => ['ADMIN', 'MANAGER', 'PRODUCER'],
            'admin.users.index' => ['ADMIN'],
            'admin.settings.index' => ['ADMIN'],
        ];
        foreach (['ADMIN', 'MANAGER', 'COMMERCIAL', 'STOCK_MANAGER', 'DELIVERY_MANAGER', 'PRODUCER', 'DRIVER'] as $role) {
            $this->loginAsRole($role);
            foreach ($routes as $route => $allowed) {
                $this->get(route($route))->assertStatus(in_array($role, $allowed) ? 200 : 403);
            }
            foreach (config('frains.resources') as $resource => $definition) {
                $allowed = in_array($role, $definition['roles']);
                $this->get(route('admin.resources.index', $resource))->assertStatus($allowed ? 200 : 403);
                if (!$allowed) {
                    $this->post(route('admin.resources.store', $resource), [])->assertForbidden();
                    $this->put(route('admin.resources.update', [$resource, 999999]), [])->assertForbidden();
                }
            }
        }
    }

    public function test_commercial_can_manage_content_stock_thresholds_and_order_delivery_details(): void
    {
        $this->seed();
        $this->loginAsRole('COMMERCIAL');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.orders.index'));
        $this->get(route('admin.orders.index'))->assertOk()
            ->assertSee(route('admin.resources.index', 'gallery'))
            ->assertSee(route('admin.resources.index', 'publications'))
            ->assertDontSee(route('admin.stocks.index'));
        $product = Product::firstOrFail();
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('name="minimum_stock"', false);
        $data = ['name' => 'Produit commercial', 'sku' => $product->sku, 'unit' => $product->unit,
            'base_price' => 800, 'minimum_order_quantity' => 1, 'is_active' => 1];
        $this->put(route('admin.products.update', $product), $data + ['minimum_stock' => 123])->assertSessionHasNoErrors();
        $this->put(route('admin.products.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertEquals(123, $product->stock->fresh()->minimum_quantity);
        $this->assertEquals(800, $product->fresh()->base_price);
        $order = Order::create(['order_number' => 'ROLE-TEST', 'status' => 'READY', 'subtotal' => 100, 'total' => 100]);
        $delivery = \App\Models\Delivery::create(['order_id' => $order->id, 'delivery_number' => 'ROLE-DELIVERY', 'delivery_address' => 'Dakar']);
        $this->patch(route('admin.orders.update', $order), [
            'status' => 'READY', 'driver_name' => 'Livreur test', 'driver_phone' => '771234567', 'scheduled_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('Livreur test', $delivery->fresh()->driver_name);
        $this->patch(route('admin.orders.update', $order), ['status' => 'SHIPPING'])->assertSessionHasNoErrors();
        $this->assertSame('SHIPPING', $order->fresh()->status);
        $this->post(route('admin.resources.store', 'publications'), [
            'title' => 'Actualite commerciale', 'category' => 'NEWS', 'content' => 'Actualite du catalogue.', 'status' => 'DRAFT',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('publications', ['title' => 'Actualite commerciale']);
    }
}
