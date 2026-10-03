<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_wholesale_page_and_search_only_show_active_wholesale_products(): void
    {
        $this->seed();
        \App\Models\Setting::updateOrCreate(['key'=>'phone'], ['value'=>'00221 77 989 01 01']);
        $wholesale = Product::create(['name'=>'Article Gros Visible', 'sku'=>'GROS-TEST', 'slug'=>'gros-test', 'unit'=>'KG', 'base_price'=>500, 'minimum_order_quantity'=>10, 'wholesale_available'=>true, 'is_active'=>true]);
        $wholesale->stock()->create(['quantity'=>100, 'reserved_quantity'=>0]);
        Product::create(['name'=>'Article Detail Cache', 'sku'=>'DETAIL-TEST', 'slug'=>'detail-test', 'unit'=>'KG', 'base_price'=>500, 'minimum_order_quantity'=>1, 'wholesale_available'=>false, 'is_active'=>true]);
        Product::create(['name'=>'Article Gros Inactif', 'sku'=>'INACTIF-TEST', 'slug'=>'inactif-test', 'unit'=>'KG', 'base_price'=>500, 'minimum_order_quantity'=>1, 'wholesale_available'=>true, 'is_active'=>false]);
        foreach ([[], ['q'=>'Article'], ['q'=>'Article', 'wholesale'=>0]] as $params) {
            $this->get(route('public.page', ['page'=>'vente-en-gros'] + $params))->assertOk()
                ->assertSee($wholesale->name)->assertDontSee('Article Detail Cache')->assertDontSee('Article Gros Inactif')
                ->assertDontSee('Ajouter au panier')->assertDontSee('name="quantity"', false)
                ->assertDontSee(route('cart.store', $wholesale), false)
                ->assertSee('https://wa.me/221779890101?text=', false)
                ->assertSee(rawurlencode($wholesale->name), false)
                ->assertDontSee(route('quotes.start', ['product'=>$wholesale->id]), false)
                ->assertDontSee('Contacter l’administration');
        }
        $wholesale->stock->update(['reserved_quantity'=>95]);
        $this->get(route('public.page', 'vente-en-gros'))->assertDontSee($wholesale->name);
    }

    public function test_management_can_publish_and_remove_a_wholesale_product(): void
    {
        $this->seed();
        foreach (['ADMIN', 'MANAGER', 'COMMERCIAL'] as $role) {
            $user = User::create(['role_id'=>Role::where('name', $role)->firstOrFail()->id, 'first_name'=>$role, 'last_name'=>'Test', 'email'=>strtolower($role).'-wholesale@example.test', 'password'=>'password123', 'status'=>'active']);
            $this->actingAs($user)->get(route('admin.products.create', ['wholesale'=>1]))->assertOk()->assertSee('Disponible en gros');
            $data = ['name'=>'Gros '.$role, 'sku'=>'GROS-'.$role, 'unit'=>'KG', 'base_price'=>500, 'minimum_order_quantity'=>10, 'wholesale_available'=>1, 'is_active'=>1];
            $this->post(route('admin.products.store'), $data)->assertSessionHasNoErrors();
            $product = Product::where('sku', $data['sku'])->firstOrFail();
            $this->get(route('public.page', 'vente-en-gros'))->assertDontSee($data['name']);
            $product->stock->update(['quantity'=>100]);
            $this->get(route('public.page', 'vente-en-gros'))->assertOk()->assertSee($data['name']);
            $this->put(route('admin.products.update', $product), array_replace($data, ['wholesale_available'=>0]))->assertSessionHasNoErrors();
            $this->get(route('public.page', 'vente-en-gros'))->assertDontSee($data['name']);
        }
    }
}
