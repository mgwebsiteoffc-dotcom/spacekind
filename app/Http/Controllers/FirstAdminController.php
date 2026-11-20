<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FirstAdminController extends Controller
{
    private const SESSION = 'first_admin_challenge';

    private function directory(): string
    {
        return storage_path('framework');
    }

    private function used(): string
    {
        return $this->directory().'/first-admin.completed';
    }

    private function hasAdmin(): bool
    {
        return User::where('role', 'super_admin')->where('email', '!=', 'admin@spacekind.example')->exists();
    }

    private function checkAvailable(Request $request): void
    {
        abort_unless($request->isSecure(), 403, 'Open this page using HTTPS.');
        abort_if(config('spacekind.demo') || file_exists($this->used()) || $this->hasAdmin(), 404);
    }

    public function show(Request $request)
    {
        $this->checkAvailable($request);
        $challenge = $request->session()->get(self::SESSION);
        if (! $challenge || $challenge['expires'] <= now()->timestamp) {
            $challenge = ['file' => 'approve-admin-'.Str::random(32).'.txt', 'expires' => now()->addMinutes(30)->timestamp];
            $request->session()->put(self::SESSION, $challenge);
        }

        return response()->view('auth.first-admin', ['filename' => $challenge['file']]);
    }

    public function store(Request $request)
    {
        $this->checkAvailable($request);
        $challenge = $request->session()->get(self::SESSION);
        if (! $challenge || $challenge['expires'] <= now()->timestamp || ! is_file($proof = $this->directory().'/'.$challenge['file'])) {
            return redirect('/admin/setup')->withErrors(['approval' => 'Create the file shown on this page in storage/framework, then try again in this same browser.']);
        }
        $data = $request->validate(['email' => 'required|email:rfc|max:254', 'password' => 'required|string|min:14|max:72|confirmed']);
        $email = strtolower($data['email']);
        if ($email === 'admin@spacekind.example' || User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Choose an unused, private administrator email. No existing account can be changed here.']);
        }
        if (strlen($data['password']) > 72 || str_contains($data['password'], "\0")) {
            throw ValidationException::withMessages(['password' => 'Use a password no longer than 72 bytes with no null characters.']);
        }
        // Exclusive creation serializes independent, approved browsers on this installation.
        $lock = @fopen($this->used(), 'x');
        abort_unless($lock, 409, 'Setup is locked. If this is a new installation, check permissions on storage/framework.');
        fclose($lock);
        try {
            DB::transaction(function () use ($data, $email) {
                abort_if($this->hasAdmin(), 404);
                $admin = new User(['name' => 'Platform administrator', 'email' => $email, 'password' => Hash::make($data['password'])]);
                $admin->forceFill(['role' => 'super_admin', 'business_id' => null, 'is_active' => true])->save();
            });
        } catch (\Throwable $e) {
            unlink($this->used());
            throw $e;
        }
        @unlink($proof);
        $request->session()->forget(self::SESSION);

        return redirect('/login')->with('success', 'Super admin created. Sign in with the email and password you just set.');
    }
}
