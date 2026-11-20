<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** One-time, explicitly invoked installer; never part of the default seed. */
class SaniSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'sani9431@gmail.com';
        $existing = User::where('email', $email)->first();
        if ($existing) {
            if (! $existing->isAdmin() || ! $existing->is_active || $existing->business_id !== null) {
                throw new RuntimeException('This email belongs to another or disabled account. No account was changed.');
            }
            $this->command?->info('Super admin already exists. Password unchanged.');

            return;
        }

        $password = $this->command?->secret('Super admin password (10–72 bytes)', false);
        if (! is_string($password) || strlen(trim($password)) < 10 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Enter a password of 10–72 bytes. No account was created.');
        }
        if ($password !== $this->command->secret('Confirm super admin password', false)) {
            throw new RuntimeException('Passwords did not match. No account was created.');
        }

        $admin = new User(['name' => 'Sani', 'email' => $email, 'password' => Hash::make($password)]);
        $admin->forceFill(['role' => 'super_admin', 'business_id' => null, 'is_active' => true])->save();
        $this->command->info('Super admin created. Sign in at /login with '.$email.'.');
    }
}
