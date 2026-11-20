<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Business;
use App\Models\CampaignLink;
use App\Models\Reel;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Explicitly illustrative content, not customer case studies, portfolio proof or paid ad results. */
class ContentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('spacekind.demo') || app()->environment('production')) {
            $this->command?->warn('Content demo seeding refused outside demo mode.');

            return;
        }
        $admin = User::where('email', 'admin@spacekind.example')->firstOrFail();
        $aangan = Business::where('slug', 'studio-aangan')->firstOrFail();
        $form = Business::where('slug', 'form-grain')->firstOrFail();
        Auth::setUser($admin);
        try {
            $this->article(null, $admin, 'before-the-moodboard', 'Before the moodboard, a clearer project brief.', 'A useful interior brief starts with the way you live—not just a saved image. A few practical answers help a studio understand your priorities.', 'Planning & budgets', 'hero', <<<'MD'
This is a development editorial example, not a client case study. The images are visual references.

## What should I tell an interior studio first?
Start with the location and approximate floor area, the kind of property, what you want to change, your budget range and the date you hope to begin. An approximate answer is more useful than a blank brief.

A saved moodboard can explain a feeling. It cannot tell a designer how many people use the kitchen, where everyday clutter gathers or whether the home must stay occupied during work.

## What belongs in a useful brief?
- The spaces and functions that matter most.
- Existing items you want to retain.
- Practical needs such as storage, accessibility or maintenance.
- A realistic budget range and any exclusions you already know.
- Your possession date and whether the schedule is flexible.

## Do I need a final plan before asking?
No. Share what you know, and mark the assumptions that still need a site visit or a professional discussion. A floor plan can help, but it should be shared privately with the selected studio—not uploaded as public portfolio content.

## How should I compare studios?
Look at their approach, confirm which images are completed work versus renders or concepts, and discuss scope, responsibilities, fees and agreements directly. A directory listing is a starting point for a conversation, not a guarantee of fit.
MD);
            $this->article(null, $admin, 'a-source-label-is-a-clue', 'A source label is a clue. Not a customer.', 'Campaign labels help you keep context with an enquiry. They do not, on their own, prove that an advertisement produced a paying customer.', 'For studios', 'office', <<<'MD'
This article explains how the Spacekind development tools label outcomes. It does not report the performance of a real studio or campaign.

## What does a UTM label tell a studio?
It records the campaign words that were available in a link and retained with permission. A label such as `organic_social` is useful context. It is not independent verification of where a person first discovered the business.

## Is an enquiry the same as a customer?
No. Keep a click, a submitted brief, a conversation, a studio-recorded Won outcome and a verified payment as different events. They answer different questions.

The small CRM keeps the source alongside notes and follow-ups. Converting the enquiry retains that context in the client record; it does not reconcile a bank payment.

## Why can the source be unknown?
Parameters may be removed, a visitor may change devices, or a contact may arrive directly through WhatsApp or a phone call. People may also decline optional label retention. Unknown is an honest result, not a field to fill with an assumption.

## What should a campaign link contain?
Use consistent source, medium and campaign labels. Keep names, contact details, addresses, budgets and private project information out of URLs. Put the link in a placement that actually supports clicking it.

Useful measurement starts with clear definitions, permission and consistent follow-through—not with a promise of perfect attribution.
MD);
            app(Tenant::class)->run($aangan, function () use ($aangan) {
                $owner = $aangan->users()->where('role', 'owner')->firstOrFail();
                $this->article($aangan, $owner, 'warm-materials-everyday-life', 'Warm materials. Room for everyday life.', 'An illustrative studio perspective on a calmer home: begin with practical routines, then choose materials and details that support them.', 'Materials & details', 'living', <<<'MD'
Studio Aangan is a fictional studio in this development preview. This is a sample article, not a description of a completed project.

## What makes a warm, minimal room work in everyday life?
Begin with function, comfortable circulation and places for the things you actually use. A restrained palette can feel calm, but practical decisions make the room usable.

Think about where bags land, where people sit together and which surfaces need to be easy to clean. Discuss material samples in the light of your own space rather than relying only on a screen.

## Does a natural-looking finish always mean natural material?
No. Names and images can be ambiguous. Ask your studio or supplier for the exact material, finish, maintenance requirements and what is included in the quoted scope. A reference image is not a specification.

## Where is a useful place to begin?
Choose a small set of priorities before collecting more inspiration. Storage, lighting and the items you want to keep often give the discussion more direction than a long list of aesthetic labels.

- Keep circulation clear and confirm measurements on site.
- Compare physical samples when possible.
- Ask what requires ongoing care.
- Record assumptions and exclusions in the agreed scope.

The aim is a home that feels considered and works for the people in it—not a promise that one palette suits every space.
MD);
                $this->reel('A little light. A warmer perspective.', 'warm-reference.mp4', 'living', 'Living');
                $labels = ['source' => 'instagram', 'medium' => 'paid_social', 'campaign' => 'warm_homes_demo'];
                CampaignLink::firstOrCreate(['fingerprint' => hash('sha256', '/studios/'.$aangan->slug.'|'.json_encode($labels))], ['name' => 'Illustrative · Warm homes · Existing CRM labels', 'destination_type' => 'profile', 'destination_path' => '/studios/'.$aangan->slug, 'utm' => $labels]);
            });
            app(Tenant::class)->run($form, function () use ($form) {
                $owner = $form->users()->where('role', 'owner')->firstOrFail();
                $this->article($form, $owner, 'a-kitchen-starts-with-questions', 'A practical kitchen starts with better questions.', 'An illustrative named-author studio article about routines, storage and the questions worth asking before choosing kitchen finishes.', 'Planning & budgets', 'kitchen', <<<'MD'
Form & Grain and its team are fictional development records. This named-author example is not a real project claim or a professional certification.

## What should I decide before choosing a kitchen finish?
Describe how the kitchen is used: who cooks, what needs to be stored, which appliances stay on the counter and how people move through the space. Those answers help shape a practical layout discussion.

## Why do exact measurements matter?
An image can suggest proportions but cannot confirm clearances, site conditions, plumbing or appliance requirements. Ask the appointed professionals to verify the actual site and relevant specifications before any work is committed.

## What should be written down?
Keep the agreed scope, material and hardware specifications, responsibilities, exclusions and assumptions visible to both sides. Confirm how a later change will affect cost and timing.

- List what you use every day.
- Mark what you want to keep.
- Ask about cleaning and maintenance.
- Compare samples, not only renders.

This is a starting point for a considered conversation, not a standard layout or budget that applies to every home.
MD, true);
                $this->reel('Material notes, in a little motion.', 'kitchen-reference.mp4', 'kitchen', 'Kitchen');
            });
        } finally {
            Auth::forgetGuards();
        }
        $this->command?->info('Illustrative articles, two reference motion studies and one sample UTM link are ready. No providers activated.');
    }

    private function article(?Business $business, User $actor, string $slug, string $title, string $excerpt, string $category, string $cover, string $body, bool $named = false): void
    {
        if (BlogPost::withoutGlobalScope('business')->where('slug', $slug)->exists()) {
            return;
        }
        $path = 'articles/demo/'.$slug.'.webp';
        Storage::disk('local')->put($path, file_get_contents(public_path('assets/interiors/'.$cover.'.webp')));
        $post = new BlogPost(['slug' => $slug]);
        $post->forceFill(['created_by' => $actor->id, 'is_visible' => true, 'visible_from' => now()->subDays(2)])->save();
        $post->revisions()->create(['created_by' => $actor->id, 'title' => $title, 'excerpt' => $excerpt, 'body' => $body, 'category' => $category, 'tags' => ['Development example', 'Thoughtful planning'], 'cover_path' => $path, 'cover_alt' => 'Interior visual reference for an explicitly illustrative Spacekind article.', 'byline_mode' => $named ? 'person' : 'business', 'author_id' => $named ? $actor->id : null, 'author_name' => $named ? $actor->name : ($business?->name ?? 'Spacekind editorial'), 'status' => 'approved', 'publish_at' => now()->subDays(2), 'reviewed_by' => User::where('email', 'admin@spacekind.example')->value('id'), 'reviewed_at' => now(), 'review_note' => 'Seeded illustrative development content, not customer work.']);
    }

    private function reel(string $title, string $file, string $cover, string $category): void
    {
        if (Reel::where('title', $title)->exists()) {
            return;
        }
        $reel = new Reel(['title' => $title, 'caption' => 'Development-only motion study made from reference imagery. Not a video of completed studio work. The associated URL is a general interior-design reference, not an image source or a claim that this video was posted there.', 'kind' => 'concept', 'category' => $category, 'associated_url' => 'https://en.wikipedia.org/wiki/Interior_design', 'position' => 1, 'status' => 'published']);
        $reel->forceFill(['video_path' => 'assets/reels/'.$file, 'cover_path' => 'assets/interiors/'.$cover.'.webp', 'disk' => 'public-assets', 'mime' => 'video/mp4', 'bytes' => filesize(public_path('assets/reels/'.$file)), 'duration' => 4, 'rights_confirmed_at' => now()])->save();
    }
}
