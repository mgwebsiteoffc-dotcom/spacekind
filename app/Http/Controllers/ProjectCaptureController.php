<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\ProjectCapture;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProjectCaptureController extends Controller
{
    public function admin(ProjectCapture $capture)
    {
        return view('admin.project-capture', ['s' => $capture->settings(), 'briefs' => DB::table('platform_briefs')->latest('id')->paginate(20)]);
    }

    public function settings(Request $r)
    {
        $s = $r->validate(['heading' => 'required|string|max:80', 'button' => 'required|string|max:32', 'delay' => 'required|integer|between:3,120', 'entry' => 'nullable|boolean', 'exit' => 'nullable|boolean', 'floating' => 'nullable|boolean']);
        foreach (['entry', 'exit', 'floating'] as $key) {
            $s[$key] = $r->boolean($key);
        }
        $row = SiteSetting::find(1) ?? (new SiteSetting)->forceFill(['id' => 1, 'seo' => []]);
        $row->capture = $s;
        $row->save();
        app()->forgetInstance(ProjectCapture::class);
        Audit::record('admin.project_capture_saved', $row);

        return back()->with('success', 'Project enquiry settings saved.');
    }

    public function form(Request $r, ProjectCapture $capture)
    {
        abort_unless(! $r->user() && $capture->enabled(), 404);

        return view('public.project-brief', ['s' => $capture->settings()]);
    }

    public function store(Request $r, ProjectCapture $capture)
    {
        abort_unless(! $r->user() && $capture->enabled(), 404);
        $validator = Validator::make($r->all(), ['name' => 'required|string|max:100', 'phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/D'], 'city' => ['required', Rule::in(config('spacekind.cities'))], 'floor_area' => 'required|integer|between:50,1000000', 'budget' => ['required', Rule::in(config('spacekind.budgets'))], 'scope' => ['required', Rule::in(array_values(config('spacekind.services')))], 'consent' => 'accepted', 'submission_key' => 'required|uuid', 'website' => 'nullable|string|max:0']);
        if ($validator->fails()) {
            return redirect('/project-brief')->withErrors($validator)->withInput(array_filter($r->only(['name','phone','city','floor_area','budget','scope']), 'is_scalar'));
        }
        $data = Arr::except($validator->validated(), ['consent', 'website']);
        DB::table('platform_briefs')->upsert([$data + ['consented_at' => now()]], ['submission_key'], ['submission_key']);

        return redirect('/project-brief')->with('brief_received', true);
    }

    public function destroy(int $brief)
    {
        abort_unless(DB::table('platform_briefs')->where('id', $brief)->delete(), 404);
        Audit::record('admin.platform_brief_deleted');

        return back()->with('success', 'Project enquiry deleted.');
    }
}
