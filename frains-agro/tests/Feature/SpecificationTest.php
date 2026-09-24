<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cultivation;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Driver;
use App\Models\Harvest;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Publication;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpecificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Product $product;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@frains-agro.sn')->firstOrFail();
        $this->client = $this->user('CUSTOMER', 'client');
        $this->zone = DeliveryZone::firstOrFail();
        Customer::create(['user_id' => $this->client->id, 'delivery_zone_id' => $this->zone->id, 'customer_type' => 'GROSSISTE', 'status' => 'active', 'credit_limit' => 1000000]);
        $this->product = Product::where('sku', 'TOM-001')->firstOrFail();
    }

    public function test_product_photo_upload_replacement_and_validation(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs($this->admin);
        $data = ['name' => 'Produit photo', 'sku' => 'PHOTO-TEST', 'unit' => 'KG', 'base_price' => 500, 'minimum_order_quantity' => 1, 'is_active' => 1];
        $this->post(route('admin.products.store'), $data + ['image' => \Illuminate\Http\UploadedFile::fake()->image('produit.jpg')])->assertSessionHasNoErrors()->assertRedirect(route('admin.products.index'));
        $product = Product::where('sku', 'PHOTO-TEST')->firstOrFail();
        $original = $product->image;
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($original);
        foreach (['admin.products.index', 'products.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('storage/'.$original);
        }
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('name="image"', false)->assertSee('multipart/form-data');
        $this->put(route('admin.products.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame($original, $product->fresh()->image);
        $this->put(route('admin.products.update', $product), $data + ['image' => \Illuminate\Http\UploadedFile::fake()->image('nouvelle.png')])->assertSessionHasNoErrors();
        $replacement = $product->fresh()->image;
        $this->assertNotSame($original, $replacement);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($replacement);
        $this->put(route('admin.products.update', $product), $data + ['image' => \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 10)])->assertSessionHasErrors('image');
        $this->put(route('admin.products.update', $product), $data + ['image' => \Illuminate\Http\UploadedFile::fake()->image('grande.jpg')->size(4097)])->assertSessionHasErrors('image');
        $this->assertSame($replacement, $product->fresh()->image);
    }

    public function test_public_and_admin_layouts_are_separate(): void
    {
        $this->actingAs($this->admin);
        foreach (['home', 'products.index'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('class="site-header"', false)
                ->assertDontSee('class="admin-header"', false)
                ->assertDontSee('Navigation de gestion');
        }
        foreach (['admin.dashboard', 'admin.products.index', 'admin.quotes.index', 'admin.notifications.index'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('class="admin-header"', false)
                ->assertSee('Navigation de gestion')
                ->assertDontSee('class="site-header"', false)
                ->assertDontSee('<footer>', false);
        }
        $this->actingAs($this->client);
        foreach (['quotes.index', 'notifications.index'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('class="site-header"', false)
                ->assertDontSee('Navigation de gestion');
        }
        $this->get(route('admin.dashboard'))->assertOk();
        $this->post(route('admin.logout'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    private function user(string $role, string $name): User
    {
        return User::create(['role_id' => Role::where('name', $role)->firstOrFail()->id, 'first_name' => $name, 'last_name' => 'Test', 'email' => $name.'@example.test', 'phone' => '770000000', 'password' => 'password123456', 'status' => 'active']);
    }

    private function order(): Order
    {
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 10])->assertSessionHasNoErrors();
        $this->get(route('checkout.create'))->assertOk();
        $this->post(route('checkout.store'), ['checkout_token' => session('checkout_token'), 'delivery_zone_id' => $this->zone->id, 'delivery_address' => 'Dakar', 'scheduled_date' => today()->addDay()->toDateString(), 'payment_method' => 'CASH'])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_all_admin_resource_pages_and_permissions(): void
    {
        $this->actingAs($this->admin);
        foreach (config('frains.resources') as $key => $definition) {
            $this->get(route('admin.resources.index', $key))->assertOk();
            $this->get(route('admin.resources.create', $key))->assertOk();
        }
        foreach (['admin.dashboard', 'admin.stocks.index', 'admin.clients.index', 'admin.deliveries.index', 'admin.agriculture.index', 'admin.settings.index', 'admin.users.index', 'admin.reports', 'admin.quotes.index', 'admin.notifications.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.catalogue.edit', $this->product))->assertOk();
        $this->post(route('admin.logout'));
        $this->actingAs($this->client)->get(route('admin.resources.index', 'zones'))->assertRedirect(route('admin.login'));
        $stock = $this->user('STOCK_MANAGER', 'stock');
        $this->actingAs($stock)->get(route('admin.stocks.index'))->assertOk();
        $this->get(route('admin.resources.index', 'publications'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_public_pages_and_customer_sections(): void
    {
        foreach (['a-propos', 'activites', 'vente-en-gros', 'actualites', 'galerie', 'contact'] as $page) {
            $this->get(route('public.page', $page))->assertOk();
        }
        $this->get(route('products.index', ['availability' => 'available', 'wholesale' => 1, 'min_price' => 100]))->assertOk()->assertSee('Tomate');
        $this->post(route('public.contact'), ['name' => 'Awa', 'email' => 'awa@example.test', 'subject' => 'Livraison', 'message' => 'Quels sont vos horaires ?'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_messages', 1);
        $this->actingAs($this->client);
        foreach (['tableau-de-bord', 'profil', 'adresses', 'paiements', 'factures', 'livraisons'] as $section) {
            $this->get(route('account.section', $section))->assertOk();
        }
    }

    public function test_quote_offer_acceptance_creates_one_order_and_pdf(): void
    {
        $this->actingAs($this->client)->get(route('quotes.create'))->assertOk();
        $this->post(route('quotes.store'), ['delivery_zone_id' => $this->zone->id, 'delivery_address' => 'Dakar', 'requested_date' => today()->addDay()->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 100, 'packaging' => 'Caisses']]])->assertSessionHasNoErrors();
        $quote = Quote::firstOrFail();
        $line = $quote->items->first();
        $this->get(route('quotes.show', $quote))->assertOk();
        $this->actingAs($this->admin)->put(route('admin.quotes.offer', $quote), ['status' => 'OFFERED', 'delivery_fee' => 2000, 'valid_until' => today()->addDays(7)->toDateString(), 'prices' => [$line->id => 500]])->assertSessionHasNoErrors();
        $this->actingAs($this->client)->post(route('quotes.accept', $quote), ['decision' => 'accept'])->assertSessionHasNoErrors();
        $order = $quote->fresh()->order;
        $this->assertEquals(52000, $order->total);
        $this->post(route('quotes.accept', $quote), ['decision' => 'accept'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(100, $this->product->stock->fresh()->reserved_quantity);
        $pdf = $this->get(route('invoices.show', $order))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_partial_payments_credit_and_overpayment(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin)->post(route('admin.payments.store', $order), ['amount' => 2000, 'method' => 'CASH'])->assertSessionHasNoErrors();
        $this->assertEquals(7000, $order->fresh()->balance);
        $this->assertSame('PARTIAL', $order->fresh()->payment_status);
        $this->put(route('admin.orders.due', $order), ['payment_due_date' => today()->addDays(10)->toDateString()])->assertSessionHasNoErrors();
        $this->post(route('admin.payments.store', $order), ['amount' => 8000, 'method' => 'CASH'])->assertSessionHasErrors('amount');
        $this->post(route('admin.payments.store', $order), ['amount' => 7000, 'method' => 'BANK_TRANSFER'])->assertSessionHasNoErrors();
        $this->assertEquals(0, $order->fresh()->balance);
        $this->assertDatabaseCount('payment_receipts', 2);
        $this->get(route('admin.reports', ['export' => 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_stock_cannot_consume_reserved_quantities(): void
    {
        $this->product->stock->update(['quantity' => 20, 'reserved_quantity' => 15]);
        $this->actingAs($this->admin)->post(route('admin.stocks.store'), ['product_id' => $this->product->id, 'type' => 'LOSS', 'quantity' => 6, 'reason' => 'Perte'])->assertSessionHasErrors('quantity');
        $this->post(route('admin.stocks.store'), ['product_id' => $this->product->id, 'type' => 'SUPPLY', 'quantity' => 10, 'reason' => 'Entrée'])->assertSessionHasNoErrors();
        $this->assertEquals(30, $this->product->stock->fresh()->quantity);
    }

    public function test_producer_scope_and_harvest_stock_integration(): void
    {
        $producerUser = $this->user('PRODUCER', 'producer');
        $producer = Producer::create(['user_id' => $producerUser->id, 'name' => 'Producteur', 'phone' => '770000000', 'zone' => 'Niayes', 'joined_at' => today()]);
        $parcel = Parcel::create(['reference' => 'P-001', 'producer_id' => $producer->id, 'location' => 'Niayes', 'area' => 2, 'started_at' => today()]);
        $culture = Cultivation::create(['parcel_id' => $parcel->id, 'product_id' => $this->product->id, 'area' => 1, 'planted_at' => today()->subDays(90), 'expected_harvest_at' => today(), 'expected_quantity' => 100, 'status' => 'GROWING']);
        $initial = $this->product->stock->quantity;
        $this->actingAs($producerUser)->get(route('admin.agriculture.index'))->assertOk();
        $this->post(route('admin.harvests.store'), ['cultivation_id' => $culture->id, 'harvested_at' => today()->toDateString(), 'quantity' => 50, 'loss_quantity' => 5])->assertSessionHasNoErrors();
        $this->assertEquals($initial + 45, $this->product->stock->fresh()->quantity);
        $other = $this->user('PRODUCER', 'otherproducer');
        $this->actingAs($other)->get(route('admin.resources.edit', ['cultivations', $culture->id]))->assertNotFound();
        $this->post(route('admin.harvests.store'), ['cultivation_id' => $culture->id, 'harvested_at' => today()->toDateString(), 'quantity' => 50, 'loss_quantity' => 5])->assertNotFound();
        $this->assertDatabaseCount('harvests', 1);
    }

    public function test_harvest_corrections_update_stock_and_protect_consumed_and_reserved_quantities(): void
    {
        $producer = Producer::create(['name' => 'Awa', 'phone' => '770000000', 'zone' => 'Niayes', 'joined_at' => today()]);
        $parcel = Parcel::create(['reference' => 'EDIT-P', 'producer_id' => $producer->id, 'location' => 'Niayes', 'area' => 1, 'started_at' => today()]);
        $culture = Cultivation::create(['parcel_id' => $parcel->id, 'product_id' => $this->product->id, 'area' => 1, 'planted_at' => today()->subDays(90), 'expected_harvest_at' => today(), 'expected_quantity' => 100, 'status' => 'GROWING']);
        $initial = $this->product->stock->quantity;
        $this->actingAs($this->admin, 'admin');
        $data = ['harvested_at' => today()->toDateString(), 'quantity' => 50, 'loss_quantity' => 5, 'notes' => 'Correction'];
        $this->post(route('admin.harvests.store'), $data + ['cultivation_id' => $culture->id])->assertSessionHasNoErrors();
        $harvest = Harvest::where('cultivation_id', $culture->id)->firstOrFail();
        $this->get(route('admin.harvests.edit', $harvest))->assertOk()->assertSee('Modifier une récolte');
        $this->get(route('admin.agriculture.index'))->assertOk()->assertSee(route('admin.harvests.edit', $harvest));
        $data['quantity'] = 70;
        $this->put(route('admin.harvests.update', $harvest), $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.agriculture.index'));
        $this->assertEquals($initial + 65, $this->product->stock->fresh()->quantity);
        $this->assertDatabaseHas('stock_lots', ['harvest_id' => $harvest->id, 'quantity' => 65, 'remaining_quantity' => 65]);
        $this->assertDatabaseHas('stock_movements', ['reason' => 'Récolte #'.$harvest->id, 'quantity' => 65]);
        $this->put(route('admin.harvests.update', $harvest), $data)->assertSessionHasNoErrors();
        $this->assertEquals($initial + 65, $this->product->stock->fresh()->quantity);
        $data['quantity'] = 60;
        $this->put(route('admin.harvests.update', $harvest), $data)->assertSessionHasNoErrors();
        $this->assertEquals($initial + 55, $this->product->stock->fresh()->quantity);
        $this->put(route('admin.harvests.update', $harvest), array_replace($data, ['harvested_at' => today()->subDays(91)->toDateString()]))->assertSessionHasErrors('harvested_at');
        $stock = $this->product->stock->fresh();
        $stock->update(['reserved_quantity' => $stock->quantity]);
        $this->put(route('admin.harvests.update', $harvest), array_replace($data, ['quantity' => 59]))->assertSessionHasErrors('quantity');
        $stock->update(['reserved_quantity' => 0]);
        \Illuminate\Support\Facades\DB::table('stock_lots')->where('harvest_id', $harvest->id)->update(['remaining_quantity' => 5]);
        $stock->decrement('quantity', 50);
        $this->put(route('admin.harvests.update', $harvest), array_replace($data, ['quantity' => 54]))->assertSessionHasErrors('quantity');
        $this->assertEquals(60, $harvest->fresh()->quantity);
        $this->actingAs($this->user('PRODUCER', 'unrelated-harvest'), 'admin');
        $this->get(route('admin.harvests.edit', $harvest))->assertNotFound();
        $this->put(route('admin.harvests.update', $harvest), $data)->assertNotFound();
    }

    public function test_driver_can_only_record_assigned_partial_deliveries(): void
    {
        $order = $this->order();
        $driverUser = $this->user('DRIVER', 'driver');
        $driver = Driver::create(['user_id' => $driverUser->id, 'name' => 'Livreur', 'phone' => '770000000', 'is_available' => true]);
        $this->actingAs($this->admin)->post(route('admin.deliveries.assign', $order->delivery), ['driver_id' => $driver->id, 'scheduled_date' => today()->addDay()->toDateString()])->assertSessionHasNoErrors();
        foreach (['CONFIRMED', 'PREPARING', 'READY'] as $status) {
            $this->patch(route('admin.orders.update', $order), ['status' => $status])->assertSessionHasNoErrors();
        }
        $item = $order->items->first();
        $this->actingAs($driverUser)->get(route('admin.deliveries.index'))->assertOk();
        $this->post(route('admin.deliveries.dispatch', $order->delivery), ['mark_paid' => 1, 'status' => 'COMPLETED'])->assertSessionHasNoErrors();
        $this->assertSame('PENDING', $order->fresh()->payment_status);
        $this->post(route('admin.deliveries.receive', $order->delivery), ['quantities' => [$item->id => 4]])->assertSessionHasNoErrors();
        $this->assertSame('PARTIALLY_DELIVERED', $order->fresh()->status);
        $this->post(route('admin.deliveries.receive', $order->delivery), ['quantities' => [$item->id => 3]])->assertSessionHasErrors('quantities');
        $this->post(route('admin.deliveries.receive', $order->delivery), ['quantities' => [$item->id => 10]])->assertSessionHasNoErrors();
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $other = $this->user('DRIVER', 'otherdriver');
        $this->actingAs($other)->post(route('admin.deliveries.receive', $order->delivery), ['quantities' => [$item->id => 10]])->assertForbidden();
    }

    public function test_draft_content_and_other_customer_addresses_are_private(): void
    {
        $p = Publication::create(['title' => 'Brouillon', 'category' => 'NEWS', 'content' => 'Confidentiel', 'status' => 'DRAFT', 'published_at' => now()]);
        $this->get(route('public.publication', $p))->assertNotFound();
        $this->actingAs($this->client)->post(route('account.addresses.store'), ['label' => 'Maison', 'address' => 'Dakar', 'city' => 'Dakar', 'delivery_zone_id' => $this->zone->id])->assertSessionHasNoErrors();
        $address = Address::firstOrFail();
        $other = $this->user('CUSTOMER', 'otherclient');
        Customer::create(['user_id' => $other->id, 'status' => 'active']);
        $this->actingAs($other)->delete(route('account.addresses.destroy', $address))->assertForbidden();
    }

    public function test_quote_threshold_and_overlapping_prices(): void
    {
        $this->actingAs($this->admin)->post(route('admin.catalogue.price', $this->product), ['min_quantity' => 10, 'max_quantity' => 99, 'price' => 600])->assertSessionHasNoErrors();
        $this->post(route('admin.catalogue.price', $this->product), ['min_quantity' => 50, 'max_quantity' => 200, 'price' => 500])->assertSessionHasErrors('min_quantity');
        $this->assertEquals(600, $this->product->priceFor(20));
        $this->product->update(['quote_threshold' => 100]);
        $this->actingAs($this->client)->post(route('cart.store', $this->product), ['quantity' => 100])->assertRedirect(route('quotes.start', ['product' => $this->product->id]));
    }

    public function test_resource_creation_and_area_validation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.resources.store', 'zones'), ['name' => 'Thiès', 'base_fee' => 4000, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.resources.store', 'categories'), ['name' => 'Fruits', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.resources.store', 'producers'), ['name' => 'Awa', 'phone' => '771234567', 'zone' => 'Niayes', 'joined_at' => today()->toDateString(), 'status' => 'active'])->assertSessionHasNoErrors();
        $producer = Producer::firstOrFail();
        $this->post(route('admin.resources.store', 'parcels'), ['reference' => 'TEST-P', 'producer_id' => $producer->id, 'location' => 'Niayes', 'area' => 2, 'status' => 'ACTIVE', 'started_at' => today()->toDateString()])->assertSessionHasNoErrors();
        $parcel = Parcel::firstOrFail();
        $data = ['parcel_id' => $parcel->id, 'product_id' => $this->product->id, 'area' => 1.5, 'planted_at' => today()->toDateString(), 'expected_harvest_at' => today()->addDays(90)->toDateString(), 'expected_quantity' => 1000, 'status' => 'GROWING'];
        $this->post(route('admin.resources.store', 'cultivations'), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.resources.store', 'cultivations'), $data)->assertSessionHasErrors('area');
        $this->post(route('admin.resources.store', 'publications'), ['title' => 'Récolte du jour', 'category' => 'NEWS', 'content' => 'Informations de récolte', 'status' => 'PUBLISHED'])->assertSessionHasNoErrors();
        $this->get(route('public.publication', Publication::firstOrFail()))->assertOk();
    }

    public function test_harvest_lots_follow_sales_and_duplicate_submissions(): void
    {
        $this->product->stock->update(['quantity' => 0]);
        $producer = Producer::create(['name' => 'Awa', 'phone' => '770000000', 'zone' => 'Niayes', 'joined_at' => today()]);
        $parcel = Parcel::create(['reference' => 'LOT-P', 'producer_id' => $producer->id, 'location' => 'Niayes', 'area' => 1, 'started_at' => today()]);
        $culture = Cultivation::create(['parcel_id' => $parcel->id, 'product_id' => $this->product->id, 'area' => 1, 'planted_at' => today()->subDays(90), 'expected_harvest_at' => today(), 'expected_quantity' => 100, 'status' => 'GROWING']);
        $data = ['cultivation_id' => $culture->id, 'harvested_at' => today()->toDateString(), 'quantity' => 50, 'loss_quantity' => 5, 'submission_token' => (string) Str::uuid()];
        $this->actingAs($this->admin)->post(route('admin.harvests.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.harvests.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('harvests', 1);
        $order = $this->order();
        $this->actingAs($this->admin);
        foreach (['CONFIRMED', 'PREPARING', 'READY', 'SHIPPING'] as $status) {
            $this->patch(route('admin.orders.update', $order), ['status' => $status, 'driver_name' => 'Moussa', 'driver_phone' => '770000000'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('stock_lots', ['harvest_id' => Harvest::first()->id, 'remaining_quantity' => 35]);
        $this->assertDatabaseHas('stock_lot_allocations', ['quantity' => 10]);
        $receipt = ['amount' => 2000, 'method' => 'CASH', 'submission_token' => (string) Str::uuid()];
        $this->post(route('admin.payments.store', $order), $receipt)->assertSessionHasNoErrors();
        $this->post(route('admin.payments.store', $order), $receipt)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_receipts', 1);
        $this->get(route('admin.agriculture.index'))->assertOk()->assertSee('35');
    }

    public function test_api_and_pdf_do_not_expose_other_customer_orders(): void
    {
        $order = $this->order();
        $this->getJson(route('api.orders.show', $order))->assertOk()->assertJsonPath('number', $order->order_number);
        $other = $this->user('CUSTOMER', 'apiother');
        Customer::create(['user_id' => $other->id, 'status' => 'active']);
        $this->actingAs($other)->getJson(route('api.orders.show', $order))->assertForbidden();
        $this->get(route('invoices.show', $order))->assertForbidden();
        $this->getJson(route('api.products'))->assertOk();
        $this->getJson(route('api.orders'))->assertOk()->assertJsonCount(0, 'data');
    }
}
