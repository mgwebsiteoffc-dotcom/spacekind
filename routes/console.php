<?php

use App\Services\PreviewState;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('spacekind:snapshot', function (PreviewState $state) {
    if (! $state->enabled()) {
        $this->error('Local demo mode is required.');

        return 1;
    }
    $state->save();
    $this->info('Private development recovery state saved. This is not a production backup system.');
})->purpose('Save local demo MySQL records for workspace recovery');
Artisan::command('spacekind:restore-preview', function (PreviewState $state) {
    try {
        $count = $state->restore();
        $this->info("Restored {$count} rows into empty local MySQL application tables. Sign in again; device tokens and sessions are not restored.");
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }
})->purpose('Recover the local development state into an empty migrated MySQL database');

Artisan::command('spacekind:create-admin {email}', function () {
    $email = strtolower(trim($this->argument('email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $email === 'admin@spacekind.example' || \App\Models\User::where('email', $email)->exists()) {
        $this->error('Choose a new, private administrator email. Existing accounts cannot be promoted by this command.');

        return 1;
    }
    $password = $this->secret('New password (at least 14 characters)', false);
    if (! is_string($password) || strlen($password) < 14 || strlen($password) > 72 || $password !== $this->secret('Confirm new password', false)) {
        $this->error('Passwords must match and be 14–72 bytes. No account created.');

        return 1;
    }
    $user = new \App\Models\User(['name' => 'Spacekind editorial', 'email' => $email, 'password' => $password]);
    $user->forceFill(['role' => 'super_admin', 'is_active' => true])->save();
    $this->info('Administrator created. Sign in using the normal password form.');

    return 0;
})->purpose('Provision a private administrator from a trusted server terminal');
