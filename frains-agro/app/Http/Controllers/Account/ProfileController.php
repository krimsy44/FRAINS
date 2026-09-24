<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index(string $section = 'tableau-de-bord')
    {
        abort_unless(in_array($section, ['tableau-de-bord', 'profil', 'adresses', 'paiements', 'factures', 'livraisons']), 404);
        $customer = auth()->user()->customer;

        return view('account.overview', ['section' => $section, 'customer' => $customer, 'orders' => $customer->orders()->with(['receipts', 'delivery'])->latest()->paginate(20), 'addresses' => Address::where('customer_id', $customer->id)->get(), 'zones' => DeliveryZone::where('is_active', true)->get()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'phone' => 'required|string|max:30', 'address' => 'required|string|max:255', 'city' => 'required|string|max:80', 'company_name' => 'nullable|string|max:120', 'password' => 'nullable|string|min:12|confirmed', 'current_password' => 'required_with:password|nullable|current_password']);
        DB::transaction(function () use ($data) {
            $user = auth()->user();
            $user->fill(collect($data)->only(['first_name', 'last_name', 'phone', 'address', 'city'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->customer->update(['company_name' => $data['company_name'] ?? null]);
        });

        return back()->with('success', 'Profil mis à jour.');
    }

    public function address(Request $request)
    {
        $data = $request->validate(['label' => 'required|string|max:80', 'address' => 'required|string|max:500', 'city' => 'required|string|max:80', 'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')->where('is_active', true)]]);
        Address::create($data + ['customer_id' => auth()->user()->customer->id]);

        return back()->with('success', 'Adresse ajoutée.');
    }

    public function remove(Address $address)
    {
        abort_unless($address->customer_id === auth()->user()->customer->id, 403);
        $address->delete();

        return back()->with('success', 'Adresse retirée du carnet.');
    }
}
