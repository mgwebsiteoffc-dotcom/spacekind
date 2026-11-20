<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\InspirationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkerRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function member(Business $business, string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['business_id' => $business->id, 'role' => $role, 'is_active' => true])->save();

        return $user;
    }

    public function test_dated_rates_are_append_only_and_private_to_the_owner(): void
    {
        $a = Business::create(['name' => 'First', 'slug' => 'first', 'city' => 'Noida']);
        $b = Business::create(['name' => 'Second', 'slug' => 'second', 'city' => 'Noida']);
        $owner = $this->member($a, 'owner');
        $rate = ['effective_from' => '2026-01-01', 'daily_rate' => '850.50'];
        $this->actingAs($owner)->post('/workspace/labour', ['name' => 'Asha', 'trade' => 'Painter'] + $rate)->assertRedirect('/workspace/labour');
        $id = DB::table('workers')->where('business_id', $a->id)->value('id');
        $url = '/workspace/labour/'.$id.'/rates';
        $this->post($url, $rate)->assertSessionHasErrors('effective_from');
        $this->post($url, ['effective_from' => '2026-02-01', 'daily_rate' => '900'])->assertSessionHasNoErrors();
        $this->get('/workspace/labour')->assertOk()->assertSee('Asha')->assertSee('850.50')->assertSee('900.00');
        $this->actingAs($this->member($b, 'owner'))->get('/workspace/labour')->assertOk()->assertDontSee('Asha');
        $this->post($url, $rate)->assertNotFound();
        $this->actingAs($this->member($a, 'staff'))->get('/workspace/labour')->assertForbidden();
        $this->post($url, $rate)->assertForbidden();
        $this->assertDatabaseCount('worker_rates', 2);
        $this->assertDatabaseHas('worker_rates', ['worker_id' => $id, 'daily_paise' => 85050, 'effective_from' => '2026-01-01']);
    }

    public function test_public_editorial_seed_is_repeatable_and_does_not_invent_studios(): void
    {
        config(['spacekind.demo' => false]);
        $this->seed(InspirationSeeder::class);
        $this->seed(InspirationSeeder::class);
        $this->assertDatabaseCount('inspiration_edits', 3);
        $this->assertDatabaseCount('businesses', 0);
        $this->get('/')->assertOk()->assertSee('Less noise. More living.')->assertDontSee('Sample enquiry')->assertDontSee('Development preview');
    }
}
