<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_stock_without_changing_reserved_quantities(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        $product = Product::where('sku', 'TOM-001')->firstOrFail();
        $product->stock->update(['reserved_quantity' => 10]);
        $before = (float) $product->stock->quantity;
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Ajouter du stock');
        $this->get(route('admin.stocks.index', ['product' => $product->id]))->assertOk()->assertSee('Ajouter au stock')->assertSee('value="'.$product->id.'" selected', false);
        $data = ['product_id' => $product->id, 'type' => 'SUPPLY', 'quantity' => 25.5, 'reason' => 'Réception test'];
        $this->post(route('admin.stocks.store'), $data)->assertSessionHasNoErrors();
        $stock = $product->stock->fresh();
        $this->assertEquals($before + 25.5, $stock->quantity);
        $this->assertEquals(10, $stock->reserved_quantity);
        $this->assertEquals($before + 15.5, $stock->available_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'SUPPLY', 'quantity' => 25.5, 'reason' => 'Réception test']);
        $this->post(route('admin.stocks.store'), array_replace($data, ['quantity' => -1]))->assertSessionHasErrors('quantity');
        $this->assertEquals($before + 25.5, $stock->fresh()->quantity);
    }
}
