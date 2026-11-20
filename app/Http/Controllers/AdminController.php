<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\Lead;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index()
    {
        $stats = ['studios' => Business::count(), 'pending' => Business::where('approval_status', 'pending')->count(), 'enquiries' => Lead::withoutGlobalScope('business')->count(), 'projects' => Project::withoutGlobalScope('business')->count()];

        return view('admin.index', ['stats' => $stats, 'businesses' => Business::withCount('users')->latest()->paginate(12), 'events' => AuditEvent::with('user', 'business')->latest()->limit(10)->get(), 'dbVersion' => DB::selectOne('SELECT VERSION() AS version')->version]);
    }

    public function review(Request $r, Business $business)
    {
        $v = $r->validate(['decision' => ['required', Rule::in(['approved', 'pending', 'rejected'])]]);
        $business->forceFill(['approval_status' => $v['decision'], 'reviewed_at' => now()])->save();
        Audit::record('admin.studio_'.$v['decision'], $business);

        return back()->with('success', 'Studio listing review updated.');
    }

    public function access(Business $business)
    {
        $business->forceFill(['account_status' => $business->account_status === 'suspended' ? 'pilot' : 'suspended'])->save();
        if ($business->account_status === 'suspended') {
            foreach ($business->users as $user) {
                $user->tokens()->delete();
            }
        }
        Audit::record('admin.workspace_access_changed', $business, ['status' => $business->account_status]);

        return back()->with('success', 'Workspace access updated.');
    }
}
