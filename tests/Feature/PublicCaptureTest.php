<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProjectCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_capture_is_opt_in_private_validated_and_idempotent(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="project-capture"', false);
        $this->get('/project-brief')->assertNotFound();
        $this->get('/admin/project-capture')->assertRedirect('/login');
        $u = User::factory()->create();
        $this->actingAs($u)->put('/admin/project-capture', [])->assertForbidden();
        $u->forceFill(['role' => 'super_admin', 'is_active' => true])->save();
        $settings = ['preset' => 'all', 'heading' => 'Your project shortlist', 'button' => 'Ask Spacekind', 'delay' => 4];
        $this->put('/admin/project-capture', $settings)->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('id="project-capture"', false);
        auth()->logout();
        $this->get('/')->assertSee('id="project-capture"', false)->assertSee('Ask Spacekind')->assertSee('data-entry="1"', false)->assertSee('data-exit="1"', false);
        $this->get('/login')->assertDontSee('id="project-capture"', false);
        $brief = ['submission_key' => (string) Str::uuid(), 'name' => 'Private contact', 'phone' => '+919876543210', 'city' => 'Lucknow', 'floor_area' => 900, 'budget' => config('spacekind.budgets')[0], 'scope' => config('spacekind.services.home')];
        $this->post('/project-brief', $brief)->assertSessionHasErrors('consent')->assertRedirect('/project-brief');
        $this->post('/project-brief', $brief + ['consent' => 1])->assertSessionHas('brief_received');
        $this->post('/project-brief', $brief + ['consent' => 1])->assertSessionHas('brief_received');
        $this->assertDatabaseCount('platform_briefs', 1);
        $this->assertDatabaseCount('leads', 0);
        $this->get('/inspiration')->assertOk()->assertSee('Studio moodboards.')->assertDontSee('Private contact');
        $this->actingAs($u)->get('/admin/project-capture')->assertOk()->assertSee('Private contact');
        $this->delete('/admin/project-capture/'.DB::table('platform_briefs')->value('id'))->assertRedirect();
        $this->assertDatabaseCount('platform_briefs', 0);
        $this->put('/admin/project-capture', ProjectCapture::DEFAULTS)->assertSessionHasNoErrors();
        auth()->logout();
        $this->post('/project-brief', $brief + ['consent' => 1])->assertNotFound();
    }
}
