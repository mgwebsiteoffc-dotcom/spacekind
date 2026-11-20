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

    public function test_passwordless_admin_access_is_gone_and_private_admin_password_login_works(): void
    {
        config(['spacekind.demo' => true]);
        $this->get('/login')->assertDontSee('Super admin')->assertDontSee('/demo-login');
        $this->post('/demo-login', ['role' => 'admin'])->assertNotFound();
        $this->get('/admin/integrations')->assertRedirect('/login');
        $retired = $this->account('super_admin');
        $retired->forceFill(['email' => 'admin@spacekind.example', 'password' => 'a-test-password'])->save();
        $this->post('/login', ['email' => $retired->email, 'password' => 'a-test-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($retired)->get('/admin')->assertForbidden();
        auth()->logout();
        $private = $this->account('super_admin');
        $private->forceFill(['password' => 'another-private-password'])->save();
        $this->post('/login', ['email' => $private->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $private->email, 'password' => 'another-private-password'])->assertRedirect();
        $this->get('/admin/integrations')->assertOk();
    }

    public function test_email_connection_can_be_rotated_without_showing_or_flashing_the_password(): void
    {
        config(['spacekind.demo' => false]);
        $url = '/admin/integrations/email';
        $fields = ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'starttls', 'username' => 'mailer', 'smtp_password' => 'secret-for-test', 'from_address' => 'support@example.test', 'from_name' => 'Spacekind'];
        $this->actingAs($this->account('owner', $this->studio('smtp-test')))->put($url, $fields)->assertForbidden();
        $this->actingAs($this->account('super_admin'))->put($url, ['port' => 0] + $fields)->assertSessionHasErrors('port');
        $this->assertNull(session()->getOldInput('smtp_password'));
        $this->put($url, $fields)->assertSessionHasNoErrors();
        $this->assertNotSame('secret-for-test', DB::table('email_delivery_settings')->value('smtp_password'));
        $this->get('/admin/integrations')->assertSee('smtp.example.test')->assertDontSee('secret-for-test');
        $this->put($url, ['smtp_password' => '', 'from_name' => 'Editorial'] + $fields)->assertSessionHasNoErrors();
        $this->put($url, ['smtp_password' => '', 'host' => 'another.example.test'] + $fields)->assertSessionHasErrors('smtp_password');
        config(['spacekind.demo' => true]);
        $this->delete($url)->assertForbidden();
        config(['spacekind.demo' => false]);
        $this->delete($url)->assertRedirect();
        $this->assertDatabaseCount('email_delivery_settings', 0);
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
        $this->get('/')->assertDontSee($quote['quote']);
        $this->patch($url, $quote + ['permission' => 1, 'is_visible' => 1, 'show_on_home' => 1])->assertSessionHasNoErrors();
        $this->get('/')->assertSee($quote['quote']);
        $this->post('/workspace/gallery', ['image' => UploadedFile::fake()->image('mood.jpg', 500, 400), 'kind' => 'inspiration', 'caption' => 'Earth tones moodboard', 'rights' => 1])->assertSessionHasNoErrors();
        $this->get('/studios/one')->assertOk()->assertSee($quote['quote'])->assertSee('Earth tones moodboard')->assertViewHas('gallery', fn ($g) => $g->isEmpty());
        $second = $this->studio('two');
        $this->actingAs($this->account('owner', $second))->patch($url, $quote + ['permission' => 1])->assertNotFound();
        $this->delete($url)->assertNotFound();
        $this->get('/studios/two')->assertDontSee($quote['quote'])->assertDontSee('Earth tones moodboard');
        $this->actingAs($this->account('staff', $b))->get('/workspace/testimonials')->assertForbidden();
        $b->forceFill(['account_status' => 'suspended'])->save();
        $this->get('/studios/one')->assertNotFound();
        $this->get('/')->assertDontSee($quote['quote']);
    }
}
