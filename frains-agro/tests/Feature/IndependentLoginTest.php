<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class IndependentLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_accounts_can_login_and_logout_independently(): void
    {
        $this->seed();
        $client = User::create(['first_name' => 'Client', 'last_name' => 'Independant', 'email' => 'independent@example.test', 'phone' => '770001122', 'password' => 'password123', 'status' => 'active', 'role_id' => Role::where('name', 'CUSTOMER')->firstOrFail()->id]);
        Customer::create(['user_id' => $client->id, 'status' => 'active']);

        $this->get(route('admin.products.index'))->assertRedirect(route('admin.login'))->assertSessionHas('auth.intended.admin', route('admin.products.index'));
        $this->get(route('account.orders.index'))->assertRedirect(route('login'));
        $this->post(route('admin.login.store'), ['email' => 'admin@frains-agro.sn', 'password' => 'password'])
            ->assertRedirect(route('admin.products.index'));
        $this->get(route('login'))->assertOk()->assertSee('name="full_name"', false);
        $adminToken = session()->token();
        $this->post(route('login.store'), ['full_name' => $client->name, 'phone' => $client->phone])
            ->assertRedirect(route('account.orders.index'));
        $this->assertAuthenticated('admin');
        $this->assertAuthenticatedAs($client, 'web');
        $this->assertSame($adminToken, session()->token());

        // Reload identities from the session, as separate browser requests do.
        Auth::forgetGuards();
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('account.orders.index'))->assertOk();
        $this->get(route('admin.quotes.index'))->assertOk();
        $this->get(route('quotes.index'))->assertOk();
        $this->get(route('admin.notifications.index'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        Auth::forgetGuards();
        $this->get(route('account.orders.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk();
        $clientToken = session()->token();
        $this->post(route('admin.login.store'), ['email' => 'admin@frains-agro.sn', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame($clientToken, session()->token());
        $this->post(route('logout'))->assertRedirect(route('login'));
        Auth::forgetGuards();
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('account.orders.index'))->assertRedirect(route('login'));
    }
}
