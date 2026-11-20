<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Lead;
use App\Services\LeadWorkflow;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:80', 'stage' => ['nullable', Rule::in(Lead::STAGES)], 'source' => ['nullable', Rule::in(config('spacekind.sources'))], 'view' => ['nullable', Rule::in(['list', 'board'])], 'due' => 'nullable|in:1']);
        $query = Lead::with('assignee')->latest();
        if ($r->filled('q')) {
            $query->where(function ($q) use ($r) {
                $v = '%'.addcslashes($r->q, '%_\\').'%';
                $q->where('name', 'like', $v)->orWhere('locality', 'like', $v)->orWhere('phone', 'like', $v);
            });
        }
        if ($r->filled('stage')) {
            $query->where('stage', $r->stage);
        }
        if ($r->filled('source')) {
            $query->where('source', $r->source);
        }
        if ($r->boolean('due')) {
            $query->whereNotIn('stage', ['Won', 'Lost'])->where('next_follow_up_at', '<=', now());
        }
        $leads = $query->paginate($r->input('view') === 'board' ? 100 : 20)->withQueryString();

        return view('workspace.enquiries', compact('leads'));
    }

    public function create()
    {
        return view('workspace.new-enquiry', ['members' => app(Tenant::class)->business()->users()->where('is_active', true)->get()]);
    }

    public function store(StoreLeadRequest $r, LeadWorkflow $workflow)
    {
        $lead = $workflow->create($r->validated(), $r->file('plan'));

        return redirect('/workspace/enquiries/'.$lead->id)->with('success', 'Enquiry saved. No message was sent.');
    }

    public function show(Lead $lead)
    {
        $lead->load(['activities.user', 'assignee', 'project']);

        return view('workspace.enquiry', ['lead' => $lead, 'members' => app(Tenant::class)->business()->users()->where('is_active', true)->get()]);
    }

    public function update(UpdateLeadRequest $r, Lead $lead, LeadWorkflow $workflow)
    {
        $workflow->update($lead, $r->validated());

        return back()->with('success', 'Enquiry updated. Follow-ups are in-app reminders only.');
    }

    public function note(Request $r, Lead $lead, LeadWorkflow $workflow)
    {
        $v = $r->validate(['body' => 'required|string|min:2|max:3000']);
        $workflow->note($lead, $v['body']);

        return back()->with('success', 'Note added to the activity history.');
    }

    public function complete(Lead $lead, LeadWorkflow $workflow)
    {
        $workflow->completeFollowUp($lead);

        return back()->with('success', 'Follow-up marked complete.');
    }

    public function convert(Lead $lead, LeadWorkflow $workflow)
    {
        $project = $workflow->convert($lead);

        return redirect('/workspace/projects/'.$project->id)->with('success', 'Client and project ready. Outcome recorded as Won; no payment has been verified.');
    }

    public function plan(Lead $lead)
    {
        abort_unless($lead->plan_path && Storage::disk('local')->exists($lead->plan_path), 404);

        return Storage::disk('local')->download($lead->plan_path, 'project-plan-'.$lead->reference.'.'.pathinfo($lead->plan_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
