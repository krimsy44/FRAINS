<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParcelProducerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_producer_accounts_are_available_and_linked_when_saving(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@frains-agro.sn')->firstOrFail();
        $account = User::create([
            'role_id' => \App\Models\Role::where('name', 'PRODUCER')->firstOrFail()->id,
            'first_name' => 'Compte', 'last_name' => 'Agricole', 'email' => 'parcel-account@example.test',
            'password' => 'password', 'phone' => '771234567', 'city' => 'Diogo', 'status' => 'active',
        ]);
        $this->actingAs($admin, 'admin')->get(route('admin.resources.create', 'parcels'))
            ->assertOk()->assertSee('value="user:'.$account->id.'"', false)->assertSee('Compte Agricole');
        $data = [
            'reference' => 'ACCOUNT-P001', 'producer_id' => 'user:'.$account->id,
            'location' => 'Diogo', 'area' => 2, 'status' => 'ACTIVE', 'started_at' => today()->toDateString(),
        ];
        $this->post(route('admin.resources.store', 'parcels'), array_replace($data, ['producer_id' => 'user:'.$admin->id]))
            ->assertSessionHasErrors('producer_id');
        $this->post(route('admin.resources.store', 'parcels'), $data)->assertSessionHasNoErrors();
        $producer = Producer::where('user_id', $account->id)->firstOrFail();
        $this->assertDatabaseHas('parcels', ['reference' => 'ACCOUNT-P001', 'producer_id' => $producer->id]);
        $this->get(route('admin.resources.create', 'parcels'))->assertOk()
            ->assertSee('Compte Agricole')->assertDontSee('value="user:'.$account->id.'"', false);
        $this->post(route('admin.resources.store', 'parcels'), array_replace($data, [
            'reference' => 'ACCOUNT-P002', 'producer_id' => $producer->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, Producer::where('user_id', $account->id)->count());
    }

    public function test_registered_producers_can_be_selected_without_an_inline_creation_form(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail(), 'admin');
        $producer = Producer::create([
            'name' => 'Producteur parcelle', 'phone' => '771234567', 'zone' => 'Diogo',
            'joined_at' => today()->toDateString(), 'status' => 'active',
        ]);
        $this->get(route('admin.resources.create', 'parcels'))->assertOk()
            ->assertSee('Choisir un producteur')->assertSee('Producteur parcelle')
            ->assertDontSee('parcel-producer-form')->assertDontSee('Ajouter un producteur');

        $this->post(route('admin.resources.store', 'parcels'), [
            'reference' => 'INLINE-P001', 'producer_id' => $producer->id,
            'location' => 'Diogo', 'area' => 2, 'status' => 'ACTIVE',
            'started_at' => today()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.resources.index', 'parcels'));
        $this->assertDatabaseHas('parcels', ['reference' => 'INLINE-P001', 'producer_id' => $producer->id]);
    }
}
