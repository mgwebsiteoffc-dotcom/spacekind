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
