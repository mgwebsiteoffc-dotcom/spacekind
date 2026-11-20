<?php

use App\Services\PreviewState;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Delete only the precisely named Phase 1B synthetic browser fixtures. Never production. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! config('spacekind.demo') || ! app()->environment('local')) {
    fwrite(STDERR, "Refusing cleanup outside local demo mode.\n");
    exit(1);
}
$studio = DB::table('businesses')->where('slug', 'studio-aangan')->where('name', 'Studio Aangan')->first();
$admin = DB::table('users')->where('email', getenv('SPACEKIND_QA_ADMIN_EMAIL') ?: '')->where('role', 'super_admin')->first();
if (! $studio || ! $admin) {
    fwrite(STDERR, "The expected fictional demo identities are missing.\n");
    exit(1);
}
$statePath = __DIR__.'/phase1b-journey-progress.json';
$state = is_file($statePath) ? json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR) : [];
$versionIds = array_values(array_filter($state['fixtures']['tracking_versions'] ?? [], fn ($v) => is_int($v) && $v > 0));
$qaRoots = DB::table('tracking_versions')->where('user_id', $admin->id)->where('note', 'like', 'QA Phase1B Tracking %')->pluck('id')->all();
$versionIds = array_unique(array_merge($versionIds, $qaRoots, DB::table('tracking_versions')->where('user_id', $admin->id)->whereIn('note', array_map(fn ($id) => 'Explicit rollback to configuration #'.$id, $qaRoots))->pluck('id')->all()));
$versions = DB::table('tracking_versions')->where('user_id', $admin->id)->whereIn('id', $versionIds)->get()->filter(fn ($v) => str_starts_with($v->note, 'QA Phase1B Tracking ') || $v->note === 'Emergency disable from the admin panel' || preg_match('/^Explicit rollback to configuration #[0-9]+$/', $v->note));
$leads = DB::table('leads')->where('business_id', $studio->id)->where('name', 'QA Phase1B Client')->where('brief', 'QA Phase1B synthetic brief only, with no real client data.')->get();
$leadIds = $leads->pluck('id')->all();
$postIds = DB::table('blog_posts')->where('business_id', $studio->id)->where('slug', 'qa-phase1b-article')->pluck('id')->all();
$covers = DB::table('blog_revisions')->whereIn('blog_post_id', $postIds)->pluck('cover_path')->filter()->unique()->all();
$reels = DB::table('reels')->where('business_id', $studio->id)->where('title', 'QA Phase1B Video')->where('caption', 'A synthetic upload and playback test. No client data and no claim of completed work.')->get();
$links = DB::table('campaign_links')->where('business_id', $studio->id)->where('name', 'QA Phase1B Campaign')->get();
$subjects = array_merge(array_map(fn ($v) => 'Lead:'.$v, $leadIds), array_map(fn ($v) => 'BlogPost:'.$v, $postIds), $reels->map(fn ($v) => 'Reel:'.$v->id)->all(), $links->map(fn ($v) => 'CampaignLink:'.$v->id)->all(), $versions->map(fn ($v) => 'TrackingVersion:'.$v->id)->all());
DB::transaction(function () use ($leadIds, $postIds, $reels, $links, $versions, $subjects) {
    DB::table('projects')->whereIn('lead_id', $leadIds)->delete();
    DB::table('clients')->whereIn('lead_id', $leadIds)->delete();
    DB::table('lead_activities')->whereIn('lead_id', $leadIds)->delete();
    DB::table('leads')->whereIn('id', $leadIds)->delete();
    DB::table('blog_revisions')->whereIn('blog_post_id', $postIds)->delete();
    DB::table('blog_posts')->whereIn('id', $postIds)->delete();
    DB::table('campaign_links')->whereIn('id', $links->pluck('id')->all())->delete();
    DB::table('reels')->whereIn('id', $reels->pluck('id')->all())->delete();
    DB::table('tracking_versions')->whereIn('id', $versions->pluck('id')->all())->delete();
    DB::table('audit_events')->whereIn('subject', $subjects)->delete();
});
foreach ($leads as $lead) {
    if ($lead->plan_path && str_starts_with($lead->plan_path, 'plans/'.$studio->id.'/')) {
        Storage::disk('local')->delete($lead->plan_path);
    }
}
foreach ($covers as $path) {
    if (str_starts_with($path, 'articles/'.$studio->id.'/') && ! DB::table('blog_revisions')->where('cover_path', $path)->exists()) {
        Storage::disk('local')->delete($path);
    }
}
foreach ($reels as $reel) {
    if ($reel->disk === 'local') {
        foreach ([$reel->video_path, $reel->cover_path] as $path) {
            if (str_starts_with($path, 'reels/'.$studio->id.'/')) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
app(PreviewState::class)->save();
echo json_encode(['removed' => ['leads' => count($leadIds), 'articles' => count($postIds), 'videos' => $reels->count(), 'links' => $links->count(), 'tracking_versions' => $versions->count()]], JSON_PRETTY_PRINT).PHP_EOL;
