<?php

use App\Models\BillingSetting;
use App\Models\Business;
use App\Models\Client;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\FinancialDocument;
use App\Models\PaymentAllocation;
use App\Models\Project;
use App\Services\FinancialPdf;
use App\Services\FinancialWorkflow;
use App\Support\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'spacekind_test') {
    throw new RuntimeException('Concurrency QA is restricted to the isolated spacekind_test MySQL database.');
}
$path = storage_path('framework/testing/finance-concurrency.json');
$mode = $argv[1] ?? '';
if ($mode === 'prepare') {
    if (DB::table('businesses')->exists() || is_file($path)) {
        throw new RuntimeException('Test database or fixture manifest occupied; refusing to overwrite.');
    }
    $b = Business::create(['name' => 'Synthetic Concurrency QA', 'slug' => 'synthetic-concurrency-qa', 'city' => 'Noida']);
    $case = app(Tenant::class)->run($b, function () use ($b) {
        $c = Client::create(['name' => 'Synthetic Concurrency Client', 'phone' => '+919000000000', 'locality' => 'Synthetic locality', 'source' => 'Isolated concurrency test']);
        $p = Project::create(['client_id' => $c->id, 'name' => 'Synthetic Concurrent Project']);
        BillingSetting::create(['data' => ['name' => 'Synthetic Test Issuer', 'address' => '8 Synthetic Street, Noida', 'state' => '09', 'gstin' => '09ABCDE1234F1Z5', 'tax_basis' => 'exclusive', 'quotation_prefix' => 'QTN', 'invoice_prefix' => 'INV', 'receipt_prefix' => 'RCT']]);
        $v = ['kind' => 'invoice', 'client_id' => $c->id, 'project_id' => $p->id, 'document_date' => today()->toDateString(), 'due_date' => today()->addDays(10)->toDateString(), 'customer' => ['name' => $c->name, 'address' => '8 Synthetic Client Address', 'state' => '09'], 'place_of_supply' => '09', 'items' => [['description' => 'Concurrent test material', 'category' => 'material', 'unit' => 'job', 'quantity' => '1', 'rate' => '1000', 'discount' => '0', 'tax_bps' => 1800]]];
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = app(FinancialWorkflow::class)->draft($v + ['draft_key' => (string) Str::uuid()])->id;
        }

return ['business_id' => $b->id, 'client_id' => $c->id, 'project_id' => $p->id, 'invoices' => $ids, 'payment_key' => (string) Str::uuid(), 'advance_key' => (string) Str::uuid(), 'allocation_key' => (string) Str::uuid()];
    });
    file_put_contents($path, json_encode($case, JSON_THROW_ON_ERROR));
    chmod($path, 0600);
    echo json_encode(['prepared' => true, 'invoice_jobs' => 6]);
    exit;
}
$case = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$b = Business::where('slug', 'synthetic-concurrency-qa')->findOrFail($case['business_id']);
app(Tenant::class)->run($b, function () use ($case, $mode, $argv, $b, $path) {
    $f = app(FinancialWorkflow::class);
    if ($mode === 'issue') {
        $d = FinancialDocument::findOrFail($case['invoices'][(int) $argv[2]]);
        echo $f->issue($d, 1)->number;
    } elseif ($mode === 'pay' || $mode === 'advance') {
        $p = $f->recordPayment(['submission_key' => $case[$mode === 'pay' ? 'payment_key' : 'advance_key'], 'client_id' => $case['client_id'], 'project_id' => $case['project_id'], 'invoice_id' => $mode === 'pay' ? $case['invoices'][0] : null, 'amount' => '100.00', 'paid_on' => today()->toDateString(), 'method' => 'bank', 'reference' => 'SYNTHETIC-CONCURRENCY-'.$mode, 'note' => 'Isolated concurrency QA, not funds.']);
        echo $p->id;
    } elseif ($mode === 'allocate') {
        $p = CustomerPayment::where('submission_key', $case['advance_key'])->firstOrFail();
        $f->allocate($p, ['submission_key' => $case['allocation_key'], 'invoice_id' => $case['invoices'][0], 'amount' => '50.00']);
        echo 'allocated';
    } elseif ($mode === 'verify') {
        $d = FinancialDocument::findOrFail($case['invoices'][0]);
        $result = ['invoice_count' => FinancialDocument::where('kind', 'invoice')->count(), 'invoice_numbers' => FinancialDocument::where('kind', 'invoice')->orderBy('number')->pluck('number')->all(), 'invoice_sequence' => DocumentSequence::where('kind', 'invoice')->value('last_number'), 'receipt_count' => FinancialDocument::where('kind', 'receipt')->count(), 'payment_count' => CustomerPayment::count(), 'allocation_count' => PaymentAllocation::count(), 'paid_minor' => $d->paid_minor, 'balance_minor' => $d->balance_minor, 'available_advance_minor' => CustomerPayment::where('submission_key', $case['advance_key'])->first()->available_minor];
        $items = [];
        for ($i = 1; $i <= 50; $i++) {
            $items[] = ['description' => 'Synthetic scope line '.str_pad($i, 2, '0', STR_PAD_LEFT).' — material, workmanship & a carefully described installation detail.', 'category' => 'material', 'unit' => 'sq ft', 'quantity' => '1.005', 'rate' => '118.00', 'discount' => '0.25', 'tax_bps' => 1800, 'hsn_sac' => '9983'];
        }$v = ['kind' => 'quotation', 'draft_key' => (string) Str::uuid(), 'client_id' => $case['client_id'], 'project_id' => $case['project_id'], 'document_date' => today()->toDateString(), 'due_date' => today()->addDays(15)->toDateString(), 'customer' => ['name' => 'Synthetic PDF Client', 'address' => '8 Fictional Client Street, Noida', 'state' => '09'], 'place_of_supply' => '09', 'items' => $items, 'terms' => str_repeat('Synthetic term, to test page layout and wrapping only. ', 60), 'notes' => 'FINAL SENTINEL: fifty lines retained. <script>not executable</script>'];
        $pdfdoc = $f->draft($v)->refresh();
        file_put_contents(base_path('qa/phase2-multipage.pdf'), app(FinancialPdf::class)->render($pdfdoc));
        echo json_encode($result, JSON_PRETTY_PRINT);
    } elseif ($mode === 'cleanup') {
        DB::transaction(function () use ($b) {
            foreach (['document_shares', 'payment_allocations', 'customer_payments'] as $t) {
                DB::table($t)->where('business_id', $b->id)->delete();
            }foreach (FinancialDocument::orderByDesc('id')->pluck('id') as $id) {
                DB::table('financial_documents')->where('business_id', $b->id)->where('id', $id)->delete();
            }foreach (['document_sequences', 'catalogue_items', 'billing_settings', 'projects', 'clients', 'audit_events'] as $t) {
                DB::table($t)->where('business_id', $b->id)->delete();
            }DB::table('businesses')->where('id', $b->id)->delete();
        });
        unlink($path);
        echo 'Exact isolated concurrency fixture removed';
    } else {
        throw new RuntimeException('Unknown QA operation.');
    }
});
