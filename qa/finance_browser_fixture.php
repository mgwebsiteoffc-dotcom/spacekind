<?php

use App\Models\Business;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\PreviewState;
use App\Support\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || ! config('spacekind.demo') || DB::connection()->getDatabaseName() !== 'spacekind') {
    throw new RuntimeException('Browser fixtures are restricted to this local Spacekind demo.');
}
$path = storage_path('framework/testing/finance-browser.json');
$mode = $argv[1] ?? '';
if ($mode === 'prepare') {
    if (is_file($path) || Business::where('slug', 'like', 'qa-phase2-finance-%')->exists()) {
        throw new RuntimeException('Named QA fixture already exists. Clean up the exact prior fixture, do not overwrite.');
    }
    if (DB::table('platform_billing_settings')->exists() || DB::table('platform_invoices')->exists()) {
        throw new RuntimeException('This QA run requires untouched demo platform billing; refusing to alter existing records.');
    }
    $users = [];
    $businesses = [];
    $password = 'Synthetic-Phase2-Only-2026!';
    $project = null;
    $client = null;
    foreach (['owner', 'other', 'admin'] as $role) {
        $b = $role === 'admin' ? null : Business::create(['name' => $role === 'owner' ? 'QA Finance Studio' : 'QA Other Finance Studio', 'slug' => 'qa-phase2-finance-'.$role, 'city' => 'Noida', 'locality' => 'Synthetic area']);
        if ($b) {
            $businesses[] = $b->id;
            [$c,$p] = app(Tenant::class)->run($b, function () {
                $c = Client::create(['name' => 'QA Finance Client', 'phone' => '+919000000000', 'email' => 'qa-finance@example.test', 'locality' => 'Synthetic Sector, Noida', 'source' => 'Synthetic browser QA']);
                $p = Project::create(['client_id' => $c->id, 'name' => 'QA Finance Project']);

                return [$c, $p];
            });
            if ($role === 'owner') {
                $client = $c->id;
                $project = $p->id;
            }
        }
        $u = new User(['name' => 'QA Finance '.ucfirst($role), 'email' => 'qa-phase2-finance-'.$role.'@example.test', 'password' => Hash::make($password)]);
        $u->forceFill(['business_id' => $b?->id, 'role' => $role === 'admin' ? 'super_admin' : 'owner', 'is_active' => true])->save();
        $users[$role] = ['id' => $u->id, 'email' => $u->email];
    }
    $case = ['business_ids' => $businesses, 'owner_business_id' => $businesses[0], 'users' => $users, 'password' => $password, 'client_id' => $client, 'project_id' => $project];
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }file_put_contents($path, json_encode($case, JSON_THROW_ON_ERROR));
    chmod($path, 0600);
    echo json_encode(['prepared' => true]);
    exit;
}
$case = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
if ($mode !== 'cleanup') {
    throw new RuntimeException('Unknown operation.');
}
foreach ($case['business_ids'] as $id) {
    if (! Business::where('slug', 'like', 'qa-phase2-finance-%')->whereKey($id)->exists()) {
        throw new RuntimeException('Fixture identity mismatch; cleanup refused.');
    }
}
$logos = DB::table('billing_settings')->whereIn('business_id', $case['business_ids'])->pluck('logo_path')->filter()->all();
DB::transaction(function () use ($case) {
    $ids = $case['business_ids'];
    $users = array_column($case['users'], 'id');
    $pi = DB::table('platform_invoices')->whereIn('business_id', $ids)->pluck('id');
    DB::table('platform_payments')->whereIn('platform_invoice_id', $pi)->delete();
    DB::table('platform_invoices')->whereIn('id', $pi)->delete();
    if (! DB::table('platform_invoices')->exists()) {
        DB::table('platform_sequences')->delete();
        DB::table('platform_billing_settings')->where('data->name', 'QA Spacekind Platform')->delete();
    }
    foreach (['document_shares', 'payment_allocations', 'customer_payments'] as $t) {
        DB::table($t)->whereIn('business_id', $ids)->delete();
    }foreach (DB::table('financial_documents')->whereIn('business_id', $ids)->orderByDesc('id')->pluck('id') as $id) {
        DB::table('financial_documents')->where('id', $id)->delete();
    }foreach (['document_sequences', 'catalogue_items', 'billing_settings', 'projects', 'clients'] as $t) {
        DB::table($t)->whereIn('business_id', $ids)->delete();
    }DB::table('audit_events')->whereIn('business_id', $ids)->orWhereIn('user_id', $users)->delete();
    DB::table('personal_access_tokens')->whereIn('tokenable_id', $users)->where('tokenable_type', User::class)->delete();
    DB::table('sessions')->whereIn('user_id', $users)->delete();
    DB::table('users')->whereIn('id', $users)->where('email', 'like', 'qa-phase2-finance-%@example.test')->delete();
    DB::table('businesses')->whereIn('id', $ids)->delete();
});
foreach ($logos as $pathToLogo) {
    if (preg_match('#^billing-logos/('.implode('|', $case['business_ids']).')/#', $pathToLogo)) {
        Storage::disk('local')->delete($pathToLogo);
    }
}
unlink($path);
app(PreviewState::class)->save();
echo 'Exact named Phase 2 browser fixtures and test logo removed.';
