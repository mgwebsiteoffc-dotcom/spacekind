<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\RazorpayCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegrationAndPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, ?Business $business = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'business_id' => $business?->id])->save();

        return $user;
    }

    private function studio(string $slug): Business
    {
        $b = Business::create(['slug' => $slug, 'name' => $slug, 'city' => 'Noida']);
        $b->forceFill(['approval_status' => 'approved'])->save();

        return $b;
    }

    public function test_admin_can_configure_gateway_and_templates_without_exposing_secrets(): void
    {
        config(['spacekind.demo' => false]);
        $base = '/admin/integrations';
        $url = $base.'/razorpay/test';
        $data = ['key_id' => 'rzp_test_1234567890', 'key_secret' => 'test-only-not-a-provider-secret'];
        $this->actingAs($this->account('owner', $this->studio('own')))->put($url, $data)->assertForbidden();
        $this->actingAs($this->account('super_admin'))->get($base)->assertOk()->assertSee('Email templates');
        $this->from($base)->put($url, ['key_id' => 'wrong'] + $data)->assertSessionHasErrors('key_id');
        $this->assertNull(session()->getOldInput('key_secret'));
        $this->put($url, $data)->assertSessionHasNoErrors();
        $stored = RazorpayCredential::sole();
        $this->assertNotSame($data['key_secret'], $stored->getRawOriginal('key_secret'));
        $this->assertSame($data['key_secret'], $stored->key_secret);
        $this->get($base)->assertSee($data['key_id'])->assertDontSee($data['key_secret']);
        $this->put($url, ['key_id' => $data['key_id']])->assertSessionHasNoErrors();
        $this->put($url, ['key_id' => 'rzp_test_456789'])->assertSessionHasErrors('key_secret');
        $this->put($base.'/templates/welcome', ['subject' => 'Welcome to your studio', 'body' => 'Start with your portfolio.'])->assertSessionHasNoErrors();
        $this->get($base)->assertSee('Start with your portfolio.');
        config(['spacekind.demo' => true]);
        $this->put($url, $data)->assertForbidden();
        config(['spacekind.demo' => false]);
        $this->delete($url)->assertRedirect();
        $this->assertDatabaseCount('razorpay_credentials', 0);
    }

    public function test_published_testimonials_and_inspiration_are_scoped_to_the_approved_profile(): void
    {
        Storage::fake('local');
        $b = $this->studio('one');
        $owner = $this->account('owner', $b);
        $this->actingAs($owner)->get('/workspace/testimonials')->assertOk();
        $quote = ['quote' => 'We love the kitchen', 'attribution' => 'A client'];
        $this->post('/workspace/testimonials', $quote)->assertSessionHasErrors('permission');
        $this->post('/workspace/testimonials', $quote + ['permission' => 1])->assertSessionHasNoErrors();
        $id = DB::table('testimonials')->value('id');
        $url = '/workspace/testimonials/'.$id;
        $this->get('/studios/one')->assertDontSee($quote['quote']);
        $this->patch($url, $quote + ['permission' => 1, 'is_visible' => 1])->assertSessionHasNoErrors();
        $this->post('/workspace/gallery', ['image' => UploadedFile::fake()->image('mood.jpg', 500, 400), 'kind' => 'inspiration', 'caption' => 'Earth tones moodboard', 'rights' => 1])->assertSessionHasNoErrors();
        $this->get('/studios/one')->assertOk()->assertSee($quote['quote'])->assertSee('Earth tones moodboard')->assertViewHas('gallery', fn ($g) => $g->isEmpty());
        $second = $this->studio('two');
        $this->actingAs($this->account('owner', $second))->patch($url, $quote + ['permission' => 1])->assertNotFound();
        $this->delete($url)->assertNotFound();
        $this->get('/studios/two')->assertDontSee($quote['quote'])->assertDontSee('Earth tones moodboard');
        $this->actingAs($this->account('staff', $b))->get('/workspace/testimonials')->assertForbidden();
        $b->forceFill(['account_status' => 'suspended'])->save();
        $this->get('/studios/one')->assertNotFound();
    }
}
