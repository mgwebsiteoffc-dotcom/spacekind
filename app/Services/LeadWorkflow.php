<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Support\Audit;
use App\Support\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Shared application workflow for Blade and /api/v1. */
class LeadWorkflow
{
    public function capturePublic(Business $business, array $data, ?UploadedFile $plan = null): Lead
    {
        abort_unless($business->approval_status === 'approved' && $business->account_status === 'pilot', 404);

        return app(Tenant::class)->run($business, fn () => $this->create($data, $plan, true));
    }

    public function create(array $data, ?UploadedFile $plan = null, bool $public = false): Lead
    {
        if ($public && ($existing = Lead::where('submission_key', $data['submission_key'])->first())) {
            return $existing;
        }
        $path = null;
        try {
            if ($plan) {
                $path = $plan->store('plans/'.app(Tenant::class)->id(), 'local');
            }

            return DB::transaction(function () use ($data, $public, $path) {
                $attrs = Arr::only($data, ['name', 'phone', 'email', 'city', 'locality', 'floor_area', 'property_type', 'configuration', 'scope', 'budget', 'readiness', 'brief']);
                $attrs['phone'] = preg_replace('/[\s\-]+/', '', $attrs['phone']);
                $attrs['stage'] = 'New';
                $attrs['plan_path'] = $path;
                $attrs['source'] = $public ? (($data['origin'] ?? 'profile') === 'marketplace' ? 'Marketplace' : 'Studio profile') : ($data['source'] ?? 'Unknown');
                $attrs['contact_consent_at'] = ! empty($data['contact_consent']) ? now() : null;
                $attrs['consent_version'] = $public ? 'project-contact-v1' : null;
                $attrs['marketing_consent'] = $public && ! empty($data['marketing_consent']);
                $attrs['submission_key'] = $public ? $data['submission_key'] : null;
                $attrs['assigned_to'] = $public ? app(Tenant::class)->business()->users()->where('role', 'owner')->where('is_active', true)->value('id') : ($data['assigned_to'] ?? auth()->id());
                if ($public && ! empty($data['attribution_consent'])) {
                    $utm = [];
                    foreach (['source', 'medium', 'campaign', 'content', 'term'] as $key) {
                        if (! empty($data['utm_'.$key])) {
                            $utm[$key] = $data['utm_'.$key];
                        }
                    }
                    $attrs['utm'] = $utm ?: null;
                    $context = app(PublicEntryContext::class)->resolve(app(Tenant::class)->business(), $data['entry_context'] ?? null);
                    $attrs['entry_context'] = $context ? ($context + ['recorded_at' => now()->toIso8601String()]) : null;
                    $attrs['attribution_consent_at'] = now();
                    if ($utm) {
                        $attrs['source'] = 'Campaign link';
                    }
                }
                $lead = Lead::create($attrs);
                $this->activity($lead, 'created', $public ? 'Qualified project brief submitted through the studio profile. No WhatsApp message sent.' : 'Enquiry added by the studio.', $public ? null : auth()->id());
                Audit::record('enquiry.created', $lead, ['channel' => $public ? 'public-form' : 'workspace']);

                return $lead;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if ($public && ($existing = Lead::where('submission_key', $data['submission_key'])->first())) {
                return $existing;
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
    }

    public function update(Lead $lead, array $data): Lead
    {
        return DB::transaction(function () use ($lead, $data) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            $before = $lead->stage;
            $attrs = Arr::only($data, ['stage', 'assigned_to', 'next_follow_up_at', 'follow_up_task', 'lost_reason']);
            if (array_key_exists('next_follow_up_at', $attrs)) {
                $attrs['next_follow_up_at'] = $attrs['next_follow_up_at'] ? Carbon::parse($attrs['next_follow_up_at']) : null;
            }
            if (($attrs['stage'] ?? $before) !== 'Lost') {
                $attrs['lost_reason'] = null;
            }
            $lead->fill($attrs);
            $dirty = array_keys($lead->getDirty());
            $lead->save();
            if ($before !== $lead->stage) {
                $this->activity($lead, 'stage', "Stage changed from {$before} to {$lead->stage}.", auth()->id());
            }
            if (array_intersect($dirty, ['next_follow_up_at', 'follow_up_task'])) {
                $this->activity($lead, 'follow_up', $lead->next_follow_up_at ? 'In-app follow-up set for '.$lead->next_follow_up_at->format('d M Y, g:i a').': '.$lead->follow_up_task : 'In-app follow-up cleared.', auth()->id());
            }
            if (in_array('assigned_to', $dirty)) {
                $this->activity($lead, 'assignment', 'Enquiry assignment updated.', auth()->id());
            }
            if ($dirty) {
                Audit::record('enquiry.updated', $lead, ['fields' => $dirty]);
            }

            return $lead;
        });
    }

    public function note(Lead $lead, string $note): LeadActivity
    {
        $activity = $this->activity($lead, 'note', $note, auth()->id());
        Audit::record('enquiry.note_added', $lead);

        return $activity;
    }

    public function completeFollowUp(Lead $lead): void
    {
        DB::transaction(function () use ($lead) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            if (! $lead->next_follow_up_at) {
                return;
            }
            $task = $lead->follow_up_task ?: 'Follow-up';
            $lead->update(['next_follow_up_at' => null, 'follow_up_task' => null]);
            $this->activity($lead, 'follow_up', 'Completed in-app follow-up: '.$task, auth()->id());
            Audit::record('enquiry.follow_up_completed', $lead);
        });
    }

    public function convert(Lead $lead): Project
    {
        return DB::transaction(function () use ($lead) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            if ($project = Project::where('lead_id', $lead->id)->first()) {
                return $project;
            }
            $client = Client::firstOrCreate(['lead_id' => $lead->id], $lead->only('name', 'phone', 'email', 'locality', 'source', 'utm', 'entry_context'));
            $project = Project::create(['client_id' => $client->id, 'lead_id' => $lead->id, 'name' => $lead->name.' · '.$lead->scope, 'stage' => 'Planned']);
            $lead->update(['stage' => 'Won', 'lost_reason' => null, 'next_follow_up_at' => null, 'follow_up_task' => null]);
            $this->activity($lead, 'converted', 'Marked Won and converted to a client/project. This is a studio-recorded outcome, not payment verification.', auth()->id());
            Audit::record('enquiry.converted', $lead, ['project_id' => $project->id]);

            return $project;
        });
    }

    private function activity(Lead $lead, string $type, string $body, ?int $userId): LeadActivity
    {
        return LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $userId, 'type' => $type, 'body' => $body]);
    }
}
