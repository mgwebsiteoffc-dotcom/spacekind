<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\GalleryImage;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Services\PreviewState;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpacekindFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function studio(string $name = 'Test Studio', string $status = 'approved'): array
    {
        $business = Business::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'city' => 'Noida', 'locality' => 'Sector 104', 'bio' => 'A synthetic studio for isolated automated tests.']);
        $business->forceFill(['approval_status' => $status])->save();
        $user = User::factory()->create(['name' => $name.' Owner']);
        $user->forceFill(['business_id' => $business->id, 'role' => 'owner', 'is_active' => true])->save();

        return [$business->refresh(), $user->refresh()];
    }

    private function payload(): array
    {
        return ['name' => 'Synthetic Test Client', 'phone' => '+91 9000000000', 'city' => 'Noida', 'locality' => 'Sector 137', 'floor_area' => 1650, 'property_type' => 'Apartment', 'configuration' => '3 BHK', 'scope' => 'Full-home interiors', 'budget' => '₹10–20 lakh', 'readiness' => 'Within 1–3 months', 'brief' => 'A synthetic project brief for automated development testing only.', 'contact_consent' => 1, 'submission_key' => (string) Str::uuid()];
    }

    private function lead(Business $business, User $owner, array $extra = []): Lead
    {
        return app(Tenant::class)->run($business, fn () => Lead::create(array_merge(collect($this->payload())->except(['contact_consent', 'submission_key'])->all(), ['stage' => 'New', 'source' => 'Marketplace', 'assigned_to' => $owner->id], $extra)));
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'is_active' => true])->save();

        return $admin;
    }

    public function test_enquiry_alert_uses_studio_owner_and_sends_no_homeowner_details_on_retry(): void
    {
        config(['spacekind.enquiry_email' => true, 'spacekind.demo' => false]);
        [$studio, $owner] = $this->studio();
        \App\Models\EmailDeliverySetting::forceCreate(['id' => 1, 'host' => 'mail.example.test', 'port' => 587, 'encryption' => 'starttls', 'username' => 'sender', 'smtp_password' => 'fake', 'from_name' => 'Test', 'from_address' => 'sender@example.test']);
        $payload = $this->payload();
        $this->partialMock(\App\Services\EnquiryAlert::class)->shouldReceive('deliver')->once()->withArgs(function ($smtp, $message) use ($payload, $owner) {
            return $message->getTo()[0]->getAddress() === $owner->email && ! str_contains($message->getTextBody(), $payload['phone']) && ! str_contains($message->getTextBody(), $payload['brief']);
        });
        for ($i = 0; $i < 2; $i++) {
            $this->post('/studios/'.$studio->slug.'/enquire', $payload)->assertRedirect('/studios/'.$studio->slug.'/enquire/thanks');
        }
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_application_runs_on_actual_mysql_and_models_fail_closed_without_a_tenant(): void
    {
        [$business,$owner] = $this->studio();
        $this->lead($business, $owner);
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringStartsWith('8.', DB::selectOne('SELECT VERSION() AS version')->version);
        $this->assertSame(0, Lead::count());
        $this->assertSame(1, app(Tenant::class)->run($business, fn () => Lead::count()));
        $this->assertNull(app(Tenant::class)->id());
    }

    public function test_public_directory_filters_only_approved_active_studios(): void
    {
        [$approved] = $this->studio('Published Practice');
        [$pending] = $this->studio('Hidden Practice', 'pending');
        $this->get('/?city=Noida&service=home')->assertOk()->assertSee('Published Practice')->assertDontSee('Hidden Practice');
        $this->get('/studios/'.$pending->slug)->assertNotFound();
        $this->get('/studios/'.$approved->slug)->assertOk();
    }

    public function test_qualified_public_enquiry_is_tenant_routed_and_ignores_privileged_input(): void
    {
        [$a,$ownerA] = $this->studio('Studio A');
        [$b,$ownerB] = $this->studio('Studio B');
        $payload = $this->payload() + ['business_id' => $b->id, 'assigned_to' => $ownerB->id, 'stage' => 'Won', 'source' => 'Campaign link', 'origin' => 'marketplace', 'role' => 'super_admin'];
        $this->post('/studios/'.$a->slug.'/enquire', $payload)->assertRedirect('/studios/'.$a->slug.'/enquire/thanks');
        $this->assertDatabaseHas('leads', ['business_id' => $a->id, 'name' => $payload['name'], 'stage' => 'New', 'source' => 'Marketplace', 'assigned_to' => $ownerA->id, 'marketing_consent' => false]);
        $this->assertDatabaseMissing('leads', ['business_id' => $b->id]);
    }

    public function test_contact_consent_and_qualification_are_required_but_marketing_is_optional(): void
    {
        [$business] = $this->studio();
        $payload = $this->payload();
        unset($payload['contact_consent'],$payload['floor_area']);
        $this->postJson('/studios/'.$business->slug.'/enquire', $payload)->assertUnprocessable()->assertJsonValidationErrors(['contact_consent', 'floor_area']);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_public_submission_is_idempotent_and_does_not_send_notifications(): void
    {
        [$business] = $this->studio();
        $payload = $this->payload();
        $this->post('/studios/'.$business->slug.'/enquire', $payload)->assertRedirect();
        $this->post('/studios/'.$business->slug.'/enquire', $payload)->assertRedirect();
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('lead_activities', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_campaign_labels_require_separate_permission_and_cannot_contain_obvious_pii(): void
    {
        [$business] = $this->studio();
        $payload = $this->payload() + ['utm_source' => 'instagram', 'utm_campaign' => 'warm_homes'];
        $this->post('/studios/'.$business->slug.'/enquire', $payload)->assertRedirect();
        $this->assertDatabaseHas('leads', ['business_id' => $business->id, 'utm' => null, 'source' => 'Studio profile']);
        $payload['submission_key'] = (string) Str::uuid();
        $payload['attribution_consent'] = 1;
        $this->post('/studios/'.$business->slug.'/enquire', $payload)->assertRedirect();
        $this->assertDatabaseHas('leads', ['business_id' => $business->id, 'source' => 'Campaign link', 'utm->campaign' => 'warm_homes']);
        $payload['submission_key'] = (string) Str::uuid();
        $payload['utm_campaign'] = 'person@example.test';
        $this->postJson('/studios/'.$business->slug.'/enquire', $payload)->assertUnprocessable()->assertJsonValidationErrors('utm_campaign');
    }

    public function test_unapproved_studio_cannot_accept_a_public_enquiry(): void
    {
        [$business] = $this->studio('Pending Studio', 'pending');
        $this->post('/studios/'.$business->slug.'/enquire', $this->payload())->assertNotFound();
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_cross_studio_lead_reads_writes_notes_conversions_and_files_are_rejected(): void
    {
        Storage::fake('local');
        [$a,$ownerA] = $this->studio('Studio A');
        [$b,$ownerB] = $this->studio('Studio B');
        $lead = $this->lead($b, $ownerB, ['plan_path' => 'plans/private.pdf']);
        Storage::disk('local')->put('plans/private.pdf', '%PDF-1.4 synthetic');
        $this->actingAs($ownerA)->get('/workspace/enquiries/'.$lead->id)->assertNotFound();
        $this->patch('/workspace/enquiries/'.$lead->id, ['stage' => 'Qualified'])->assertNotFound();
        $this->post('/workspace/enquiries/'.$lead->id.'/notes', ['body' => 'Forbidden note'])->assertNotFound();
        $this->post('/workspace/enquiries/'.$lead->id.'/convert')->assertNotFound();
        $this->get('/workspace/enquiries/'.$lead->id.'/plan')->assertNotFound();
        $this->assertDatabaseMissing('lead_activities', ['body' => 'Forbidden note']);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_enquiry_can_be_assigned_only_to_an_active_member_of_its_own_studio(): void
    {
        [$a,$ownerA] = $this->studio('Studio A');
        [$b,$ownerB] = $this->studio('Studio B');
        $lead = $this->lead($a, $ownerA);
        $this->actingAs($ownerA)->patchJson('/workspace/enquiries/'.$lead->id, ['stage' => 'Qualified', 'assigned_to' => $ownerB->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'stage' => 'New', 'assigned_to' => $ownerA->id]);
    }

    public function test_notes_stages_and_followups_persist_with_activity_history(): void
    {
        [$business,$owner] = $this->studio();
        $lead = $this->lead($business, $owner);
        $this->actingAs($owner)->patch('/workspace/enquiries/'.$lead->id, ['stage' => 'Qualified', 'assigned_to' => $owner->id, 'next_follow_up_at' => now()->addDay()->format('Y-m-d\TH:i'), 'follow_up_task' => 'Confirm synthetic site visit'])->assertRedirect();
        $this->post('/workspace/enquiries/'.$lead->id.'/notes', ['body' => 'Synthetic note persisted to the database.'])->assertRedirect();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'stage' => 'Qualified', 'follow_up_task' => 'Confirm synthetic site visit']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'note', 'body' => 'Synthetic note persisted to the database.']);
        $this->post('/workspace/enquiries/'.$lead->id.'/complete')->assertRedirect();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'next_follow_up_at' => null]);
        $this->patchJson('/workspace/enquiries/'.$lead->id, ['stage' => 'Lost'])->assertUnprocessable()->assertJsonValidationErrors('lost_reason');
    }

    public function test_conversion_is_transactional_idempotent_and_retains_recorded_source(): void
    {
        [$business,$owner] = $this->studio();
        $lead = $this->lead($business, $owner, ['source' => 'Campaign link', 'utm' => ['source' => 'instagram', 'campaign' => 'synthetic_demo']]);
        $this->actingAs($owner)->post('/workspace/enquiries/'.$lead->id.'/convert')->assertRedirect();
        $this->post('/workspace/enquiries/'.$lead->id.'/convert')->assertRedirect();
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'stage' => 'Won']);
        $this->assertDatabaseHas('clients', ['business_id' => $business->id, 'lead_id' => $lead->id, 'source' => 'Campaign link', 'utm->campaign' => 'synthetic_demo']);
    }

    public function test_project_details_and_handover_are_tenant_scoped(): void
    {
        [$a,$ownerA] = $this->studio('Studio A');
        [$b,$ownerB] = $this->studio('Studio B');
        $lead = $this->lead($a, $ownerA);
        $this->actingAs($ownerA)->post('/workspace/enquiries/'.$lead->id.'/convert');
        $project = Project::withoutGlobalScope('business')->firstOrFail();
        $this->patch('/workspace/projects/'.$project->id, ['name' => 'Synthetic project', 'stage' => 'Handover', 'notes' => 'Synthetic handover notes'])->assertRedirect();
        $this->assertNotNull(DB::table('projects')->where('id', $project->id)->value('handover_at'));
        $this->actingAs($ownerB)->get('/workspace/projects/'.$project->id)->assertNotFound();
        $this->patch('/workspace/projects/'.$project->id, ['name' => 'Intrusion', 'stage' => 'Closed'])->assertNotFound();
    }

    public function test_staff_cannot_manage_profile_gallery_team_or_platform_admin(): void
    {
        [$business,$owner] = $this->studio();
        $staff = User::factory()->create();
        $staff->forceFill(['business_id' => $business->id, 'role' => 'staff', 'is_active' => true])->save();
        $this->actingAs($staff)->get('/workspace')->assertOk();
        foreach (['/workspace/studio', '/workspace/team', '/admin'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/workspace/gallery', [])->assertForbidden();
        $this->patch('/workspace/studio', [])->assertForbidden();
    }

    public function test_private_plan_is_downloadable_only_in_the_authorised_workspace(): void
    {
        Storage::fake('local');
        [$business,$owner] = $this->studio();
        $payload = $this->payload();
        $payload['plan'] = UploadedFile::fake()->createWithContent('synthetic-plan.pdf', "%PDF-1.4\n% Synthetic test plan\n");
        $this->post('/studios/'.$business->slug.'/enquire', $payload)->assertRedirect();
        $lead = Lead::withoutGlobalScope('business')->firstOrFail();
        $this->assertNotNull($lead->plan_path);
        Storage::disk('local')->assertExists($lead->plan_path);
        $this->get('/workspace/enquiries/'.$lead->id.'/plan')->assertRedirect('/login');
        $this->actingAs($owner)->get('/workspace/enquiries/'.$lead->id.'/plan')->assertOk()->assertDownload();
    }

    public function test_gallery_upload_visibility_ownership_and_deletion_are_enforced(): void
    {
        Storage::fake('local');
        [$a,$ownerA] = $this->studio('Studio A');
        [$b,$ownerB] = $this->studio('Studio B');
        $image = UploadedFile::fake()->createWithContent('synthetic.webp', file_get_contents(public_path('assets/interiors/hero.webp')));
        $this->actingAs($ownerA)->post('/workspace/gallery', ['image' => $image, 'caption' => 'Synthetic gallery image', 'kind' => 'concept', 'rights' => 1])->assertRedirect();
        $record = GalleryImage::withoutGlobalScope('business')->firstOrFail();
        Storage::disk('local')->assertExists($record->path);
        $this->actingAs($ownerB)->delete('/workspace/gallery/'.$record->id)->assertNotFound();
        $this->actingAs($ownerA)->patch('/workspace/gallery/'.$record->id, ['caption' => 'Hidden concept', 'position' => 0, 'is_visible' => 0])->assertRedirect();
        auth()->logout();
        $this->get('/media/'.$record->id)->assertNotFound();
        $this->actingAs($ownerA)->get('/media/'.$record->id)->assertOk();
        $this->delete('/workspace/gallery/'.$record->id)->assertRedirect();
        Storage::disk('local')->assertMissing($record->path);
    }

    public function test_executable_or_svg_gallery_uploads_are_rejected(): void
    {
        [$business,$owner] = $this->studio();
        $file = UploadedFile::fake()->createWithContent('payload.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($owner)->postJson('/workspace/gallery', ['image' => $file, 'caption' => 'Unsafe file', 'kind' => 'concept', 'rights' => 1])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseCount('gallery_images', 0);
    }

    public function test_admin_approval_and_suspension_control_public_and_private_access(): void
    {
        [$business,$owner] = $this->studio('Pending Practice', 'pending');
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->patch('/admin/studios/'.$business->slug.'/review', ['decision' => 'approved'])->assertRedirect();
        $this->get('/studios/'.$business->slug)->assertOk();
        $this->patch('/admin/studios/'.$business->slug.'/access')->assertRedirect();
        $this->get('/studios/'.$business->slug)->assertNotFound();
        $this->actingAs($owner->refresh())->get('/workspace')->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.workspace_access_changed', 'business_id' => $business->id]);
    }

    public function test_registration_creates_pending_studio_owner_and_unpaid_pilot_only(): void
    {
        $this->post('/register', ['name' => 'Synthetic Owner', 'studio_name' => 'Synthetic New Practice', 'city' => 'Noida', 'email' => 'synthetic-owner@example.test', 'password' => 'TestPassword123', 'password_confirmation' => 'TestPassword123', 'terms' => 1, 'role' => 'super_admin', 'approval_status' => 'approved'])->assertRedirect('/workspace/studio');
        $this->assertDatabaseHas('businesses', ['name' => 'Synthetic New Practice', 'approval_status' => 'pending']);
        $this->assertDatabaseHas('users', ['email' => 'synthetic-owner@example.test', 'role' => 'owner']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'pilot', 'amount_paise' => 499900]);
        $this->get('/workspace/studio')->assertOk()->assertSee('data-start-tour', false)->assertSee('Step 1 of 4');
        $this->get('/workspace/studio')->assertOk()->assertDontSee('data-start-tour', false)->assertSee('Take the workspace tour');
    }

    public function test_studio_and_admin_password_logins_reach_their_own_dashboard_from_the_wrong_entry(): void
    {
        [, $owner] = $this->studio();
        $admin = $this->admin();
        foreach ([[$owner, '/admin', '/workspace'], [$admin, '/workspace', '/admin']] as [$account, $wrong, $home]) {
            $password = bin2hex(random_bytes(16));
            $account->forceFill(['password' => $password])->save();
            $this->get($wrong)->assertRedirect('/login');
            $this->get('/login')->assertOk()->assertSee('name="password"', false);
            $this->post('/login', ['email' => $account->email, 'password' => $password])->assertRedirect($home);
            $this->get($home)->assertOk();
            $this->get($wrong)->assertForbidden();
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
    }

    public function test_studio_sign_in_preserves_an_authorized_enquiry_list_link(): void
    {
        [, $owner] = $this->studio();
        $password = bin2hex(random_bytes(16));
        $owner->forceFill(['password' => $password])->save();
        $this->get('/workspace/enquiries?due=1')->assertRedirect('/login');
        $this->post('/login', ['email' => $owner->email, 'password' => $password])->assertRedirect('/workspace/enquiries?due=1');
        $this->get('/workspace/enquiries?due=1')->assertOk();
    }

    public function test_demo_login_is_unavailable_when_demo_mode_is_disabled(): void
    {
        config(['spacekind.demo' => false]);
        $this->post('/demo-login', ['role' => 'admin'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_api_requires_authentication_and_enforces_same_tenant_workflow(): void
    {
        [$a,$ownerA] = $this->studio('Studio A');
        [$b,$ownerB] = $this->studio('Studio B');
        $own = $this->lead($a, $ownerA);
        $other = $this->lead($b, $ownerB);
        $this->getJson('/api/v1/leads')->assertUnauthorized();
        $token = $ownerA->createToken('automated-test', ['workspace'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/v1/me', $headers)->assertOk()->assertJsonPath('data.business.id', $a->id);
        $this->getJson('/api/v1/leads', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonMissing(['id' => $other->id]);
        $this->getJson('/api/v1/leads/'.$other->id, $headers)->assertNotFound();
        $this->patchJson('/api/v1/leads/'.$other->id, ['stage' => 'Won'], $headers)->assertNotFound();
        $this->getJson('/api/v1/leads/'.$own->id, $headers)->assertOk()->assertJsonMissingPath('data.plan_path')->assertJsonMissingPath('data.submission_key');
    }

    public function test_inactive_staff_cannot_use_an_existing_api_token(): void
    {
        [$business,$owner] = $this->studio();
        $token = $owner->createToken('test', ['workspace'])->plainTextToken;
        $owner->is_active = false;
        $owner->save();
        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_security_headers_noindex_and_no_third_party_tracking_are_present(): void
    {
        $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('googletagmanager.com')->assertDontSee('connect.facebook.net');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<loc>', false);
    }

    public function test_pending_studio_sees_clear_review_notice_without_uncompiled_blade(): void
    {
        [$business,$owner] = $this->studio('Pending Practice', 'pending');
        $this->actingAs($owner)->get('/workspace/studio')->assertOk()->assertSee('public listing is hidden')->assertDontSee('@elseYour');
        $this->get('/studios/'.$business->slug)->assertNotFound();
    }

    public function test_https_embedded_preview_uses_secure_partitioned_session_cookies(): void
    {
        config(['spacekind.demo' => true]); // This policy applies only to shared, embedded sessions.
        $response = $this->get('https://8000-test.e2b.app/login');
        $response->assertOk();
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        $cookie = $cookies->get(config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('none', $cookie->getSameSite());
        $this->assertTrue($cookie->isPartitioned());
    }

    public function test_malformed_array_campaign_parameters_cannot_crash_the_enquiry_page(): void
    {
        [$business] = $this->studio();
        $this->get('/studios/'.$business->slug.'/enquire?utm_source[]=invalid')->assertOk()->assertDontSee('Array to string conversion');
    }

    public function test_malformed_login_identifiers_return_validation_errors_not_limiter_crashes(): void
    {
        $this->postJson('/login', ['email' => ['unexpected-array'], 'password' => 'not-a-real-password'])->assertUnprocessable()->assertJsonValidationErrors('email');
        config(['spacekind.demo' => true]);
        $this->postJson('/demo-login', ['role' => ['unexpected-array']])->assertNotFound();
    }

    public function test_development_recovery_never_snapshots_the_test_database(): void
    {
        $this->assertFalse(app(PreviewState::class)->enabled());
    }
}
