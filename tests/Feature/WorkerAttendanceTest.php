<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/workspace/labour/attendance';

    private function team(string $slug): array
    {
        $studio = Business::create(['name' => $slug, 'slug' => $slug, 'city' => 'Noida']);
        $owner = User::factory()->create();
        $owner->forceFill(['business_id' => $studio->id, 'role' => 'owner', 'is_active' => true])->save();

        return app(Tenant::class)->run($studio, function () use ($owner, $slug) {
            $worker = Worker::create(['name' => $slug.' worker', 'trade' => 'Carpentry']);
            $worker->rates()->create(['effective_from' => '2025-01-01', 'daily_paise' => 85051]);
            $client = Client::create(['name' => $slug, 'phone' => '9000000000', 'locality' => 'Noida', 'source' => 'Direct']);
            $project = fn ($name) => Project::create(['name' => $name, 'client_id' => $client->id])->id;

            return [$owner, $worker->id, $project($slug.' kitchen'), $project($slug.' bedroom')];
        });
    }

    private function sheet(array $rows, string $date = '2025-03-10'): array
    {
        return ['date' => $date, 'page' => 1, 'rows' => $rows];
    }

    public function test_split_days_retries_historical_rates_corrections_and_removal(): void
    {
        [$owner, $worker, $first, $second] = $this->team('north');
        $this->actingAs($owner)->get(self::URL)->assertOk()->assertSee('north worker');
        $split = $this->sheet([$worker => ['revision' => 0, 'first_project_id' => $first, 'second_project_id' => $second, 'note' => 'Measured and fitted panels']]);
        $this->post(self::URL, $split)->assertSessionHasNoErrors();
        $this->post(self::URL, $split)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('worker_attendances', 1);
        $this->assertDatabaseHas('worker_attendances', ['worker_id' => $worker, 'earned_paise' => 85051, 'revision' => 1]);
        $this->get(self::URL.'?date=2025-03-10')->assertOk()->assertSee('Full day')->assertSee('850.51')->assertSee('Measured and fitted panels');
        $this->post('/workspace/labour/'.$worker.'/rates', ['effective_from' => '2025-02-01', 'daily_rate' => '1000'])->assertSessionHasNoErrors();
        $this->post(self::URL, $this->sheet([$worker => ['revision' => 1, 'first_project_id' => $first]]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('worker_attendances', ['revision' => 2, 'daily_paise' => 85051, 'earned_paise' => 42526, 'second_project_id' => null]);
        $this->post(self::URL, $split)->assertSessionHasErrors('rows.'.$worker);
        $this->post(self::URL, $this->sheet([$worker => ['revision' => 2, 'absent' => 1]]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('worker_attendances', ['revision' => 3, 'absent' => true, 'earned_paise' => 0]);
        $this->post(self::URL, $this->sheet([$worker => ['revision' => 3]]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('worker_attendances', ['revision' => 4, 'absent' => false, 'earned_paise' => 0]);
        $this->assertSame(4, DB::table('audit_events')->where('action', 'attendance.saved')->count());
    }

    public function test_unmarked_rows_are_not_absences_and_bad_bulk_edits_roll_back(): void
    {
        [$owner, $id, $site] = $this->team('bulk');
        $second = app(Tenant::class)->run($owner->business, fn () => Worker::create(['name' => 'No rate yet', 'trade' => 'Painting'])->id);
        $this->actingAs($owner)->post(self::URL, $this->sheet([$id => ['revision' => 0]]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('worker_attendances', 0);
        $rows = [$id => ['revision' => 0, 'first_project_id' => $site], $second => ['revision' => 0, 'second_project_id' => $site]];
        $this->post(self::URL, $this->sheet($rows))->assertSessionHasErrors('rows.'.$second);
        $this->assertDatabaseCount('worker_attendances', 0);
        $rows[$second] = ['revision' => 0, 'absent' => 1];
        $this->post(self::URL, $this->sheet($rows))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('worker_attendances', 2);
        $this->post(self::URL, $this->sheet([$id => ['revision' => 1, 'absent' => 1, 'first_project_id' => $site]]))->assertSessionHasErrors('rows.'.$id);
        $this->post(self::URL, $this->sheet($rows, today()->addDay()->toDateString()))->assertSessionHasErrors('date');
        $this->assertDatabaseCount('worker_attendances', 2);
    }

    public function test_private_workers_and_projects_cannot_be_submitted_across_studios(): void
    {
        [$owner, $worker] = $this->team('own');
        [, $other, $foreignSite] = $this->team('other');
        $this->get(self::URL)->assertRedirect('/login');
        $this->actingAs($owner)->get(self::URL)->assertOk()->assertDontSee('other kitchen')->assertDontSee('other worker');
        $this->post(self::URL, $this->sheet([$other => ['revision' => 0, 'absent' => 1]]))->assertNotFound();
        $this->post(self::URL, $this->sheet([$worker => ['revision' => 0, 'first_project_id' => $foreignSite]]))->assertNotFound();
        $owner->forceFill(['role' => 'staff'])->save();
        $this->get(self::URL)->assertForbidden();
        $this->post(self::URL, $this->sheet([$worker => ['revision' => 0]]))->assertForbidden();
        $this->assertDatabaseCount('worker_attendances', 0);
    }
}
