<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
class AuthController extends Controller
{
    public function create()
    {
        if (auth()->user()?->role?->name === 'CUSTOMER') {
            return to_route('account.section');
        }

        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $expectedName = Str::of($data['full_name'])->squish()->lower()->toString();
        $expectedPhone = preg_replace('/\D+/', '', $data['phone']);

        $customer = Customer::with(['user.role'])
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')->whereHas('role', fn ($role) => $role->where('name', 'CUSTOMER'));
            })
            ->get()
            ->first(function (Customer $customer) use ($expectedName, $expectedPhone) {
                $user = $customer->user;
                $names = array_filter([$customer->name, $user?->name]);
                $phones = array_filter([$customer->contact_phone, $user?->phone]);

                $nameMatches = collect($names)->contains(fn ($name) => Str::of($name)->squish()->lower()->toString() === $expectedName);
                $phoneMatches = collect($phones)->contains(fn ($phone) => preg_replace('/\D+/', '', $phone) === $expectedPhone);

                return $nameMatches && $phoneMatches;
            });

        if (! $customer?->user) {
            return back()
                ->withErrors(['full_name' => 'Aucun compte client actif ne correspond à ce nom complet et ce numéro de téléphone.'])
                ->onlyInput(['full_name', 'phone']);
        }

        $existingToken = Auth::guard('admin')->check() ? $request->session()->token() : null;
        Auth::guard('web')->login($customer->user);
        $request->session()->regenerate();
        if ($existingToken) {
            $request->session()->put('_token', $existingToken);
        }

        $intended = $request->session()->get('url.intended');
        $adminUrl = route('admin.dashboard');
        if (is_string($intended) && ($intended === $adminUrl || str_starts_with($intended, $adminUrl.'/') || str_starts_with($intended, $adminUrl.'?'))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route(session('cart') ? 'checkout.create' : 'account.orders.index'));
    }

    public function adminCreate()
    {
        return view('auth.admin-login');
    }

    public function adminStore(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $roles = ['ADMIN', 'MANAGER', 'COMMERCIAL', 'STOCK_MANAGER', 'DELIVERY_MANAGER', 'PRODUCER', 'DRIVER'];
        $existingToken = Auth::guard('web')->check() ? $request->session()->token() : null;
        if (! Auth::guard('admin')->attemptWhen([...$credentials, 'status' => 'active'], fn ($user) => in_array($user->role?->name, $roles, true), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Ces identifiants ne correspondent pas à un compte de gestion actif.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        if ($existingToken) {
            $request->session()->put('_token', $existingToken);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        $guard = $request->routeIs('admin.logout') ? 'admin' : 'web';
        Auth::guard($guard)->logout();
        $request->session()->forget(['auth.intended.'.$guard, 'url.intended']);
        // Preserve the other space's login and CSRF token in its open tabs.
        $request->session()->migrate(true);

        return to_route($request->routeIs('admin.logout') ? 'admin.login' : 'login');
    }
}
