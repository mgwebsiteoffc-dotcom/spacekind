<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPayment;
use App\Services\AttendanceSheet;
use App\Services\WorkerLedger;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkerStatementTest extends TestCase
{
    use RefreshDatabase;

    private function team(string $slug): array
    {
        $studio = Business::create(['name' => $slug, 'slug' => $slug, 'city' => 'Noida']);
        $owner = User::factory()->create();
        $owner->forceFill(['business_id' => $studio->id, 'role' => 'owner', 'is_active' => true])->save();

        return app(Tenant::class)->run($studio, function () use ($owner, $studio) {
            $worker = Worker::create(['name' => 'Worker, one', 'trade' => 'Painting']);
            $worker->rates()->create(['effective_from' => '2025-01-01', 'daily_paise' => 85051]);
            $client = Client::create(['name' => 'Client', 'phone' => '9000000000', 'locality' => 'Noida', 'source' => 'Direct']);
            $a = Project::create(['name' => '=Test', 'client_id' => $client->id]);
            $b = Project::create(['name' => 'Site B', 'client_id' => $client->id]);

            return [$studio, $owner, $worker, $a, $b];
        });
    }

    public function test_site_totals_reconcile_with_the_worker_and_the_csv(): void
    {
        [$studio, $owner, $worker, $a, $b] = $this->team('first');
        $this->actingAs($owner);
        app(Tenant::class)->run($studio, function () use ($worker, $a, $b) {
            $work = app(AttendanceSheet::class);
            $work->save('2025-03-10', [$worker->id => ['revision' => 0, 'first_project_id' => $a->id, 'second_project_id' => $b->id]]);
            $work->save('2025-03-11', [$worker->id => ['revision' => 0, 'second_project_id' => $b->id]]);
            $ledger = app(WorkerLedger::class);
            foreach ([['2025-03-08', '10.50', $a->id, 'advance'], ['2025-03-11', '100', $b->id, 'wage'], ['2025-03-11', '12.25', null, 'advance'], ['2025-03-12', '750', $a->id, 'wage'], ['2025-03-13', '450', $a->id, 'wage']] as [$date, $amount, $site, $kind]) {
                $ledger->record($worker, ['entry_key' => (string) Str::uuid(), 'paid_on' => $date, 'amount' => $amount, 'project_id' => $site, 'kind' => $kind, 'method' => 'cash']);
            }
            // A reversed payment in the period is excluded from both output formats.
            $ledger->record($worker, ['entry_key' => (string) Str::uuid(), 'paid_on' => '2025-03-11', 'amount' => '999', 'kind' => 'advance', 'method' => 'cash']);
            $ledger->reverse($worker, WorkerPayment::max('id'), 'Wrong amount');
        });

        $filters = ['worker' => $worker, 'from' => '2025-03-10', 'through' => '2025-03-12'];
        foreach ([[null, 40302], [$a->id, -33524], [$b->id, 75051], [0, -1225]] as [$site, $closing]) {
            $this->get(route('worker.statement', $filters + ['project_id' => $site]))->assertOk()->assertViewHas('closing', $closing);
        }
        $csv = $this->get(route('worker.statement.csv', $filters + ['project_id' => $a->id]))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString('"Worker, one",\'=Test', $csv);
        $this->assertStringContainsString('Opening,,,,-10.50', $csv);
        $this->assertStringContainsString('Activity,425.26,0.00,0.00,414.76', $csv);
        $this->assertStringContainsString('425.26,0.00,750.00,-335.24', $csv);
        $this->assertDatabaseHas('audit_events', ['business_id' => $studio->id, 'action' => 'worker.statement_exported']);
        $this->get(route('worker.statement', ['worker' => $worker, 'from' => '2025-03-14', 'through' => '2025-03-14']))
            ->assertOk()->assertViewHas('opening', -4698)->assertViewHas('closing', -4698)->assertViewHas('days', []);
    }

    public function test_period_validation_and_owner_only_tenant_boundaries_apply_to_export_too(): void
    {
        [, $owner, $worker] = $this->team('own');
        [, , $other, $foreignSite] = $this->team('other');
        $this->actingAs($owner);
        foreach (['worker.statement', 'worker.statement.csv'] as $route) {
            $this->get(route($route, $other))->assertNotFound();
            $this->get(route($route, ['worker' => $worker, 'project_id' => $foreignSite->id]))->assertNotFound();
            foreach ([['2025-02-01', '2025-01-01'], ['2024-01-01', '2025-01-01'], ['2025-01-01', today()->addDay()->toDateString()]] as [$from, $through]) {
                $this->getJson(route($route, compact('worker', 'from', 'through')))->assertUnprocessable()->assertJsonValidationErrors('through');
            }
        }
        $owner->forceFill(['role' => 'staff'])->save();
        $this->get(route('worker.statement', $worker))->assertForbidden();
        $this->get(route('worker.statement.csv', $worker))->assertForbidden();
    }
}
