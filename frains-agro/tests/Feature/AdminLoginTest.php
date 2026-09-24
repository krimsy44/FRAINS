<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_login_page_does_not_redirect_an_admin_to_dashboard(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail())
            ->get(route('login'))->assertOk()->assertSee('Connexion client')->assertSee('name="full_name"', false);
    }

    public function test_admin_login_is_separate_and_preserves_destination(): void
    {
        $this->seed();
        $this->get(route('admin.products.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertSee('Connexion administration')->assertDontSee('class="site-header"', false);
        $this->post(route('admin.login.store'), ['email' => 'admin@frains-agro.sn', 'password' => 'password'])->assertRedirect(route('admin.products.index'));
        $this->assertAuthenticated();
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->get(route('account.orders.index'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('class="site-header"', false);
    }

    public function test_admin_login_rejects_customer_inactive_and_invalid_credentials(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@frains-agro.sn')->firstOrFail();
        $this->from(route('admin.login'))->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $admin->update(['status' => 'inactive']);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $admin->update(['status' => 'active', 'role_id' => Role::where('name', 'CUSTOMER')->firstOrFail()->id]);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
