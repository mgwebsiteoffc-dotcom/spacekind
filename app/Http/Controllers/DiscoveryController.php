<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\Discovery;
use App\Support\Audit;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function index(Discovery $discovery)
    {
        return view('admin.discovery', ['settings' => $discovery->settings(), 'deploymentIndexing' => config('spacekind.allow_indexing', false) && ! config('spacekind.demo')]);
    }

    public function update(Request $r)
    {
        $v = $r->validate(['home_title' => 'required|string|max:70', 'home_description' => 'required|string|max:170', 'business_title' => 'required|string|max:70', 'business_description' => 'required|string|max:170', 'organisation_description' => 'required|string|max:400', 'indexing_enabled' => 'nullable|boolean']);
        $v['indexing_enabled'] = $r->boolean('indexing_enabled');
        $setting = SiteSetting::find(1) ?? new SiteSetting;
        $setting->forceFill(['id' => 1]);
        $setting->seo = $v;
        $setting->save();
        app(Discovery::class)->reset();
        Audit::record('admin.discovery_updated', $setting, ['indexing_requested' => $v['indexing_enabled']]);

        return back()->with('success', 'Discovery defaults saved. The preview and deployment indexation lock still prevent indexing.');
    }
}
