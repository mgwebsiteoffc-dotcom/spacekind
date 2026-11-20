<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Support\Tenant;

class WorkspaceController extends Controller
{
    public function index()
    {
        $metrics = ['enquiries' => Lead::count(), 'new' => Lead::where('stage', 'New')->count(), 'due' => Lead::whereNotIn('stage', ['Won', 'Lost'])->where('next_follow_up_at', '<=', now())->count(), 'projects' => Project::where('stage', '!=', 'Closed')->count()];
        $recent = Lead::with('assignee')->latest()->limit(5)->get();
        $followups = Lead::whereNotIn('stage', ['Won', 'Lost'])->whereNotNull('next_follow_up_at')->orderBy('next_follow_up_at')->limit(4)->get();
        $sources = Lead::selectRaw("source, COUNT(*) AS total, SUM(CASE WHEN stage = 'Won' THEN 1 ELSE 0 END) AS won")->groupBy('source')->orderByDesc('total')->get();
        $stages = Lead::selectRaw('stage, COUNT(*) AS total')->groupBy('stage')->pluck('total', 'stage');

        return view('workspace.overview', compact('metrics', 'recent', 'followups', 'sources', 'stages'));
    }

    public function clients()
    {
        return view('workspace.clients', ['clients' => Client::with('projects')->latest()->paginate(20)]);
    }

    public function modules()
    {
        return view('workspace.modules', ['subscription' => app(Tenant::class)->business()->subscription]);
    }
}
