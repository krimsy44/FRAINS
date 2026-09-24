<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', ['settings' => Setting::values(), 'fields' => config('frains.settings')]);
    }

    public function update(Request $request)
    {
        $rules = array_fill_keys(array_keys(config('frains.settings')), 'nullable|string|max:10000');
        $rules['email'] = 'nullable|email|max:255';
        foreach (['facebook_url', 'instagram_url', 'snapchat_url', 'tiktok_url'] as $socialField) {
            $rules[$socialField] = 'nullable|url:http,https|max:2048';
        }
        foreach (['objectives', 'history'] as $section) {
            $rules[$section.'_image_caption'] = 'nullable|string|max:255';
        }
        $data = $request->validate($rules);
        $request->validate(['logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', 'objectives_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', 'history_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096']);
        foreach (['objectives', 'history'] as $section) {
            if ($request->hasFile($section.'_image')) {
                $data[$section.'_image_path'] = $request->file($section.'_image')->store('media/gie', 'public');
            }
        }
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('media', 'public');
        }
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            } AuditLog::record('settings.updated', 'gie');
        });

        return back()->with('success', 'Paramètres enregistrés.');
    }

    public function users()
    {
        return view('admin.users', [
            'users' => User::whereHas('role', fn ($query) => $query->where('name', '!=', 'CUSTOMER'))
                ->with('role')->paginate(20),
            'roles' => Role::where('name', '!=', 'CUSTOMER')->get(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate(['first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'email' => 'required|email|unique:users|max:255', 'phone' => 'nullable|string|max:30', 'password' => 'required|string|min:12|confirmed', 'role_id' => ['required', Rule::exists('roles', 'id')->whereNot('name', 'CUSTOMER')]]);
        $user = User::create($data + ['status' => 'active']);
        AuditLog::record('user.created', 'user:'.$user->id);

        return back()->with('success', 'Compte créé.');
    }

    public function updateUser(Request $request, User $user)
    {
        abort_if($user->customer?->account_deleted_at, 410, 'Ce compte client a été supprimé.');
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:80',
            'last_name' => 'sometimes|required|string|max:80',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:active,inactive',
        ]);
        // Never detach a customer profile from its role, or attach an unprovisioned client role.
        $target = Role::findOrFail($data['role_id']);
        if (($target->name === 'CUSTOMER') !== ($user->role?->name === 'CUSTOMER')) {
            return back()->withErrors(['role_id' => 'Le rôle client ne peut pas être converti en compte interne.']);
        }
        if ($user->id === auth()->id() && ($user->role_id != $data['role_id'] || $user->status !== $data['status'])) {
            return back()->withErrors(['status' => 'Votre propre rôle et votre accès doivent être modifiés par un autre administrateur.']);
        }
        $user->update($data);
        AuditLog::record('user.updated', 'user:'.$user->id, $data);

        return back()->with('success','Informations de l’utilisateur mises à jour.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        if ($user->customer) {
            app(OperationsController::class)->destroyClient($user->customer);
        } else {
            DB::transaction(function () use ($user) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                AuditLog::record('user.deleted', 'user:'.$user->id);
                $user->delete();
            });
        }

        return to_route('admin.users.index')->with('success', 'Utilisateur supprimé. Les historiques métier sont conservés.');
    }
}
