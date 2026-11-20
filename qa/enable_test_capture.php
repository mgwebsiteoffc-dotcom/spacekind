<?php

// Browser-test setup only: never enable lead capture on a deployed installation.
if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'spacekind_test') {
    throw new RuntimeException('Requires the disposable test database.');
}
$row = App\Models\SiteSetting::find(1) ?? (new App\Models\SiteSetting)->forceFill(['id' => 1, 'seo' => []]);
$row->capture = array_replace(App\Services\ProjectCapture::DEFAULTS, ['entry' => true, 'exit' => true, 'floating' => true, 'delay' => 3]);
$row->save();
