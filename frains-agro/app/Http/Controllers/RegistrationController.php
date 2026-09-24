<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Role;
use App\Models\User;
use App\Services\PlatformNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('auth.register', ['zones' => DeliveryZone::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'], 'email' => ['required', 'email', 'unique:users'], 'phone' => ['required', 'string', 'max:30'], 'address' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:80'], 'delivery_zone_id' => ['required', 'exists:delivery_zones,id'], 'customer_type' => ['required', 'in:PARTICULIER,REVENDEUR,GROSSISTE,RESTAURANT,HOTEL,SUPERMARCHE,ENTREPRISE,DISTRIBUTEUR'], 'company_name' => ['nullable', 'string', 'max:120'], 'password' => ['required', 'confirmed', 'min:8']]);
        $user = DB::transaction(function () use ($data) {
            if (! DeliveryZone::whereKey($data['delivery_zone_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['delivery_zone_id' => 'Cette zone est indisponible.']);
            }
            $role = Role::firstOrCreate(['name' => 'CUSTOMER'], ['label' => 'Client']);
            $user = User::create([...collect($data)->only(['first_name', 'last_name', 'email', 'phone', 'address', 'city'])->all(), 'role_id' => $role->id, 'status' => 'active', 'password' => $data['password']]);
            Customer::create(['user_id' => $user->id, 'delivery_zone_id' => $data['delivery_zone_id'], 'customer_type' => $data['customer_type'], 'company_name' => $data['company_name'] ?? null, 'status' => 'active']);

            PlatformNotice::staff('Nouvelle inscription client : '.$user->name, route('admin.clients.index'));

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return to_route(session('cart') ? 'checkout.create' : 'account.orders.index');
    }
}
