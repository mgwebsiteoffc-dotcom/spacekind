<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Services\LeadWorkflow;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    public function token(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'device_name' => 'required|string|max:80']);
        $user = User::where('email', $v['email'])->where('is_active', true)->first();
        if (! $user || ! Hash::check($v['password'], $user->password) || $user->isAdmin() || $user->business?->account_status !== 'pilot') {
            throw ValidationException::withMessages(['email' => 'These details do not match an active studio account.']);
        }

        return response()->json(['token' => $user->createToken($v['device_name'], ['workspace'], now()->addDays(30))->plainTextToken, 'token_type' => 'Bearer', 'expires_in_days' => 30], 201);
    }

    public function revoke(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $r)
    {
        return ['data' => ['id' => $r->user()->id, 'name' => $r->user()->name, 'email' => $r->user()->email, 'role' => $r->user()->role, 'business' => app(Tenant::class)->business()->only('id', 'name', 'slug', 'approval_status')]];
    }

    public function leads(Request $r)
    {
        $r->validate(['stage' => ['nullable', Rule::in(Lead::STAGES)]]);
        $q = Lead::latest();
        if ($r->filled('stage')) {
            $q->where('stage', $r->stage);
        }

        return $q->paginate(20);
    }

    public function showLead(Lead $lead)
    {
        return ['data' => $lead->load('activities', 'assignee')];
    }

    public function storeLead(StoreLeadRequest $r, LeadWorkflow $w)
    {
        return response()->json(['data' => $w->create($r->validated(), $r->file('plan'))], 201);
    }

    public function updateLead(UpdateLeadRequest $r, Lead $lead, LeadWorkflow $w)
    {
        return ['data' => $w->update($lead, $r->validated())];
    }

    public function note(Request $r, Lead $lead, LeadWorkflow $w)
    {
        $v = $r->validate(['body' => 'required|string|min:2|max:3000']);

        return response()->json(['data' => $w->note($lead, $v['body'])], 201);
    }

    public function convert(Lead $lead, LeadWorkflow $w)
    {
        return ['data' => $w->convert($lead)];
    }

    public function projects()
    {
        return Project::with('client')->latest()->paginate(20);
    }
}
