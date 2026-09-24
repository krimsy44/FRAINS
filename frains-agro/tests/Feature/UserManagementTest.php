<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Producer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::create([
            'first_name' => 'Test', 'last_name' => 'Compte', 'email' => uniqid().'@example.test',
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
            'password' => 'password123456', 'status' => 'active',
        ]);
    }

    public function test_admin_can_edit_details_and_delete_an_internal_user_preserving_producer(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@frains-agro.sn')->firstOrFail();
        $user = $this->account('PRODUCER');
        $producer = Producer::create(['user_id' => $user->id, 'name' => 'Producteur', 'phone' => '771234567', 'zone' => 'Diogo', 'joined_at' => today()]);
        $this->actingAs($admin, 'admin')->get(route('admin.users.index'))->assertOk()
            ->assertSee(route('admin.users.destroy', $user))->assertSee('name="first_name"', false);
        $data = ['first_name' => 'Awa', 'last_name' => 'Diop', 'email' => 'awa@example.test', 'phone' => '771234567', 'address' => 'Diogo', 'city' => 'Thies', 'role_id' => $user->role_id, 'status' => 'active'];
        $this->put(route('admin.users.update', $user), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Awa', 'email' => 'awa@example.test']);
        $this->put(route('admin.users.update', $user), array_replace($data, ['email' => $admin->email]))->assertSessionHasErrors('email');
        $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('producers', ['id' => $producer->id, 'user_id' => null]);
        $this->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_permissions_and_customer_history_protection(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@frains-agro.sn')->firstOrFail();
        $user = $this->account('CUSTOMER');
        $customer = Customer::create(['user_id' => $user->id, 'status' => 'active']);
        $this->actingAs($this->account('MANAGER'), 'admin');
        $this->put(route('admin.users.update', $user), [])->assertForbidden();
        $this->delete(route('admin.users.destroy', $user))->assertForbidden();
        $this->actingAs($admin, 'admin');
        $this->put(route('admin.users.update', $admin), ['role_id' => $admin->role_id, 'status' => 'inactive'])->assertSessionHasErrors('status');
        $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
        $this->assertNotNull($customer->fresh()->account_deleted_at);
        $this->assertSame('inactive', $user->fresh()->status);
        $this->get(route('admin.users.index'))->assertDontSee($user->email);
    }
}
