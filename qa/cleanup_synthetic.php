<?php

use App\Services\PreviewState;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Removes ONLY the exact synthetic records created by check_journey.py. Local demo only.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! config('spacekind.demo') || ! app()->environment('local')) {
    fwrite(STDERR, "Refusing cleanup outside local demo mode.\n");
    exit(1);
}
$leadRows = DB::table('leads')->where('name', 'QA Synthetic Journey')->where('email', 'qa-journey@example.test')->get();
$leadIds = $leadRows->pluck('id')->all();
$gallery = DB::table('gallery_images')->where('caption', 'QA synthetic gallery concept')->get();
$user = DB::table('users')->where('email', 'qa-owner@example.test')->where('name', 'QA Synthetic Owner')->first();
$business = $user ? DB::table('businesses')->where('id', $user->business_id)->where('name', 'QA Synthetic Studio')->where('slug', 'qa-synthetic-studio')->first() : null;
DB::transaction(function () use ($leadIds, $gallery, $user, $business) {
    if ($leadIds) {
        DB::table('projects')->whereIn('lead_id', $leadIds)->delete();
        DB::table('clients')->whereIn('lead_id', $leadIds)->delete();
        DB::table('lead_activities')->whereIn('lead_id', $leadIds)->delete();
        DB::table('leads')->whereIn('id', $leadIds)->delete();
        DB::table('audit_events')->whereIn('subject', array_map(fn ($id) => 'Lead:'.$id, $leadIds))->delete();
    }
    foreach ($gallery as $image) {
        DB::table('gallery_images')->where('id', $image->id)->delete();
    }
    DB::table('personal_access_tokens')->where('name', 'qa-journey')->delete();
    if ($business) {
        DB::table('audit_events')->where('business_id', $business->id)->delete();
        DB::table('subscriptions')->where('business_id', $business->id)->delete();
        DB::table('users')->where('id', $user->id)->delete();
        DB::table('businesses')->where('id', $business->id)->delete();
    }
});
foreach ($leadRows as $lead) {
    if ($lead->plan_path) {
        Storage::disk('local')->delete($lead->plan_path);
    }
}
foreach ($gallery as $image) {
    if ($image->disk === 'local') {
        Storage::disk('local')->delete($image->path);
    }
}
app(PreviewState::class)->save();
echo 'Removed '.count($leadIds).' synthetic QA enquiry record(s) and the exact QA registration fixture, if present. Demo studios remain unchanged.'.PHP_EOL;
