<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\GalleryImage;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LeadWorkflow;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InspirationSeeder::class);

        if (! config('spacekind.demo')) {
            $this->command?->warn('Demo content is disabled. No accounts or studios were created.');

            return;
        }
        $studios = [
            ['Studio Aangan', 'Noida', 'Sector 104', 'home', 12, 35, 'Aa', ['Warm minimal', 'Turnkey interiors'], ['living', 'hero', 'bedroom'], 'Warm materials, thoughtful details and a little breathing room. A fictional residential practice for exploring the Spacekind workspace. All portfolio images are design references, not this studio’s completed work.'],
            ['Form & Grain', 'Gurugram', 'DLF Phase 2', 'kitchen', 5, 18, 'fg', ['Modern naturals', 'Custom cabinetry'], ['kitchen', 'kitchenLight', 'living'], 'A considered approach to the rooms you use every day. This fictional studio demonstrates functional kitchens, crafted storage and natural finishes, using reference imagery only.'],
            ['The Quiet Studio', 'New Delhi', 'Greater Kailash', 'home', 18, 50, 'q.', ['Contemporary', 'Understated luxury'], ['classic', 'bedroom', 'hero'], 'A quieter point of view on contemporary homes. A fictional practice bringing together sculptural forms, balanced proportions and restrained palettes. Portfolio imagery is for reference only.'],
            ['Mitti Living', 'Noida', 'Sector 75', 'renovation', 8, 25, 'm', ['Earthy & timeless', 'Thoughtful renovation'], ['airy', 'hero', 'office'], 'New possibilities for a space you already call home. A fictional renovation specialist with an earthy, practical and personal design approach. No reference image is claimed as completed studio work.'],
            ['Northline Spaces', 'New Delhi', 'Saket', 'commercial', 15, 60, 'N', ['Workspaces', 'Functional design'], ['office', 'classic', 'kitchenLight'], 'Spaces shaped around the way people work. This fictional commercial practice shows how workspace specialists can present their approach and connect with new clients. All images are visual references.'],
            ['Oak & Arc', 'Ghaziabad', 'Indirapuram', 'home', 10, 30, 'oa', ['Soft minimal', 'Family-first spaces'], ['soft', 'bedroom', 'kitchen'], 'A comfortable balance of everyday practicality and softer details. A fictional family-focused residential practice, using clearly labelled reference images for its material studies.'],
        ];
        $owners = [];
        foreach ($studios as $i => $s) {
            [$name,$city,$locality,$service,$min,$max,$initials,$tags,$images,$bio] = $s;
            $business = Business::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'city' => $city, 'locality' => $locality, 'service' => $service, 'budget_min' => $min, 'budget_max' => $max, 'initials' => $initials, 'tags' => $tags, 'bio' => $bio, 'services' => [$service], 'cover_key' => $images[0]]);
            if ($business->wasRecentlyCreated) {
                $business->forceFill(['approval_status' => 'approved', 'is_demo' => true, 'reviewed_at' => now()])->save();
            }
            $email = $i === 0 ? 'owner@aangan.example' : 'owner@'.Str::slug($name).'.example';
            $owner = User::firstOrCreate(['email' => $email], ['name' => $i === 0 ? 'Aarav · Demo owner' : $name.' · Demo owner', 'password' => 'Spacekind@2026']);
            if (! $owner->business_id) {
                $owner->forceFill(['business_id' => $business->id, 'role' => 'owner'])->save();
            }
            $owners[] = $owner;
            app(Tenant::class)->run($business, function () use ($images) {
                Subscription::firstOrCreate([], ['status' => 'pilot', 'starts_at' => today()]);
                foreach ($images as $j => $key) {
                    GalleryImage::firstOrCreate(['path' => 'assets/interiors/'.$key.'.webp'], ['disk' => 'public-assets', 'kind' => 'reference', 'caption' => ['Materials & mood', 'Space & proportion', 'Everyday details'][$j].' · Visual reference only', 'position' => $j]);
                }
            });
        }
        $staff = User::firstOrCreate(['email' => 'team@aangan.example'], ['name' => 'Meera · Demo team', 'password' => 'Spacekind@2026']);
        $staff->forceFill(['business_id' => $owners[0]->business_id, 'role' => 'staff'])->save();
        $admin = User::firstOrCreate(['email' => 'admin@spacekind.example'], ['name' => 'Spacekind editorial', 'password' => Str::random(64)]);
        $admin->forceFill(['role' => 'super_admin', 'business_id' => null, 'is_active' => false, 'remember_token' => null])->save();
        $pending = Business::firstOrCreate(['slug' => 'atelier-draft'], ['name' => 'Atelier Draft', 'city' => 'Ghaziabad', 'locality' => 'Vasundhara', 'bio' => 'A fictional studio awaiting its first listing review. This record demonstrates manual platform approval.']);
        if ($pending->wasRecentlyCreated) {
            $pending->forceFill(['is_demo' => true])->save();
        }
        $aangan = $owners[0]->business;
        app(Tenant::class)->run($aangan, function () use ($owners, $staff) {
            $rows = [
                ['Ananya Sharma', 'Qualified', 'Noida', 'Sector 137', '₹10–20 lakh', 'Campaign link', 'Discuss material palette', -1],
                ['Rohan Mehta', 'New', 'Noida', 'Sector 150', '₹20–35 lakh', 'Marketplace', 'Call to understand scope', 0],
                ['Priya Kapoor', 'Site Visit', 'New Delhi', 'Greater Kailash', '₹10–20 lakh', 'Studio profile', 'Confirm site visit timing', 1],
                ['Kabir Sethi', 'Contacted', 'Ghaziabad', 'Indirapuram', 'Under ₹10 lakh', 'Referral', 'Share design approach', 0],
                ['Ishita Rao', 'Quote Sent', 'Noida', 'Sector 104', '₹20–35 lakh', 'Campaign link', 'Review quotation together', 2],
                ['Arjun Bhatia', 'Won', 'Noida', 'Sector 75', '₹10–20 lakh', 'Marketplace', null, null],
                ['Neha Jain', 'New', 'Ghaziabad', 'Vasundhara', '₹10–20 lakh', 'Studio profile', 'Understand possession timeline', 1],
                ['Dev Malhotra', 'Lost', 'New Delhi', 'Saket', 'Under ₹10 lakh', 'Phone call', null, null],
                ['Sana Ali', 'Qualified', 'Noida', 'Sector 76', '₹20–35 lakh', 'WhatsApp (manual)', 'Discuss site measurements', 3],
                ['Vikram Shah', 'Won', 'Noida', 'Sector 62', '₹10–20 lakh', 'Referral', null, null],
            ];
            foreach ($rows as $i => $row) {
                [$name,$stage,$city,$locality,$budget,$source,$task,$due] = $row;
                $lead = Lead::firstOrCreate(['name' => $name, 'is_demo' => true], [
                    'phone' => '90000000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'email' => Str::slug($name).'@example.test', 'city' => $city, 'locality' => $locality, 'floor_area' => [1650, 2200, 1450, 1100, 2400, 1800, 1500, 950, 2100, 1750][$i],
                    'property_type' => 'Apartment', 'configuration' => '3 BHK', 'scope' => 'Full-home interiors', 'budget' => $budget, 'readiness' => 'Within 1–3 months',
                    'brief' => 'Sample project brief for development: a thoughtful, practical home with warm natural finishes. Fictional contact details; do not contact.',
                    'stage' => $stage, 'source' => $source, 'utm' => $source === 'Campaign link' ? ['source' => 'instagram', 'medium' => 'paid_social', 'campaign' => 'warm_homes_demo'] : null,
                    'assigned_to' => $i % 3 === 0 ? $staff->id : $owners[0]->id, 'contact_consent_at' => now()->subDays($i + 1), 'attribution_consent_at' => $source === 'Campaign link' ? now()->subDays($i + 1) : null, 'consent_version' => 'demo-only',
                    'next_follow_up_at' => $due !== null ? today()->addDays($due)->setTime(11 + $i % 4, 0) : null, 'follow_up_task' => $task, 'lost_reason' => $stage === 'Lost' ? 'Sample outcome: budget and scope did not align.' : null,
                    'created_at' => now()->subDays($i)->subHours(2),
                ]);
                if ($lead->wasRecentlyCreated) {
                    LeadActivity::create(['lead_id' => $lead->id, 'type' => 'created', 'body' => 'Seeded fictional enquiry for development. Source labels and outcomes are illustrative, not advertising results.']);
                }
                if ($stage === 'Won') {
                    $p = app(LeadWorkflow::class)->convert($lead);
                    if ($name === 'Arjun Bhatia') {
                        $p->update(['stage' => 'Execution', 'target_date' => today()->addDays(45), 'notes' => 'Demo project: carpentry and lighting coordination in progress.']);
                    }
                }
            }
        });
        app(Tenant::class)->run($owners[1]->business, function () use ($owners) {
            foreach (['Second Studio Client One', 'Second Studio Client Two'] as $i => $name) {
                Lead::firstOrCreate(['name' => $name], ['phone' => '900000009'.($i + 1), 'city' => 'Gurugram', 'locality' => 'DLF Phase 2', 'floor_area' => 850, 'property_type' => 'Apartment', 'configuration' => '2 BHK', 'scope' => 'Kitchens & wardrobes', 'budget' => 'Under ₹10 lakh', 'readiness' => 'Ready now', 'brief' => 'Private synthetic enquiry belonging only to the second demo studio.', 'stage' => 'New', 'source' => 'Studio profile', 'assigned_to' => $owners[1]->id, 'is_demo' => true]);
            }
        });
        if (! AuditEvent::where('action', 'development.seeded')->exists()) {
            AuditEvent::create(['user_id' => $admin->id, 'action' => 'development.seeded', 'changes' => ['fictional_data' => true]]);
        }
        $this->call(ContentDemoSeeder::class);
        $this->command?->info('Spacekind demo studios, roles, enquiries, galleries and projects seeded. No messages, charges or ad events were sent.');
    }
}
