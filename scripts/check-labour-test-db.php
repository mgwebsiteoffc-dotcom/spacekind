<?php

// Read-only preflight for CI. Never migrate or empty a database to pass this check.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

if (! $app->environment('testing') || DB::getDefaultConnection() !== 'mysql' || DB::connection()->getDatabaseName() !== 'spacekind_test') {
    throw new RuntimeException('Use a new, isolated MySQL spacekind_test database with APP_ENV=testing.');
}
$server = DB::selectOne('SELECT VERSION() AS version, DATABASE() AS db, @@foreign_key_checks AS fk');
if (! str_starts_with($server->version, '8.') || $server->db !== 'spacekind_test' || (int) $server->fk !== 1) {
    throw new RuntimeException('Expected MySQL 8 on spacekind_test with foreign key checks enabled.');
}
if (DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()')->n != 0) {
    throw new RuntimeException('Refusing an occupied database. Create a fresh disposable instance.');
}
printf("Verified empty %s on MySQL %s (FK checks enabled).\n", $server->db, $server->version);
