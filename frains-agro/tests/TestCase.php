<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? ($user->role?->name === 'CUSTOMER' ? 'web' : 'admin'));
    }
}
