<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Business;
use App\Models\CampaignLink;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Reel;
use App\Models\TrackingVersion;
use App\Models\User;
use App\Services\Discovery;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpacekindContentTest extends TestCase
{
    use RefreshDatabase;

    private function studio(string $name = 'Content Studio', string $approval = 'approved'): array
    {
        $b = Business::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'city' => 'Noida', 'locality' => 'Sector 104', 'bio' => 'A synthetic, isolated studio used only by automated tests.']);
        $b->forceFill(['approval_status' => $approval])->save();
        $u = User::factory()->create(['name' => $name.' Owner']);
        $u->forceFill(['business_id' => $b->id, 'role' => 'owner', 'is_active' => true])->save();

        return [$b, $u];
    }

    private function admin(): User
    {
        $u = User::factory()->create(['name' => 'Synthetic Admin']);
        $u->forceFill(['role' => 'super_admin', 'business_id' => null, 'is_active' => true])->save();

        return $u;
    }

    private function articleData(array $extra = []): array
    {
        return array_replace(['title' => 'A thoughtful synthetic article', 'slug' => 'synthetic-'.Str::lower(Str::random(8)), 'excerpt' => 'A clear, synthetic short answer for an isolated content workflow test.', 'body' => "## What is a useful starting point?\n\nA clear synthetic answer with practical context. This is not a real client project or a professional claim.\n\n- Ask a useful question.\n- Record the agreed assumptions.", 'category' => 'Planning & budgets', 'byline' => 'business', 'rights' => 1, 'intent' => 'submit'], $extra);
    }

    private function submit(Business $b, User $owner, array $extra = []): BlogPost
    {
        $this->actingAs($owner)->post('/workspace/blog', $this->articleData($extra))->assertRedirect();

        return BlogPost::withoutGlobalScope('business')->where('business_id', $b->id)->latest('id')->firstOrFail()->load('latestRevision');
    }

    private function approve(BlogPost $p, ?User $admin = null, ?string $date = null): void
    {
        $this->actingAs($admin ?? $this->admin())->patch('/admin/blog/'.$p->id.'/revisions/'.$p->latestRevision->id.'/review', ['decision' => 'approve', 'publish_at' => $date])->assertRedirect();
    }

    private function guest(): void
    {
        auth()->logout();
    }

    private function image(string $name = 'cover.webp'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('assets/interiors/hero.webp')));
    }

    private function video(string $name = 'video.mp4'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('assets/reels/warm-reference.mp4')))->mimeType('video/mp4');
    }

    private function reelData(array $extra = []): array
    {
        return array_replace(['title' => 'Synthetic studio video', 'caption' => 'A clearly classified synthetic motion study. No real customer data.', 'kind' => 'concept', 'category' => 'Living', 'associated_url' => 'https://en.wikipedia.org/wiki/Interior_design', 'video' => $this->video(), 'cover' => $this->image(), 'status' => 'draft', 'rights' => 1], $extra);
    }

    private function upload(Business $b, User $owner, string $status = 'published'): Reel
    {
        $this->actingAs($owner)->post('/workspace/reels', $this->reelData(['status' => $status]))->assertSessionHasNoErrors()->assertRedirect();

        return Reel::withoutGlobalScope('business')->where('business_id', $b->id)->latest('id')->firstOrFail();
    }

    private function brief(array $extra = []): array
    {
        return array_replace(['name' => 'Synthetic Content Client', 'phone' => '+91 9000000000', 'city' => 'Noida', 'locality' => 'Synthetic locality', 'floor_area' => 1200, 'property_type' => 'Apartment', 'configuration' => '2 BHK', 'scope' => 'Full-home interiors', 'budget' => '₹10–20 lakh', 'readiness' => 'Within 1–3 months', 'brief' => 'A synthetic project brief only, never contact this example.', 'contact_consent' => 1, 'submission_key' => (string) Str::uuid()], $extra);
    }

    private function tracking(array $extra = []): array
    {
        return array_replace(['intent' => 'publish', 'note' => 'Synthetic test configuration', 'enabled' => 1, 'ga4_id' => 'G-TEST12345', 'custom_category' => 'marketing', 'scopes' => ['marketplace', 'studio', 'articles', 'reels']], $extra);
    }

    public function test_drafts_previews_revisions_and_uploads_are_tenant_isolated(): void
    {
        Storage::fake('local');
        [$a,$owner] = $this->studio('Studio A');
        [$b,$other] = $this->studio('Studio B');
        $post = $this->submit($a, $owner, ['intent' => 'draft', 'cover' => $this->image(), 'cover_alt' => 'Synthetic cover description']);
        $rev = $post->latestRevision;
        $this->actingAs($other)->get('/workspace/blog/'.$post->id.'/edit')->assertNotFound();
        $this->get('/workspace/blog/'.$post->id.'/preview')->assertNotFound();
        $this->patch('/workspace/blog/'.$post->id, $this->articleData(['base_revision_id' => $rev->id]))->assertNotFound();
        $this->get('/article-media/'.$post->id.'/'.$rev->id)->assertNotFound();
        $this->actingAs($owner)->get('/workspace/blog/'.$post->id.'/preview')->assertOk()->assertSee('Private saved-revision preview')->assertHeader('Cache-Control', 'no-store, private');
        $this->guest();
        $this->get($post->public_url)->assertNotFound();
        $this->get('/article-media/'.$post->id.'/'.$rev->id)->assertNotFound();
        $this->get('/blog')->assertDontSee($rev->title);
        $this->assertSame(0, BlogPost::count());
        $this->assertSame(0, CampaignLink::count());
    }

    public function test_business_article_requires_review_and_shows_an_authorised_name_not_email(): void
    {
        [$b,$owner] = $this->studio();
        $post = $this->submit($b, $owner, ['byline' => 'user:'.$owner->id]);
        $this->guest();
        $this->get($post->public_url)->assertNotFound();
        $this->approve($post);
        $this->guest();
        $this->get($post->public_url)->assertOk()->assertSee('By '.$owner->name.' · '.$b->name)->assertDontSee($owner->email)->assertSee('BlogPosting')->assertSee('"@type":"Person"', false);
        $this->get('/blog')->assertOk()->assertSee($post->latestRevision->title);
        $this->get('/studios/'.$b->slug)->assertSee($post->latestRevision->title);
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.article_approved', 'business_id' => $b->id]);
    }

    public function test_scheduled_revision_faqs_match_visible_answers_and_json_ld_only_when_due(): void
    {
        [$studio, $owner] = $this->studio();
        $pair = ['question' => 'Which finish?', 'answer' => 'Oak & oil, not <paint>.'];
        $future = now()->addDay();
        $payload = $this->articleData(['intent' => 'schedule', 'publish_at' => $future->format('Y-m-d\TH:i'), 'faqs' => [$pair], 'faq_editor' => 1]);
        $this->actingAs($owner)->get('/workspace/blog/new')->assertOk()->assertSee('Add FAQ')->assertSee('Request scheduled publication');
        $this->postJson('/workspace/blog', ['faqs' => [['question' => 'Incomplete']]] + $payload)->assertJsonValidationErrors('faqs.0.answer');
        $this->postJson('/workspace/blog', ['publish_at' => null] + $payload)->assertJsonValidationErrors('publish_at');
        $post = $this->submit($studio, $owner, $payload);
        $this->assertSame('submitted', $post->latestRevision->status);
        $this->assertEquals([$pair], $post->latestRevision->faqs);
        $this->approve($post);
        $this->guest();
        $this->get($post->public_url)->assertNotFound();
        $this->travelTo($future->addMinute());
        $page = $this->get($post->public_url)->assertOk()->assertSee('<summary>Which finish?</summary>', false)->assertSee($pair['answer']);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $page->getContent(), $matches);
        $nodes = collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR)['@graph'])->keyBy('@type');
        $this->assertSame($pair['question'], $nodes['FAQPage']['mainEntity'][0]['name']);
        $this->assertSame($pair['answer'], $nodes['FAQPage']['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertStringContainsString('Ask a useful question.', $nodes['BlogPosting']['articleBody']);
        $this->actingAs($owner)->patch('/workspace/blog/'.$post->id, ['base_revision_id' => $post->latestRevision->id, 'intent' => 'draft', 'faqs' => []] + $payload)->assertSessionHasNoErrors();
        $this->assertSame([], $post->revisions()->latest('id')->firstOrFail()->faqs);
        $this->guest();
        $this->get($post->public_url)->assertSee('Which finish?');
    }

    public function test_unapproved_edits_do_not_replace_a_live_article_and_scheduling_is_automatic_when_due(): void
    {
        [$b,$owner] = $this->studio();
        $admin = $this->admin();
        $post = $this->submit($b, $owner);
        $this->approve($post, $admin);
        $old = $post->latestRevision;
        $this->actingAs($owner)->patch('/workspace/blog/'.$post->id, $this->articleData(['title' => 'The pending replacement', 'base_revision_id' => $old->id]))->assertRedirect();
        $post->refresh()->load('latestRevision');
        $this->guest();
        $this->get($post->public_url)->assertSee($old->title)->assertDontSee('The pending replacement');
        $future = now()->addHours(2);
        $this->approve($post, $admin, $future->format('Y-m-d\TH:i'));
        $this->guest();
        $this->get($post->public_url)->assertSee($old->title);
        $this->travelTo($future->addMinute());
        $this->get($post->public_url)->assertSee('The pending replacement')->assertDontSee('<h1>'.$old->title.'</h1>', false);
    }

    public function test_new_scheduled_articles_are_hidden_and_unpublication_is_not_undone_by_time(): void
    {
        [$b,$owner] = $this->studio();
        $admin = $this->admin();
        $post = $this->submit($b, $owner);
        $future = now()->addDay();
        $this->approve($post, $admin, $future->format('Y-m-d\TH:i'));
        $this->guest();
        $this->get($post->public_url)->assertNotFound();
        $this->actingAs($admin)->patch('/admin/blog/'.$post->id.'/unpublish', ['reason' => 'Synthetic moderation reason'])->assertRedirect();
        $this->travelTo($future->addHour());
        $this->guest();
        $this->get($post->public_url)->assertNotFound();
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.article_unpublished', 'business_id' => $b->id]);
    }

    public function test_review_reason_and_revision_parent_are_validated(): void
    {
        [$a,$ownerA] = $this->studio('A');
        [$b,$ownerB] = $this->studio('B');
        $post = $this->submit($a, $ownerA);
        $foreign = $this->submit($b, $ownerB);
        $admin = $this->admin();
        $this->actingAs($admin)->patchJson('/admin/blog/'.$post->id.'/revisions/'.$foreign->latestRevision->id.'/review', ['decision' => 'approve'])->assertNotFound();
        $this->patchJson('/admin/blog/'.$post->id.'/revisions/'.$post->latestRevision->id.'/review', ['decision' => 'changes'])->assertUnprocessable()->assertJsonValidationErrors('review_note');
        $this->patch('/admin/blog/'.$post->id.'/revisions/'.$post->latestRevision->id.'/review', ['decision' => 'changes', 'review_note' => 'Explain the material assumptions.'])->assertRedirect();
        $this->actingAs($ownerA)->get('/workspace/blog')->assertSee('Explain the material assumptions.');
    }

    public function test_foreign_or_inactive_public_authors_and_stale_editor_writes_are_rejected(): void
    {
        [$a,$ownerA] = $this->studio('A');
        [$b,$ownerB] = $this->studio('B');
        $this->actingAs($ownerA)->postJson('/workspace/blog', $this->articleData(['byline' => 'user:'.$ownerB->id]))->assertUnprocessable()->assertJsonValidationErrors('byline');
        $post = $this->submit($a, $ownerA);
        $this->patchJson('/workspace/blog/'.$post->id, $this->articleData(['base_revision_id' => 999999]))->assertUnprocessable();
        $this->assertDatabaseCount('blog_revisions', 1);
    }

    public function test_markdown_and_metadata_do_not_inject_scripts_images_or_iframes(): void
    {
        [$b,$owner] = $this->studio();
        $post = $this->submit($b, $owner, ['seo_title' => '</title><script>alert(42)</script>', 'body' => "## A safe question\n\nThis synthetic body tests rendering and removes remote inline images and executable embeds.\n\n<script>alert('unsafe')</script>\n\n<iframe src=\"https://example.org\"></iframe>\n\n![pixel](https://tracker.example/pixel.png)\n\n[unsafe](javascript:alert(1))"]);
        $this->approve($post);
        $this->guest();
        $r = $this->get($post->public_url)->assertOk()->assertDontSee('<iframe', false)->assertDontSee('<script>alert(42)</script>', false)->assertDontSee('src="https://tracker.example', false)->assertDontSee('href="javascript:', false)->assertSee('&lt;/title&gt;&lt;script&gt;alert(42)&lt;/script&gt;', false);
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $r->getContent(), $m);
        $this->assertNotEmpty($m[1]);
        $this->assertIsArray(json_decode($m[1][0], true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_platform_editorial_can_be_published_only_by_the_admin(): void
    {
        [$b,$owner] = $this->studio();
        $this->actingAs($owner)->post('/admin/blog', $this->articleData(['byline' => 'editorial', 'intent' => 'publish']))->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/blog', $this->articleData(['byline' => 'editorial', 'intent' => 'publish']))->assertRedirect();
        $post = BlogPost::withoutGlobalScope('business')->whereNull('business_id')->firstOrFail();
        $this->guest();
        $this->get($post->public_url)->assertOk()->assertSee('By Spacekind editorial')->assertSee('"@type":"Organization"', false);
        $this->actingAs($admin)->get('/admin/blog/new')->assertOk();
        $this->get('/admin/blog/'.$post->id.'/edit')->assertOk();
    }

    public function test_uploaded_video_draft_and_cover_are_private_then_playable_when_published(): void
    {
        Storage::fake('local');
        [$b,$owner] = $this->studio();
        $reel = $this->upload($b, $owner, 'draft');
        Storage::disk('local')->assertExists($reel->video_path);
        $this->guest();
        $this->get($reel->video_url)->assertNotFound();
        $this->get($reel->cover_url)->assertNotFound();
        $this->get($reel->public_url)->assertNotFound();
        $this->actingAs($owner)->get($reel->video_url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get('/workspace/reels/'.$reel->id.'/preview')->assertOk();
        $data = $this->reelData(['status' => 'published']);
        unset($data['video'],$data['cover']);
        $this->patch('/workspace/reels/'.$reel->id, $data)->assertRedirect();
        $this->guest();
        $this->get($reel->public_url)->assertOk()->assertSee('Open associated link')->assertSee('controls', false);
        $this->get($reel->video_url)->assertOk()->assertHeader('Content-Type', 'video/mp4')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_cross_tenant_video_edits_previews_deletes_and_media_reads_fail(): void
    {
        Storage::fake('local');
        [$a,$ownerA] = $this->studio('A');
        [$b,$ownerB] = $this->studio('B');
        $reel = $this->upload($a, $ownerA, 'draft');
        $this->actingAs($ownerB)->get('/workspace/reels/'.$reel->id.'/edit')->assertNotFound();
        $this->get('/workspace/reels/'.$reel->id.'/preview')->assertNotFound();
        $this->get($reel->video_url)->assertNotFound();
        $this->patch('/workspace/reels/'.$reel->id, [])->assertNotFound();
        $this->delete('/workspace/reels/'.$reel->id)->assertNotFound();
        Storage::disk('local')->assertExists($reel->video_path);
    }

    public function test_video_mime_codec_url_rights_and_server_verifier_fail_closed(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        [$b,$owner] = $this->studio();
        $this->actingAs($owner)->postJson('/workspace/reels', $this->reelData(['video' => UploadedFile::fake()->createWithContent('fake.mp4', '<script>not a video</script>')]))->assertUnprocessable()->assertJsonValidationErrors('video');
        foreach (['javascript:alert(1)', 'http://example.org', 'https://127.0.0.1/', 'https://127.0.0.1./', 'https://user:secret@example.org/', 'https://foo.local/path'] as $url) {
            $this->postJson('/workspace/reels', $this->reelData(['associated_url' => $url]))->assertUnprocessable()->assertJsonValidationErrors('associated_url');
        }
        $this->postJson('/workspace/reels', $this->reelData(['rights' => 0]))->assertUnprocessable()->assertJsonValidationErrors('rights');
        config(['spacekind.ffprobe_binary' => '/definitely/not/a/verifier']);
        $this->postJson('/workspace/reels', $this->reelData())->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->assertDatabaseCount('reels', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_video_quota_failed_writes_leave_no_orphan_files_and_deletion_removes_uploads(): void
    {
        Storage::fake('local');
        [$b,$owner] = $this->studio();
        config(['spacekind.video_bytes_limit' => 1]);
        $this->actingAs($owner)->postJson('/workspace/reels', $this->reelData())->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles());
        config(['spacekind.video_bytes_limit' => 104857600]);
        $reel = $this->upload($b, $owner);
        $this->delete('/workspace/reels/'.$reel->id)->assertRedirect();
        Storage::disk('local')->assertMissing($reel->video_path);
        Storage::disk('local')->assertMissing($reel->cover_path);
    }

    public function test_admin_video_hold_cannot_be_removed_by_the_owner(): void
    {
        Storage::fake('local');
        [$b,$owner] = $this->studio();
        $reel = $this->upload($b, $owner);
        $admin = $this->admin();
        $this->actingAs($admin)->patch('/admin/reels/'.$reel->id.'/moderate', ['action' => 'block', 'reason' => 'Synthetic moderation review'])->assertRedirect();
        $this->guest();
        $this->get($reel->video_url)->assertNotFound();
        $data = $this->reelData(['status' => 'published', 'blocked_at' => null]);
        unset($data['video'],$data['cover']);
        $this->actingAs($owner)->patch('/workspace/reels/'.$reel->id, $data)->assertRedirect();
        $this->guest();
        $this->get($reel->public_url)->assertNotFound();
        $this->actingAs($admin)->patch('/admin/reels/'.$reel->id.'/moderate', ['action' => 'restore', 'reason' => 'Synthetic rights review complete'])->assertRedirect();
        $this->guest();
        $this->get($reel->public_url)->assertOk();
    }

    public function test_pending_and_suspended_studios_have_no_public_articles_or_videos(): void
    {
        Storage::fake('local');
        [$b,$owner] = $this->studio();
        $post = $this->submit($b, $owner);
        $this->approve($post);
        $reel = $this->upload($b, $owner);
        $b->forceFill(['approval_status' => 'pending'])->save();
        $this->guest();
        $this->get($post->public_url)->assertNotFound();
        $this->get($reel->video_url)->assertNotFound();
        $b->forceFill(['approval_status' => 'approved', 'account_status' => 'suspended'])->save();
        $this->get($post->public_url)->assertNotFound();
        $this->get($reel->public_url)->assertNotFound();
        $this->actingAs($owner)->get('/workspace/reels')->assertForbidden();
    }

    public function test_campaign_destinations_are_owned_and_labels_cannot_contain_obvious_pii(): void
    {
        [$a,$ownerA] = $this->studio('A');
        [$b,$ownerB] = $this->studio('B');
        $post = $this->submit($b, $ownerB);
        $this->approve($post);
        $data = ['name' => 'Synthetic campaign', 'destination' => 'article:'.$post->id, 'utm_source' => 'instagram', 'utm_medium' => 'organic_social', 'utm_campaign' => 'warm_homes'];
        $this->actingAs($ownerA)->postJson('/workspace/campaign-links', $data)->assertUnprocessable()->assertJsonValidationErrors('destination');
        $data['destination'] = 'https://attacker.example';
        $this->postJson('/workspace/campaign-links', $data)->assertUnprocessable();
        $data['destination'] = 'profile';
        $data['utm_campaign'] = 'person@example.test';
        $this->postJson('/workspace/campaign-links', $data)->assertUnprocessable()->assertJsonValidationErrors('utm_campaign');
        $data['utm_campaign'] = '9000000000';
        $this->postJson('/workspace/campaign-links', $data)->assertUnprocessable();
        $data['utm_campaign'] = 'warm_homes';
        $data['business_id'] = $b->id;
        $this->post('/workspace/campaign-links', $data)->assertRedirect();
        $link = CampaignLink::withoutGlobalScope('business')->firstOrFail();
        $this->assertSame($a->id, $link->business_id);
        $this->assertStringStartsWith(url('/studios/'.$a->slug).'?', $link->share_url);
        $this->actingAs($ownerB)->delete('/workspace/campaign-links/'.$link->id)->assertNotFound();
    }

    public function test_reel_campaign_context_is_preserved_only_with_permission_and_survives_conversion(): void
    {
        Storage::fake('local');
        [$b,$owner] = $this->studio();
        $reel = $this->upload($b, $owner);
        $this->guest();
        $query = '?utm_source=instagram&utm_medium=organic_social&utm_campaign=synthetic_motion';
        $this->get($reel->public_url.$query)->assertOk()->assertSee('entry_context=reel%3A'.$reel->id, false);
        $this->get('/studios/'.$b->slug.'/enquire'.$query.'&entry_context=reel:'.$reel->id)->assertSee('name="entry_context"', false);
        $labels = ['utm_source' => 'instagram', 'utm_medium' => 'organic_social', 'utm_campaign' => 'synthetic_motion', 'entry_context' => 'reel:'.$reel->id];
        $this->post('/studios/'.$b->slug.'/enquire', $this->brief($labels))->assertRedirect();
        $no = Lead::withoutGlobalScope('business')->latest('id')->firstOrFail();
        $this->assertNull($no->entry_context);
        $this->assertNull($no->utm);
        $this->post('/studios/'.$b->slug.'/enquire', $this->brief($labels + ['attribution_consent' => 1]))->assertRedirect();
        $lead = Lead::withoutGlobalScope('business')->latest('id')->firstOrFail();
        $this->assertSame($reel->id, $lead->entry_context['id']);
        $this->assertSame('reel', $lead->entry_context['kind']);
        $this->assertArrayHasKey('recorded_at', $lead->entry_context);
        $this->actingAs($owner)->post('/workspace/enquiries/'.$lead->id.'/convert')->assertRedirect();
        $client = app(Tenant::class)->run($b, fn () => Client::firstOrFail());
        $this->assertSame($lead->entry_context, $client->entry_context);
    }

    public function test_foreign_entry_context_is_not_retained_and_campaign_report_does_not_invent_visits(): void
    {
        Storage::fake('local');
        [$a,$ownerA] = $this->studio('A');
        [$b,$ownerB] = $this->studio('B');
        $video = $this->upload($b, $ownerB);
        $this->guest();
        $this->post('/studios/'.$a->slug.'/enquire', $this->brief(['entry_context' => 'reel:'.$video->id, 'attribution_consent' => 1, 'utm_source' => 'instagram', 'utm_medium' => 'organic_social', 'utm_campaign' => 'synthetic']))->assertRedirect();
        $lead = Lead::withoutGlobalScope('business')->latest('id')->firstOrFail();
        $this->assertNull($lead->entry_context);
        $this->actingAs($ownerA)->post('/workspace/campaign-links', ['name' => 'Synthetic report', 'destination' => 'profile', 'utm_source' => 'instagram', 'utm_medium' => 'organic_social', 'utm_campaign' => 'synthetic'])->assertRedirect();
        $this->get('/workspace/campaign-links')->assertOk()->assertSee('Matching-label enquiries')->assertSee('No visit counts')->assertSee('Recorded Won');
        $token = $ownerA->createToken('content-test', ['workspace'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/campaign-links')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_staff_cannot_manage_content_links_or_global_settings(): void
    {
        [$b,$owner] = $this->studio();
        $staff = User::factory()->create();
        $staff->forceFill(['business_id' => $b->id, 'role' => 'staff'])->save();
        $this->actingAs($staff);
        foreach (['/workspace/blog', '/workspace/reels', '/workspace/campaign-links', '/workspace/tools', '/admin/blog', '/admin/reels', '/admin/discovery', '/admin/tracking'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/workspace/blog', [])->assertForbidden();
        $this->post('/admin/tracking', [])->assertForbidden();
    }

    public function test_tracking_versions_are_draft_or_explicitly_published_and_demo_delivery_stays_off(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/tracking', $this->tracking(['intent' => 'draft']))->assertRedirect();
        $this->assertNull(TrackingVersion::where('status', 'published')->first());
        $this->post('/admin/tracking', $this->tracking())->assertRedirect();
        $published = TrackingVersion::where('status', 'published')->latest('id')->firstOrFail();
        $this->guest();
        $this->get('/')->assertOk()->assertDontSee('G-TEST12345')->assertDontSee('googletagmanager.com')->assertSee('consent.js');
        $this->actingAs($admin)->post('/admin/tracking/disable')->assertRedirect();
        $this->assertFalse(TrackingVersion::latest('id')->first()->settings['enabled']);
        $this->post('/admin/tracking/'.$published->id.'/restore')->assertRedirect();
        $this->assertTrue(TrackingVersion::latest('id')->first()->settings['enabled']);
        $this->get('/admin/tracking')->assertOk()->assertSee('Version history')->assertSee('deployment-locked');
    }

    public function test_clarity_needs_a_valid_public_id_and_uses_the_analytics_gate(): void
    {
        $payload = $this->tracking(['clarity_id' => 'ab123cdef', 'ga4_id' => null, 'meta_pixel_id' => null]);
        $this->actingAs($this->admin())->postJson('/admin/tracking', ['clarity_id' => 'https://example.test'] + $payload)->assertJsonValidationErrors('clarity_id');
        $this->postJson('/admin/tracking', ['gtm_id' => 'GTM-TEST123'] + $payload)->assertJsonValidationErrors('gtm_id');
        $this->post('/admin/tracking', $payload)->assertSessionHasNoErrors();
        $this->guest();
        $this->get('/')->assertDontSee('ab123cdef');
        config(['spacekind.demo' => false, 'spacekind.tracking_allowed' => true]);
        $this->app['env'] = 'production';
        $home = $this->get('/')->assertOk()->assertSee('ab123cdef');
        $this->assertStringContainsString('https://www.clarity.ms', $home->headers->get('Content-Security-Policy'));
        $this->get('/?q=private')->assertDontSee('ab123cdef');
        $this->get('/login')->assertDontSee('ab123cdef');
    }

    public function test_duplicate_or_unapproved_tracking_scripts_cannot_be_saved(): void
    {
        $this->actingAs($this->admin())->postJson('/admin/tracking', $this->tracking(['gtm_id' => 'GTM-TEST123']))->assertUnprocessable()->assertJsonValidationErrors('gtm_id');
        $this->postJson('/admin/tracking', $this->tracking(['custom_name' => 'Unreviewed script', 'custom_script_url' => 'https://unreviewed.example/tracker.js']))->assertUnprocessable()->assertJsonValidationErrors('custom_script_url');
        config(['spacekind.approved_script_hosts' => ['reviewed.example']]);
        $this->postJson('/admin/tracking', $this->tracking(['custom_name' => 'Reviewed script', 'custom_script_url' => 'https://reviewed.example/tracker.js?email=person']))->assertUnprocessable();
        $this->post('/admin/tracking', $this->tracking(['custom_name' => 'Reviewed script', 'custom_script_url' => 'https://reviewed.example/tracker.js']))->assertRedirect();
        $this->assertDatabaseCount('tracking_versions', 1);
    }

    public function test_tracking_scope_and_csp_remain_private_even_when_deployment_is_authorised(): void
    {
        [$b,$owner] = $this->studio();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/tracking', $this->tracking())->assertRedirect();
        $this->guest();
        config(['spacekind.demo' => false, 'spacekind.tracking_allowed' => true]);
        $this->app['env'] = 'production';
        $home = $this->get('/')->assertOk()->assertSee('G-TEST12345');
        $this->assertStringContainsString('https://www.googletagmanager.com', $home->headers->get('Content-Security-Policy'));
        $query = $this->get('/?q=private-search')->assertOk()->assertDontSee('G-TEST12345');
        $this->assertStringNotContainsString('googletagmanager.com', $query->headers->get('Content-Security-Policy'));
        foreach (['/login', '/register', '/studios/'.$b->slug.'/enquire'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('/assets/consent.js')->assertDontSee('G-TEST12345');
        }
        $private = $this->actingAs($owner)->get('/workspace')->assertOk()->assertDontSee('consent.js')->assertDontSee('G-TEST12345');
        $this->assertStringNotContainsString('googletagmanager.com', $private->headers->get('Content-Security-Policy'));
        $this->get('/')->assertDontSee('G-TEST12345');
    }

    public function test_discovery_defaults_indexation_gates_and_schema_exclude_private_content(): void
    {
        [$b,$owner] = $this->studio();
        $admin = $this->admin();
        $post = $this->submit($b, $owner);
        $this->approve($post, $admin);
        $draft = $this->submit($b, $owner, ['intent' => 'draft']);
        $this->actingAs($admin)->patch('/admin/discovery', array_replace(Discovery::DEFAULTS, ['home_title' => 'Spacekind — A clearer collection', 'indexing_enabled' => 1]))->assertRedirect();
        $this->guest();
        $this->get('/')->assertSee('Spacekind — A clearer collection')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        config(['spacekind.demo' => false, 'spacekind.allow_indexing' => true]);
        $this->get('/sitemap.xml')->assertSee(url($post->public_url), false)->assertDontSee(url($draft->public_url), false)->assertDontSee('/workspace')->assertDontSee('/enquire');
        $this->get('/robots.txt')->assertSee('Sitemap:');
        $this->get('/studios/'.$b->slug)->assertSee('ProfessionalService')->assertDontSee('aggregateRating');
        $this->get('/?q=studio')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_tracking_history_has_deterministic_newest_first_order_when_created_in_one_second(): void
    {
        $admin = $this->admin();
        $this->freezeTime();
        $first = TrackingVersion::create(['user_id' => $admin->id, 'status' => 'draft', 'note' => 'Synthetic first history entry', 'settings' => ['enabled' => false, 'scopes' => []]]);
        $second = TrackingVersion::create(['user_id' => $admin->id, 'status' => 'published', 'note' => 'Synthetic second history entry', 'settings' => ['enabled' => false, 'scopes' => []]]);
        $response = $this->actingAs($admin)->get('/admin/tracking')->assertOk();
        preg_match_all('/<strong>#([0-9]+) ·/', $response->getContent(), $matches);
        $this->assertSame([(string) $second->id, (string) $first->id], $matches[1]);
    }

    public function test_all_new_owner_and_admin_screens_render_without_embedded_marketing_code(): void
    {
        [$b,$owner] = $this->studio();
        $this->actingAs($owner);
        foreach (['/workspace/tools', '/workspace/blog', '/workspace/blog/new', '/workspace/reels', '/workspace/campaign-links'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('/assets/consent.js')->assertDontSee('googletagmanager.com');
        }
        $this->actingAs($this->admin());
        foreach (['/admin/blog', '/admin/blog/new', '/admin/reels', '/admin/discovery', '/admin/tracking'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('<script src="https://',false)->assertDontSee('/assets/consent.js');
        }
    }
}
