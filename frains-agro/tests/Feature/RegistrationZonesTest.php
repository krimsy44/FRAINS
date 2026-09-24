<?php

namespace Tests\Feature;

use App\Models\DeliveryZone;
use App\Models\User;
use Database\Seeders\DeliveryZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationZonesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_client_form_explains_empty_zones_and_lists_active_zones(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@frains-agro.sn')->firstOrFail();
        DeliveryZone::query()->delete();

        $this->actingAs($admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Aucune zone de livraison active')
            ->assertSee('disabled', false);

        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(DeliveryZoneSeeder::class);
        $this->assertDatabaseCount('delivery_zones', 4);

        $response = $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertDontSee('Aucune zone de livraison active');

        foreach (DeliveryZone::all() as $zone) {
            $response->assertSee('<option value="'.$zone->id.'"', false)->assertSee($zone->name);
        }

        DeliveryZone::query()->update(['is_active' => false]);
        $this->seed(DeliveryZoneSeeder::class);
        $this->assertSame(0, DeliveryZone::where('is_active', true)->count());

        $this->get(route('admin.clients.index'))->assertSee('Aucune zone de livraison active');
    }
}