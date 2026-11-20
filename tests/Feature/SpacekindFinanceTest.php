<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Models\Client;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\DocumentShare;
use App\Models\FinancialDocument;
use App\Models\PaymentAllocation;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentCalculator;
use App\Services\FinancialPdf;
use App\Services\FinancialWorkflow;
use App\Services\PlatformBilling;
use App\Services\PreviewState;
use App\Support\Money;
use App\Support\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Isolated MySQL database only; never run the suite against a deployed database. */
class SpacekindFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function studio(string $name = 'Financial Test Studio'): array
    {
        $b = Business::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(5), 'city' => 'Noida']);
        $u = User::factory()->create();
        $u->forceFill(['business_id' => $b->id, 'role' => 'owner', 'is_active' => true])->save();
        [$c,$p] = $this->within($b, function () {
            $c = Client::create(['name' => 'Synthetic Billing Client', 'phone' => '+919000000000', 'locality' => 'Sector 104, Noida', 'source' => 'Synthetic finance test']);
            $p = Project::create(['client_id' => $c->id, 'name' => 'Synthetic residential project']);
            BillingSetting::create(['data' => $this->seller()]);

            return [$c, $p];
        });

        return [$b, $u, $c, $p];
    }

    private function within(Business $b, callable $f): mixed
    {
        return app(Tenant::class)->run($b, $f);
    }

    private function seller(string $basis = 'exclusive', string $state = '09'): array
    {
        return ['name' => 'Synthetic Interior Studio', 'address' => 'Suite 8, Synthetic Street, Noida', 'state' => $state, 'gstin' => $basis === 'none' ? null : $state.'ABCDE1234F1Z5', 'phone' => '+919000000000', 'email' => 'billing@example.test', 'tax_basis' => $basis, 'quotation_prefix' => 'QTN', 'invoice_prefix' => 'INV', 'receipt_prefix' => 'RCT', 'terms' => 'Synthetic test terms. Not a commercial document.', 'bank_details' => 'Synthetic bank reference only.'];
    }

    private function line(array $extra = []): array
    {
        return array_replace(['description' => 'Synthetic material & workmanship', 'category' => 'material', 'unit' => 'sq ft', 'quantity' => '2.500', 'rate' => '1000.00', 'discount' => '100.00', 'tax_bps' => 1800, 'hsn_sac' => '9983'], $extra);
    }

    private function payload(Client $c, Project $p, string $kind = 'invoice'): array
    {
        return ['kind' => $kind, 'draft_key' => (string) Str::uuid(), 'client_id' => $c->id, 'project_id' => $p->id, 'document_date' => today()->toDateString(), 'due_date' => today()->addDays(15)->toDateString(), 'customer' => ['name' => $c->name, 'address' => 'Apartment 1, Synthetic Site, Noida', 'state' => '09', 'phone' => $c->phone], 'place_of_supply' => '09', 'items' => [$this->line()], 'terms' => 'Test milestone: review the work before payment.', 'notes' => 'Synthetic notes only.'];
    }

    private function issued(Business $b, Client $c, Project $p, string $kind = 'invoice', array $extra = []): FinancialDocument
    {
        return $this->within($b, function () use ($c, $p, $kind, $extra) {
            $f = app(FinancialWorkflow::class);
            $d = $f->draft(array_replace($this->payload($c, $p, $kind), $extra))->refresh();

            return $f->issue($d, $d->lock_version)->refresh();
        });
    }

    private function payment(Client $c, ?Project $p = null, ?FinancialDocument $d = null, string $amount = '1000.00'): array
    {
        return ['submission_key' => (string) Str::uuid(), 'client_id' => $c->id, 'project_id' => $p?->id, 'invoice_id' => $d?->id, 'amount' => $amount, 'paid_on' => today()->toDateString(), 'method' => 'upi', 'reference' => 'SYNTHETIC-REF-001', 'note' => 'Synthetic payment, not money.'];
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'super_admin', 'is_active' => true])->save();

        return $u;
    }

    private function invalid(callable $f, string $message = ''): void
    {
        try {
            $f();
            $this->fail('Expected a controlled validation failure.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
            if ($message) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function test_decimal_scaling_financial_years_and_non_decimal_rejection(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('spacekind_test', DB::connection()->getDatabaseName());
        $this->assertSame(12505, Money::scaled('125.05'));
        $this->assertSame(1005, Money::scaled('1.005', 3));
        $this->assertSame('2025-26', Money::year('2026-03-31'));
        $this->assertSame('2026-27', Money::year('2026-04-01'));
        foreach (['1e3', '-10', '1,000', 'NaN', '1.001', [], null] as $bad) {
            $this->invalid(fn () => Money::scaled($bad));
        }
    }

    public function test_exact_exclusive_and_inclusive_tax_splits_and_rounding(): void
    {
        $calc = app(DocumentCalculator::class);
        $a = $calc->calculate([$this->line()], $this->seller(), '09');
        foreach (['subtotal_minor' => 250000, 'discount_minor' => 10000, 'taxable_minor' => 240000, 'cgst_minor' => 21600, 'sgst_minor' => 21600, 'igst_minor' => 0, 'total_minor' => 283200] as $k => $n) {
            $this->assertSame($n, $a[$k], $k);
        }
        $b = $calc->calculate([$this->line()], $this->seller(), '07');
        $this->assertSame(43200, $b['igst_minor']);
        $this->assertSame(0, $b['cgst_minor']);
        $this->assertSame(283200, $b['total_minor']);
        $c = $calc->calculate([$this->line(['quantity' => '1', 'rate' => '118', 'discount' => '0'])], $this->seller('inclusive'), '09');
        $this->assertSame(10000, $c['taxable_minor']);
        $this->assertSame(900, $c['cgst_minor']);
        $this->assertSame(11800, $c['total_minor']);
        $odd = $calc->calculate([$this->line(['quantity' => '1', 'rate' => '0.04', 'discount' => '0'])], $this->seller('inclusive'), '09');
        $this->assertSame(0, $odd['cgst_minor']);
        $this->assertSame(1, $odd['sgst_minor']);
        $this->assertSame(4, $odd['total_minor']);
        $round = $calc->calculate([$this->line(['quantity' => '1', 'rate' => '0.06', 'discount' => '0'])], $this->seller(), '09');
        $this->assertSame(8, $round['total_minor']);
        $qty = $calc->calculate([$this->line(['quantity' => '1.005', 'rate' => '0.50', 'discount' => '0', 'tax_bps' => 0])], $this->seller('none'), '09');
        $this->assertSame(50, $qty['total_minor']);
        $this->invalid(fn () => $calc->calculate([$this->line()], $this->seller('none'), '09'));
        $this->invalid(fn () => $calc->calculate([$this->line(['discount' => '2501'])], $this->seller(), '09'));
        $this->invalid(fn () => $calc->calculate([$this->line(['quantity' => '0'])], $this->seller(), '09'));
        $this->invalid(fn () => $calc->calculate([$this->line(['tax_bps' => 1700])], $this->seller(), '09'));
    }

    public function test_owner_registers_editors_admin_platform_and_project_views_render(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p);
        $this->actingAs($u);
        foreach (['/workspace/finance', '/workspace/finance?kind=quotation', '/workspace/finance?kind=receipt', '/workspace/finance/settings', '/workspace/finance/catalogue', '/workspace/finance/payments', '/workspace/finance/documents/new?kind=quotation&project_id='.$p->id, '/workspace/finance/documents/'.$d->id, '/workspace/billing', '/workspace/projects/'.$p->id] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs($this->admin())->get('/admin/billing')->assertOk();
    }

    public function test_catalogue_is_reusable_tenant_scoped_and_not_retroactive(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        [$other,$v] = $this->studio('Other Catalogue');
        $d = $this->issued($b, $c, $p);
        $item = ['name' => 'Synthetic wall finish', 'category' => 'material', 'unit' => 'sq ft', 'rate' => '99.50', 'tax_bps' => 1800, 'hsn_sac' => '9983', 'is_active' => 1];
        $this->actingAs($u)->post('/workspace/finance/catalogue', $item)->assertRedirect();
        $id = DB::table('catalogue_items')->value('id');
        $this->actingAs($v)->post('/workspace/finance/catalogue', $item + ['id' => $id])->assertNotFound();
        $this->actingAs($u)->post('/workspace/finance/catalogue', array_replace($item, ['id' => $id, 'rate' => '120.00', 'is_active' => 0]))->assertRedirect();
        $this->within($b, function () use ($d) {
            $this->assertSame(283200, $d->fresh()->total_minor);
            $this->assertSame(12000, CatalogueItem::first()->rate_minor);
        });
    }

    public function test_draft_validation_and_optimistic_edit_protection(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $v = $this->payload($c, $p);
        $this->actingAs($u)->post('/workspace/finance/documents', $v)->assertRedirect();
        $id = DB::table('financial_documents')->value('id');
        $this->post('/workspace/finance/documents', $v)->assertRedirect();
        $this->assertDatabaseCount('financial_documents', 1);
        $url = '/workspace/finance/documents/'.$id;
        $this->patch($url, array_replace($v, ['lock_version' => 1, 'notes' => 'Changed draft']))->assertRedirect();
        $this->patchJson($url, array_replace($v, ['lock_version' => 1, 'notes' => 'Stale tab']))->assertUnprocessable();
        $this->assertDatabaseHas('financial_documents', ['id' => $id, 'lock_version' => 2, 'notes' => 'Changed draft']);
        $this->postJson('/workspace/finance/documents', array_replace($v, ['draft_key' => (string) Str::uuid(), 'document_date' => 'next Tuesday']))->assertUnprocessable();
        $this->postJson('/workspace/finance/documents', array_replace($v, ['draft_key' => (string) Str::uuid(), 'items' => [$this->line(['rate' => '1e4'])]]))->assertUnprocessable();
        $this->postJson('/workspace/finance/documents', array_replace($v, ['draft_key' => (string) Str::uuid(), 'items' => array_fill(0, 51, $this->line())]))->assertUnprocessable();
    }

    public function test_issue_assigns_unique_yearly_numbers_only_once_and_tenants_can_use_same_series(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $a = $this->issued($b, $c, $p);
        $b2 = $this->issued($b, $c, $p);
        $this->assertStringEndsWith('/00001', $a->number);
        $this->assertStringEndsWith('/00002', $b2->number);
        $this->within($b, function () use ($a) {
            $this->assertSame($a->id, app(FinancialWorkflow::class)->issue($a, 1)->id);
            $this->assertSame(2, DocumentSequence::where('kind', 'invoice')->value('last_number'));
        });
        [$o,$v,$oc,$op] = $this->studio('Separate Invoice Series');
        $od = $this->issued($o, $oc, $op);
        $this->assertSame($a->number, $od->number);
        $old = $this->issued($b, $c, $p, 'invoice', ['document_date' => '2025-03-31', 'due_date' => '2025-04-30']);
        $this->assertSame('INV/2425/00001', $old->number);
    }

    public function test_sequence_exhaustion_is_controlled_and_does_not_issue_or_reuse_a_number(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $this->within($b, function () use ($c, $p) {
            DocumentSequence::create(['kind' => 'invoice', 'financial_year' => Money::year(today()->toDateString()), 'last_number' => 99999]);
            $d = app(FinancialWorkflow::class)->draft($this->payload($c, $p))->refresh();
            $this->invalid(fn () => app(FinancialWorkflow::class)->issue($d, 1));
            $this->assertSame('draft', $d->fresh()->status);
            $this->assertNull($d->fresh()->number);
        });
    }

    public function test_issued_snapshot_is_frozen_with_model_and_http_edit_delete_guards(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p);
        $snapshot = $d->snapshot;
        $this->within($b, function () use ($d, $c) {
            $this->invalid(fn () => $d->update(['total_minor' => 1]));
            $this->invalid(fn () => $d->delete());
            $s = BillingSetting::first();
            $data = $s->data;
            $data['name'] = 'Changed seller';
            $s->update(['data' => $data]);
            $c->update(['name' => 'Changed CRM client']);
        });
        $fresh = $this->within($b, fn () => FinancialDocument::findOrFail($d->id));
        $this->assertSame($snapshot, $fresh->snapshot);
        $this->actingAs($u)->patchJson('/workspace/finance/documents/'.$d->id, $this->payload($c, $p) + ['lock_version' => 1])->assertUnprocessable();
        $this->get('/workspace/finance/documents/'.$d->id.'/edit')->assertNotFound();
    }

    public function test_changed_tax_setup_forces_draft_review_before_issuing_and_used_prefix_cannot_change(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $this->within($b, function () use ($c, $p) {
            $f = app(FinancialWorkflow::class);
            $d = $f->draft($this->payload($c, $p))->refresh();
            $s = BillingSetting::first();
            $data = $s->data;
            $data['tax_basis'] = 'inclusive';
            $s->update(['data' => $data]);
            $this->invalid(fn () => $f->issue($d, 1));
            $v = $this->payload($c, $p);
            $v['lock_version'] = 1;
            $d = $f->draft($v, $d);
            $f->issue($d, $d->lock_version);
        });
        $this->actingAs($u)->patchJson('/workspace/finance/settings', array_replace($this->seller('inclusive'), ['invoice_prefix' => 'BILL', 'reviewed' => 1]))->assertUnprocessable()->assertJsonValidationErrors('invoice_prefix');
        $this->patchJson('/workspace/finance/settings', array_replace($this->seller(), ['gstin' => '07ABCDE1234F1Z5', 'reviewed' => 1]))->assertUnprocessable();
    }

    public function test_quotation_sent_accepted_conversion_retry_and_revision_preserve_original(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $q = $this->issued($b, $c, $p, 'quotation');
        $url = '/workspace/finance/documents/'.$q->id;
        $this->actingAs($u)->post($url.'/sent', ['note' => 'Synthetic PDF handed to client'])->assertRedirect();
        $this->post($url.'/decision', ['decision' => 'accepted', 'note' => 'Client accepted test scope by phone'])->assertRedirect();
        $this->post($url.'/convert')->assertRedirect();
        $this->post($url.'/convert')->assertRedirect();
        $this->assertDatabaseCount('financial_documents', 2);
        $this->assertDatabaseHas('financial_documents', ['source_quote_id' => $q->id, 'kind' => 'invoice', 'status' => 'draft', 'number' => null]);
        $this->postJson($url.'/decision', ['decision' => 'rejected', 'note' => 'Cannot change linked acceptance'])->assertUnprocessable();
        $this->post($url.'/copy')->assertRedirect();
        $this->assertDatabaseCount('financial_documents', 3);
        $this->within($b, function () use ($q) {
            $fresh = $q->fresh();
            $this->assertSame('accepted', $fresh->display_status);
            $this->assertNotNull($fresh->sent_at);
            $this->assertNotNull($fresh->snapshot);
            $this->assertSame($q->items, $fresh->items);
        });
    }

    public function test_expired_and_rejected_quotes_cannot_convert(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $q = $this->issued($b, $c, $p, 'quotation', ['document_date' => today()->subDays(2)->toDateString(), 'due_date' => today()->subDay()->toDateString()]);
        $this->actingAs($u)->postJson('/workspace/finance/documents/'.$q->id.'/decision', ['decision' => 'accepted', 'note' => 'Late synthetic approval'])->assertUnprocessable();
        $this->postJson('/workspace/finance/documents/'.$q->id.'/convert')->assertUnprocessable();
        $q2 = $this->issued($b, $c, $p, 'quotation');
        $this->post('/workspace/finance/documents/'.$q2->id.'/decision', ['decision' => 'rejected', 'note' => 'Client did not accept test scope'])->assertRedirect();
        $this->postJson('/workspace/finance/documents/'.$q2->id.'/convert')->assertUnprocessable();
        $this->get('/workspace/finance?kind=quotation&status=expired')->assertSee($q->number)->assertDontSee($q2->number);
    }

    public function test_payment_retry_creates_exactly_one_receipt_and_allocation_and_rejects_changed_payload(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p);
        $v = $this->payment($c, null, $d);
        $this->actingAs($u)->post('/workspace/finance/payments', $v)->assertRedirect();
        $this->post('/workspace/finance/payments', $v)->assertRedirect();
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseCount('financial_documents', 2);
        $this->assertDatabaseHas('customer_payments', ['project_id' => $p->id]);
        $this->postJson('/workspace/finance/payments', array_replace($v, ['reference' => 'DIFFERENT-REFERENCE']))->assertUnprocessable();
        $this->postJson('/workspace/finance/payments', array_replace($v, ['invoice_id' => null]))->assertUnprocessable();
        $this->within($b, function () use ($d) {
            $d = $d->fresh();
            $this->assertSame(100000, $d->paid_minor);
            $this->assertSame(183200, $d->balance_minor);
            $this->assertSame('part-paid', $d->display_status);
            $this->assertStringStartsWith('RCT/', CustomerPayment::first()->receipt->number);
        });
    }

    public function test_partial_full_overdue_and_overpayment_balances_are_derived(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p, 'invoice', ['due_date' => today()->subDay()->toDateString(), 'document_date' => today()->subDays(2)->toDateString()]);
        $this->within($b, function () use ($c, $p, $d) {
            $f = app(FinancialWorkflow::class);
            $this->assertSame('overdue', $d->display_status);
            $this->invalid(fn () => $f->recordPayment($this->payment($c, $p, $d, '2832.01')));
            $f->recordPayment($this->payment($c, $p, $d, '1000.00'));
            $f->recordPayment($this->payment($c, $p, $d, '1832.00'));
            $fresh = $d->fresh();
            $this->assertSame('paid', $fresh->display_status);
            $this->assertSame(0, $fresh->balance_minor);
            $this->invalid(fn () => $f->recordPayment($this->payment($c, $p, $d, '0.01')));
        });
    }

    public function test_advances_allocations_retry_and_reversal_keep_original_receipt(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p);
        $this->within($b, function () use ($c, $p, $d) {
            $f = app(FinancialWorkflow::class);
            $v = $this->payment($c, $p, null, '1500');
            $pay = $f->recordPayment($v);
            $receipt = $pay->receipt->snapshot;
            $a = ['submission_key' => (string) Str::uuid(), 'invoice_id' => $d->id, 'amount' => '1000'];
            $f->allocate($pay, $a);
            $f->allocate($pay, $a);
            $this->assertSame(1, PaymentAllocation::count());
            $this->assertSame(50000, $pay->fresh()->available_minor);
            $this->assertSame($receipt, $pay->receipt->fresh()->snapshot);
            $this->invalid(fn () => $f->allocate($pay, array_replace($a, ['amount' => '500'])));
            $this->invalid(fn () => $f->allocate($pay, ['submission_key' => (string) Str::uuid(), 'invoice_id' => $d->id, 'amount' => '501']));
            $f->reverse($pay, 'Synthetic correction of duplicate offline reference');
            $f->reverse($pay, 'Retry same reversal');
            $this->assertSame(0, $d->fresh()->paid_minor);
            $this->assertSame(0, $pay->fresh()->available_minor);
            $this->assertSame('void', $pay->receipt->fresh()->status);
            $this->assertNotNull(PaymentAllocation::first()->reversed_at);
            $this->assertSame($pay->id, $f->recordPayment($v)->id);
            $this->assertSame(1, CustomerPayment::count());
        });
    }

    public function test_payment_client_project_and_invoice_identity_are_tenant_safe(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        [$o,$v,$oc,$op] = $this->studio('Other Money');
        $other = $this->issued($o, $oc, $op);
        $d = $this->issued($b, $c, $p);
        $this->actingAs($u)->postJson('/workspace/finance/payments', $this->payment($c, $p, $other))->assertNotFound();
        $this->postJson('/workspace/finance/payments', $this->payment($oc, $op, $other))->assertNotFound();
        $this->postJson('/workspace/finance/payments', $this->payment($c, $op, $d))->assertNotFound();
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_void_requires_payment_reversal_and_retains_number_history_and_zero_balance(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p);
        $this->within($b, function () use ($c, $p, $d) {
            $f = app(FinancialWorkflow::class);
            $pay = $f->recordPayment($this->payment($c, $p, $d));
            $this->invalid(fn () => $f->void($d, 'Synthetic cancellation'));
            $f->reverse($pay, 'Synthetic correction');
            $f->void($d, 'Synthetic cancellation');
            $fresh = $d->fresh();
            $this->assertSame('void', $fresh->display_status);
            $this->assertSame(0, $fresh->balance_minor);
            $this->assertSame($d->number, $fresh->number);
            $this->assertNotNull($fresh->snapshot);
            $this->invalid(fn () => $f->issue($fresh, 1));
        });
    }

    public function test_staff_admin_suspended_and_cross_tenant_access_are_denied(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        [$o,$v] = $this->studio('Hidden Finance');
        $d = $this->issued($b, $c, $p);
        $this->actingAs($v)->get('/workspace/finance/documents/'.$d->id)->assertNotFound();
        $this->post('/workspace/finance/documents/'.$d->id.'/share', ['days' => 7, 'confirm' => 1])->assertNotFound();
        $staff = User::factory()->create();
        $staff->forceFill(['business_id' => $b->id, 'role' => 'staff', 'is_active' => true])->save();
        $this->actingAs($staff);
        foreach (['/workspace/finance', '/workspace/finance/export', '/workspace/finance/documents/'.$d->id.'/pdf', '/workspace/billing', '/api/v1/invoices'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->get('/workspace/projects/'.$p->id)->assertOk()->assertDontSee($d->number);
        $this->actingAs($this->admin())->get('/workspace/finance')->assertForbidden();
        $this->actingAs($u)->get('/admin/billing')->assertForbidden();
        $b->forceFill(['account_status' => 'suspended'])->save();
        $this->actingAs($u)->get('/workspace/finance')->assertForbidden();
        $this->assertSame(0, FinancialDocument::count());
    }

    public function test_compound_database_constraints_reject_cross_client_and_cross_tenant_financial_links(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        [$o,$v,$oc,$op] = $this->studio('FK Other Studio');
        $d = $this->issued($b, $c, $p);
        $other = $this->issued($o, $oc, $op);
        $this->within($b, function () use ($c, $p, $oc, $op, $d, $other) {
            $f = app(FinancialWorkflow::class);
            $pay = $f->recordPayment($this->payment($c, $p, $d));
            foreach ([fn () => DB::table('customer_payments')->where('id', $pay->id)->update(['client_id' => $oc->id]), fn () => DB::table('financial_documents')->where('id', $d->id)->update(['project_id' => $op->id]), fn () => DB::table('payment_allocations')->where('payment_id', $pay->id)->update(['invoice_id' => $other->id])] as $attempt) {
                try {
                    $attempt();
                    $this->fail('Expected compound foreign key rejection.');
                } catch (QueryException $e) {
                    $this->assertSame('23000', $e->errorInfo[0]);
                }
            }
        });
    }

    public function test_pdf_and_share_use_frozen_data_hashes_no_cache_no_referrer_and_no_public_directory(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p);
        $this->actingAs($u)->get('/workspace/finance/documents/'.$d->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->post('/workspace/finance/documents/'.$d->id.'/share', ['days' => 1, 'confirm' => 1])->assertRedirect();
        $url = session('share_url');
        $token = basename($url);
        $this->assertSame(64, strlen($token));
        $this->assertDatabaseHas('document_shares', ['token_hash' => hash('sha256', $token)]);
        $this->assertStringNotContainsString($token, json_encode(DB::table('document_shares')->get()));
        $this->post('/logout');
        $this->get($url)->assertOk()->assertHeader('referrer-policy', 'no-referrer')->assertHeader('x-robots-tag', 'noindex, nofollow')->assertDontSee('consent.js');
        $pdf = $this->get($url.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        $this->within($b, fn () => DocumentShare::first()->update(['revoked_at' => now()]));
        $this->get($url)->assertNotFound();
        $this->get($url.'/pdf')->assertNotFound();
    }

    public function test_share_expiry_void_and_business_suspension_immediately_block_tokens(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $d = $this->issued($b, $c, $p);
        $url = $this->within($b, fn () => app(FinancialWorkflow::class)->share($d, 1));
        $this->travel(2)->days();
        $this->get($url)->assertNotFound();
        $this->travelBack();
        $url = $this->within($b, fn () => app(FinancialWorkflow::class)->share($d, 7));
        $b->forceFill(['account_status' => 'suspended'])->save();
        $this->get($url)->assertNotFound();
        $b->forceFill(['account_status' => 'pilot'])->save();
        $this->within($b, fn () => app(FinancialWorkflow::class)->void($d, 'Synthetic cancelled project'));
        $this->get($url)->assertNotFound();
    }

    public function test_pdf_html_is_escaped_and_jpeg_logo_remains_private(): void
    {
        Storage::fake('local');
        [$b,$u,$c,$p] = $this->studio();
        $file = UploadedFile::fake()->createWithContent('studio.jpg', file_get_contents(base_path('tests/Fixtures/billing-logo.jpg')));
        $this->actingAs($u)->patch('/workspace/finance/settings', $this->seller() + ['logo' => $file, 'reviewed' => 1])->assertRedirect();
        $d = $this->issued($b, $c, $p, 'invoice', ['notes' => '<img src="https://attacker.example/track"><script>alert(1)</script>']);
        $this->within($b, function () use ($d) {
            $pdf = app(FinancialPdf::class);
            $html = view('finance.pdf', ['document' => $d, 's' => $d->snapshot, 'logo' => $pdf->logo($d->snapshot['logo_path'])])->render();
            $this->assertStringContainsString('data:image/jpeg;base64,', $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringStartsWith('%PDF-', $pdf->render($d));
            $this->assertNull($pdf->logo('../../.env'));
            $this->assertNull($pdf->logo('https://attacker.example/logo.jpg'));
        });
    }

    public function test_filtered_csv_neutralises_spreadsheet_formulae_and_api_returns_integer_balances(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        [$o,$v,$oc,$op] = $this->studio('No Leak');
        $other = $this->issued($o, $oc, $op);
        $v1 = $this->payload($c, $p);
        $v1['customer']['name'] = '=HYPERLINK("https://example.test")';
        $d = $this->issued($b, $c, $p, 'invoice', $v1);
        $this->actingAs($u);
        $csv = $this->get('/workspace/finance/export?kind=invoice&client_id='.$c->id)->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('No Leak', $csv);
        $this->get('/workspace/finance?fy=2025-27')->assertSessionHasErrors('fy');
        $this->get('/api/v1/invoices')->assertOk()->assertJsonPath('data.data.0.id', $d->id)->assertJsonPath('data.data.0.total_minor', 283200)->assertJsonPath('data.data.0.balance_minor', 283200);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_platform_billing_is_separate_fixed_price_private_and_does_not_activate_subscriptions(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        [$o,$v] = $this->studio('Other Platform Customer');
        $admin = $this->admin();
        $seller = $this->seller('none') + ['default_tax_bps' => 0, 'hsn_sac' => '9983'];
        $this->actingAs($admin)->patch('/admin/billing/settings', $seller + ['reviewed' => 1])->assertRedirect();
        $data = ['business_id' => $b->id, 'submission_key' => (string) Str::uuid(), 'service' => 'software_yearly', 'quantity' => 1, 'document_date' => today()->toDateString(), 'due_date' => today()->addDays(7)->toDateString(), 'period_start' => today()->toDateString(), 'period_end' => today()->addYear()->subDay()->toDateString(), 'customer' => $this->payload($c, $p)['customer'], 'place_of_supply' => '09', 'rate_minor' => 1];
        $this->post('/admin/billing', $data)->assertRedirect();
        $this->post('/admin/billing', $data)->assertRedirect();
        $d = PlatformInvoice::firstOrFail();
        $this->assertSame(1, PlatformInvoice::count());
        $this->get('/admin/billing/'.$d->id)->assertOk()->assertSee('4,999.00');
        $this->actingAs($u)->get('/workspace/billing/'.$d->id)->assertNotFound();
        $hash = app(PlatformBilling::class)->preview($d)['hash'];
        $this->actingAs($admin)->post('/admin/billing/'.$d->id.'/issue', ['confirm' => 1, 'review_hash' => $hash])->assertRedirect();
        $d->refresh();
        $this->assertSame(499900, $d->total_minor);
        $this->assertStringStartsWith('SK/', $d->number);
        $this->invalid(fn () => $d->update(['total_minor' => 1]));
        $pay = ['submission_key' => (string) Str::uuid(), 'amount' => '4999', 'paid_on' => today()->toDateString(), 'reference' => 'SYNTHETIC-PLATFORM-REF'];
        $this->post('/admin/billing/'.$d->id.'/payments', $pay)->assertRedirect();
        $this->post('/admin/billing/'.$d->id.'/payments', $pay)->assertRedirect();
        $this->assertSame(1, PlatformPayment::count());
        $this->assertSame(0, $d->fresh()->balance_minor);
        $this->actingAs($v)->get('/workspace/billing/'.$d->id)->assertNotFound();
        $this->get('/workspace/billing/'.$d->id.'/pdf')->assertNotFound();
        $this->actingAs($u)->get('/workspace/billing/'.$d->id)->assertOk();
        $this->get('/workspace/billing/'.$d->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseCount('financial_documents', 0);
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_platform_settings_review_race_and_payment_reversal_and_fixed_campaign_rate(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $a = $this->admin();
        PlatformBillingSetting::create(['id' => 1, 'data' => $this->seller() + ['default_tax_bps' => 1800]]);
        $d = PlatformInvoice::create(['business_id' => $b->id, 'submission_key' => (string) Str::uuid(), 'service' => 'campaign_monthly', 'quantity' => 2, 'document_date' => today(), 'due_date' => today()->addDays(7), 'period_start' => today(), 'period_end' => today()->addMonths(2), 'customer' => $this->payload($c, $p)['customer'], 'place_of_supply' => '07']);
        $billing = app(PlatformBilling::class);
        $preview = $billing->preview($d);
        $this->assertSame(707764, $preview['totals']['total_minor']);
        $this->invalid(fn () => $billing->issue($d, str_repeat('0', 64)));
        $billing->issue($d, $preview['hash']);
        $d->refresh();
        $v1 = ['submission_key' => (string) Str::uuid(), 'amount' => '1000', 'paid_on' => today()->toDateString(), 'reference' => 'SYNTHETIC-PLATFORM'];
        $billing->payment($d, $v1);
        $this->invalid(fn () => $billing->payment($d, array_replace($v1, ['reference' => 'Changed reference'])));
        $p1 = PlatformPayment::first();
        $this->actingAs($a)->postJson('/admin/billing/'.$d->id.'/void', ['reason' => 'Synthetic cancellation'])->assertUnprocessable();
        $this->post('/admin/billing/'.$d->id.'/payments/'.$p1->id.'/reverse', ['reason' => 'Synthetic payment correction'])->assertRedirect();
        $this->post('/admin/billing/'.$d->id.'/void', ['reason' => 'Synthetic cancelled service'])->assertRedirect();
        $this->assertSame('void', $d->fresh()->status);
        $this->assertNotNull($p1->fresh()->reversed_at);
    }

    public function test_financial_recovery_round_trip_including_self_reference_receipt_allocations_and_share_hash(): void
    {
        [$b,$u,$c,$p] = $this->studio();
        $q = $this->issued($b, $c, $p, 'quotation');
        $this->within($b, function () use ($q, $c, $p) {
            $f = app(FinancialWorkflow::class);
            $f->decision($q, 'accepted', 'Synthetic accepted quote');
            $d = $f->invoiceFromQuote($q)->refresh();
            $d = $f->issue($d, 1);
            $f->recordPayment($this->payment($c, $p, $d));
            $f->share($d,7);
            CatalogueItem::create(['name' => 'Synthetic recovery item', 'category' => 'service', 'unit' => 'job', 'rate_minor' => 100, 'tax_bps' => 0]);
        });
        $path = storage_path('framework/testing/finance-recovery-'.Str::uuid().'.json');
        $service = new class($path) extends PreviewState
        {
            public function __construct(private string $p) {}

            public function enabled(): bool
            {
                return true;
            }

            public function path(): string
            {
                return $this->p;
            }
        };
        try {
            $service->save();
            $saved = json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
            foreach (array_reverse(array_keys($saved['tables'])) as $table) {
                if ($table === 'financial_documents') {
                    foreach (DB::table($table)->orderByDesc('id')->pluck('id') as $id) {
                        DB::table($table)->where('id',$id)->delete();
                    }
                } else {
                    DB::table($table)->delete();
                }
            }$this->assertSame(array_sum(array_map('count',$saved['tables'])),$service->restore());
            foreach ($saved['tables'] as $table => $rows) {
                $this->assertSame($rows,DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),$table);
            }$this->assertSame(1,(int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS n')->n);
        } finally {
            @unlink($path);
            @unlink($path.'.lock');
        }
    }
}
