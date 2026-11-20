<?php

namespace App\Http\Controllers;

use App\Models\FinancialDocument;
use App\Models\Project;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        return view('workspace.projects', ['projects' => Project::with('client')->latest()->paginate(12)]);
    }

    public function show(Project $project)
    {
        $project->load('client', 'lead');

        return view('workspace.project', ['project' => $project, 'financialDocuments' => auth()->user()->isOwner() ? FinancialDocument::where('project_id', $project->id)->latest('id')->limit(30)->get() : collect()]);
    }

    public function update(Request $r, Project $project)
    {
        $v = $r->validate(['name' => 'required|string|max:160', 'stage' => ['required', Rule::in(Project::STAGES)], 'target_date' => 'nullable|date|after:2000-01-01|before:2100-01-01', 'notes' => 'nullable|string|max:5000']);
        if (in_array($v['stage'], ['Handover', 'Closed']) && ! $project->handover_at) {
            $v['handover_at'] = now();
        }
        $project->update($v);
        Audit::record('project.updated', $project, ['stage' => $project->stage]);

        return back()->with('success', 'Project updated. Financial documents are linked for the owner; labour management remains upcoming.');
    }
}
