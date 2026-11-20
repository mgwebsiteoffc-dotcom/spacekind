<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Services\AttendanceSheet;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkerLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function team(string $slug): array
    {
        $b = Business::create(['name' => $slug, 'slug' => $slug, 'city' => 'Noida']);
        $user = User::factory()->create();
        $user->forceFill(['role' => 'owner', 'business_id' => $b->id, 'is_active' => true])->save();

        return app(Tenant::class)->run($b, function () use ($user) {
            $worker = Worker::create(['name' => 'Ravi', 'trade' => 'Carpentry']);
            $client = Client::create(['name' => 'Test client', 'phone' => '9000000000', 'locality' => 'Noida', 'source' => 'Direct']);
            $project = Project::create(['name' => 'Test project', 'client_id' => $client->id]);
            $worker->rates()->create(['daily_paise' => 100001, 'effective_from' => '2025-01-01']);
            app(AttendanceSheet::class)->save('2025-02-01', [$worker->id => ['revision' => 0, 'first_project_id' => $project->id]]);

            return [$user, $worker, $project];
        });
    }

    private function payment(): array
    {
        return ['entry_key' => (string) Str::uuid(), 'kind' => 'advance', 'amount' => '200.01', 'method' => 'cash', 'paid_on' => '2025-01-31'];
    }

    public function test_retries_balances_and_reversal_preserve_history(): void
    {
        [$owner, $worker, $project] = $this->team('north');
        $url = route('worker.ledger', $worker);
        $advance = $this->payment() + ['project_id' => $project->id, 'note' => 'Tools advance'];
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Outstanding · ₹500.01');
        $this->post($url, $advance)->assertRedirect($url);
        $this->post($url, $advance)->assertRedirect($url);
        $this->post($url, ['amount' => '301.00'] + $advance)->assertSessionHasErrors('entry_key');
        $this->get($url)->assertOk()->assertSee('Outstanding · ₹300.00')->assertSee('Tools advance');
        $wage = ['kind' => 'wage', 'amount' => '350.00'] + $this->payment();
        $this->post($url, $wage)->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Paid ahead · ₹50.00');
        $id = DB::table('worker_payments')->where('kind', 'wage')->value('id');
        $this->post($url.'/'.$id.'/reverse', [])->assertSessionHasErrors('reason');
        $this->post($url.'/'.$id.'/reverse', ['reason' => 'Incorrect amount'])->assertRedirect($url);
        $this->post($url.'/'.$id.'/reverse', ['reason' => 'Second attempt'])->assertRedirect($url);
        $this->post($url, $wage)->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Outstanding · ₹300.00')->assertSee('Incorrect amount')->assertDontSee('Second attempt');
        $this->assertDatabaseCount('worker_payments', 2);
        $this->assertSame(2, DB::table('audit_events')->where('action', 'worker.payment_recorded')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'worker.payment_reversed')->count());
    }

    public function test_cross_worker_tenant_and_staff_access_is_denied_and_inputs_are_checked(): void
    {
        [$a, $workerA] = $this->team('own');
        [$b, $workerB, $foreignProject] = $this->team('other');
        $url = route('worker.ledger', $workerA);
        $foreign = route('worker.ledger', $workerB);
        $payment = $this->payment();
        $this->actingAs($b)->post($foreign, $payment)->assertRedirect($foreign);
        $id = DB::table('worker_payments')->value('id');
        $otherWorker = app(Tenant::class)->run($b->business, fn () => Worker::create(['name' => 'Second worker', 'trade' => 'Painting']));
        $this->post(route('worker.ledger.reverse', [$otherWorker, $id]), ['reason' => 'Wrong worker'])->assertNotFound();
        $this->actingAs($a)->get($foreign)->assertNotFound();
        $this->post($foreign, $payment)->assertNotFound();
        $this->post($url, $payment + ['project_id' => $foreignProject->id])->assertNotFound();
        $this->post($url.'/'.$id.'/reverse', ['reason' => 'Not my entry'])->assertNotFound();
        foreach (['0', '-1', '1.001', '1e2'] as $invalid) {
            $this->post($url, ['amount' => $invalid] + $payment)->assertSessionHasErrors('amount');
        }
        $this->post($url, ['paid_on' => today()->addDay()->toDateString()] + $payment)->assertSessionHasErrors('paid_on');
        $a->forceFill(['role' => 'staff'])->save();
        $this->get($url)->assertForbidden();
        $this->post($url, $payment)->assertForbidden();
        $this->assertDatabaseCount('worker_payments', 1);
        $this->assertDatabaseHas('worker_payments', ['id' => $id, 'reversed_at' => null]);
    }
}
