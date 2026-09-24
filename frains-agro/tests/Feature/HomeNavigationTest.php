<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_link_is_available_on_public_and_admin_pages(): void
    {
        $this->seed();
        foreach (['products.index', 'cart.index', 'login', 'quotes.start'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('<a href="'.route('home').'">Accueil</a>', false);
        }
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        foreach (['admin.dashboard', 'admin.products.index', 'admin.products.create', 'admin.notifications.index', 'admin.quotes.index'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('<a href="'.route('home').'">Accueil du site</a>', false);
        }
        $this->get(route('home'))->assertOk();
    }
}
