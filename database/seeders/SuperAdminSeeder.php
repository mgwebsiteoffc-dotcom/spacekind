<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use RuntimeException;

/** Explicit one-time bootstrap. Never called by DatabaseSeeder. */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) Env::get('SPACEKIND_ADMIN_EMAIL')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $email === 'admin@spacekind.example') {
            throw new RuntimeException('Set SPACEKIND_ADMIN_EMAIL to a valid private address, not the retired shared account.');
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            if (! $existing->isAdmin() || ! $existing->is_active || $existing->business_id !== null) {
                throw new RuntimeException('This email is already assigned. No account was promoted or reactivated.');
            }
            $this->command?->info('Private administrator already exists. Password unchanged.');

            return;
        }

        $password = Env::get('SPACEKIND_ADMIN_PASSWORD');
        if (! is_string($password) || strlen(trim($password)) < 14 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Set SPACEKIND_ADMIN_PASSWORD to a private password of 14–72 bytes.');
        }
        $user = new User(['name' => 'Studio administrator', 'email' => $email, 'password' => $password]);
        $user->forceFill(['role' => 'super_admin', 'business_id' => null, 'is_active' => true])->save();
        $this->command?->info('Private administrator created. Sign in at /login. Remove the temporary provisioning secrets.');
    }
}
