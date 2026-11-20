<?php

namespace App\Http\Controllers;

use App\Models\CampaignLink;
use App\Models\Lead;
use App\Services\OwnedDestinations;
use App\Support\Audit;
use App\Support\CampaignLabels;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignLinkController extends Controller
{
    public function index(OwnedDestinations $destinations)
    {
        $links = CampaignLink::latest('id')->paginate(10);
        $options = $destinations->for(app(Tenant::class)->business());
        foreach ($links as $link) {
            $q = Lead::where('source', 'Campaign link');
            foreach (CampaignLabels::KEYS as $key) {
                isset($link->utm[$key]) ? $q->where('utm->'.$key, $link->utm[$key]) : $q->whereNull('utm->'.$key);
            }
            if (in_array($link->destination_type, ['article', 'reel'])) {
                $q->where('entry_context->kind', $link->destination_type)->where('entry_context->id', $link->destination_id);
            }
            $link->matching_enquiries = (clone $q)->count();
            $link->recorded_won = (clone $q)->where('stage', 'Won')->count();
            $key = in_array($link->destination_type, ['article', 'reel']) ? $link->destination_type.':'.$link->destination_id : $link->destination_type;
            $link->destination_available = isset($options[$key]);
        }

        return view('workspace.campaign-links', compact('links', 'options'));
    }

    public function store(Request $r, OwnedDestinations $destinations)
    {
        $options = $destinations->for(app(Tenant::class)->business());
        $v = $r->validate(['name' => 'required|string|max:100', 'destination' => ['required', Rule::in(array_keys($options))]] + CampaignLabels::rules(true));
        $utm = [];
        foreach (CampaignLabels::KEYS as $key) {
            if (! empty($v['utm_'.$key])) {
                $utm[$key] = trim($v['utm_'.$key]);
            }
        }
        $d = $options[$v['destination']];
        $fingerprint = hash('sha256', $d['path'].'|'.json_encode($utm));
        $link = CampaignLink::firstOrCreate(['fingerprint' => $fingerprint], ['name' => $v['name'], 'destination_path' => $d['path'], 'destination_type' => $d['type'], 'destination_id' => $d['id'], 'utm' => $utm]);
        Audit::record('campaign_link.saved', $link);

        return redirect('/workspace/campaign-links')->with('success', 'Campaign link saved. Copy it into a placement that supports links; nothing has been posted or sent.');
    }

    public function delete(CampaignLink $campaign)
    {
        Audit::record('campaign_link.deleted', $campaign);
        $campaign->delete();

        return back()->with('success', 'Saved link removed. Previously shared URLs and recorded CRM labels remain unchanged.');
    }

    public function api()
    {
        return response()->json(['data' => CampaignLink::latest('id')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'destination_type' => $c->destination_type, 'url' => $c->share_url, 'utm' => $c->utm])]);
    }
}
