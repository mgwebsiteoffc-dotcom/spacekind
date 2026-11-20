<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function registerForm()
    {
        return view('auth.register');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attemptWhen($data + ['is_active' => true], fn (User $user) => ! $user->isRetiredAdmin(), $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These details do not match an active account.']);
        }
        $r->session()->regenerate();

        $user = $r->user();
        $home = $user->isAdmin() ? '/admin' : '/workspace';
        $intended = $r->session()->pull('url.intended');
        // Only return to a page in this account's own area on this installation.
        if (is_string($intended)) {
            $page = Str::before($intended, '?');
            $areas = $user->isAdmin() || $user->isOwner()
                ? [$home]
                : ['/workspace/enquiries', '/workspace/clients', '/workspace/projects', '/workspace/modules'];
            foreach ($areas as $area) {
                if ($page === url($area) || str_starts_with($page, url($area).'/')) {
                    return redirect()->to($intended);
                }
            }
        }

        return redirect($home);
    }

    public function register(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100', 'studio_name' => 'required|string|min:3|max:100', 'city' => ['required', Rule::in(config('spacekind.cities'))], 'email' => 'required|email:rfc|max:254|unique:users,email', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()], 'terms' => ['accepted']]);
        $user = DB::transaction(function () use ($data) {
            $slug = Str::slug($data['studio_name']) ?: 'studio';
            if (Business::where('slug', $slug)->exists()) {
                $slug .= '-'.Str::lower(Str::random(6));
            }
            $business = Business::create(['name' => $data['studio_name'], 'slug' => $slug, 'city' => $data['city'], 'initials' => mb_strtoupper(mb_substr($data['studio_name'], 0, 2))]);
            $user = new User(Arr::only($data, ['name', 'email', 'password']));
            $user->business_id = $business->id;
            $user->role = 'owner';
            $user->save();
            app(Tenant::class)->run($business, function () use ($business) {
                Subscription::create(['starts_at' => today(), 'status' => 'pilot']);
                Audit::record('studio.registered', $business);
            });

            return $user;
        });
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/workspace/studio')->with('start_studio_tour', true)->with('success', 'Your workspace is ready. Complete your profile and gallery; a platform admin must approve your public listing. No charge has been made.');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }
}
