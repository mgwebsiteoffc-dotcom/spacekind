<?php

namespace App\Http\Controllers;

use App\Models\TrackingVersion;
use App\Rules\PublicHttpsUrl;
use App\Services\PublicTracking;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrackingController extends Controller
{
    public function index(PublicTracking $tracking)
    {
        return view('admin.tracking', ['current' => $tracking->published(), 'versions' => TrackingVersion::with('user')->latest('id')->paginate(10), 'scopes' => PublicTracking::SCOPES, 'deliveryAllowed' => $tracking->deploymentAllowed(), 'approvedHosts' => config('spacekind.approved_script_hosts', [])]);
    }

    public function store(Request $r)
    {
        $v = $r->validate([
            'intent' => ['required', Rule::in(['draft', 'publish'])], 'note' => 'required|string|min:5|max:300', 'enabled' => 'nullable|boolean',
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{5,15}$/'], 'gtm_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,12}$/'], 'meta_pixel_id' => ['nullable', 'string', 'regex:/^[0-9]{5,25}$/'],
            'clarity_id' => ['nullable', 'string', 'regex:/^[a-z0-9]{5,20}$/D'],
            'custom_name' => 'nullable|required_with:custom_script_url|string|max:80', 'custom_script_url' => ['nullable', 'string', 'max:500', new PublicHttpsUrl],
            'custom_category' => ['required', Rule::in(['analytics', 'marketing'])], 'debug_mode' => 'nullable|boolean',
            'scopes' => 'nullable|array', 'scopes.*' => ['required', Rule::in(array_keys(PublicTracking::SCOPES))],
        ]);
        if (! empty($v['gtm_id']) && (! empty($v['ga4_id']) || ! empty($v['meta_pixel_id']) || ! empty($v['clarity_id']))) {
            throw ValidationException::withMessages(['gtm_id' => 'Choose GTM OR direct GA4/Meta/Clarity tags, not both. This prevents duplicate installations.']);
        }
        if (! empty($v['custom_script_url'])) {
            $u = parse_url($v['custom_script_url']);
            $host = strtolower($u['host'] ?? '');
            if (! in_array($host, config('spacekind.approved_script_hosts', []), true) || isset($u['query']) || isset($u['fragment']) || ! str_ends_with(strtolower($u['path'] ?? ''), '.js')) {
                throw ValidationException::withMessages(['custom_script_url' => 'Use a reviewed .js URL on a deployment-approved hostname, with no query string or fragment. Arbitrary pasted HTML is not accepted.']);
            }
        }
        $v['enabled'] = $r->boolean('enabled');
        $v['debug_mode'] = $r->boolean('debug_mode');
        $v['scopes'] = array_values(array_unique($v['scopes'] ?? []));
        if ($v['enabled'] && (! $v['scopes'] || (! ($v['ga4_id'] ?? null) && ! ($v['gtm_id'] ?? null) && ! ($v['meta_pixel_id'] ?? null) && ! ($v['clarity_id'] ?? null) && ! ($v['custom_script_url'] ?? null)))) {
            throw ValidationException::withMessages(['enabled' => 'Choose at least one page scope and one valid provider before enabling delivery.']);
        }
        $version = TrackingVersion::create(['user_id' => auth()->id(), 'status' => $v['intent'] === 'publish' ? 'published' : 'draft', 'note' => $v['note'], 'settings' => collect($v)->except(['intent', 'note'])->all()]);
        app(PublicTracking::class)->reset();
        Audit::record('admin.tracking_'.$version->status, $version, ['enabled' => $v['enabled'], 'deployment_locked' => ! app(PublicTracking::class)->deploymentAllowed()]);

        return back()->with('success', $version->status === 'draft' ? 'Draft saved. It is not the published configuration.' : 'Configuration version published. The deployment lock, consent and route restrictions still control delivery; the demo never sends tags.');
    }

    public function disable()
    {
        $v = TrackingVersion::create(['user_id' => auth()->id(), 'status' => 'published', 'note' => 'Emergency disable from the admin panel', 'settings' => ['enabled' => false, 'scopes' => []]]);
        app(PublicTracking::class)->reset();
        Audit::record('admin.tracking_disabled', $v);

        return back()->with('success', 'Published tracking configuration disabled for subsequent page loads. Already-open third-party sessions cannot be remotely erased.');
    }

    public function restore(int $version)
    {
        $old = TrackingVersion::findOrFail($version);
        $v = TrackingVersion::create(['user_id' => auth()->id(), 'status' => 'published', 'note' => 'Explicit rollback to configuration #'.$old->id, 'settings' => $old->settings]);
        app(PublicTracking::class)->reset();
        Audit::record('admin.tracking_rollback', $v, ['restored_from' => $old->id]);

        return back()->with('success','A new published version restores those settings. All current deployment, allowlist, consent and page-scope restrictions still apply.');
    }
}
