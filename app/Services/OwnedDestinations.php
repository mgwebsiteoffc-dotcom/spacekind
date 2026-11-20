<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Business;
use App\Models\Reel;

class OwnedDestinations
{
    public function for(Business $business): array
    {
        if ($business->approval_status !== 'approved' || $business->account_status !== 'pilot') {
            return [];
        }
        $items = [
            'profile' => ['label' => 'Your studio profile', 'path' => '/studios/'.$business->slug, 'type' => 'profile', 'id' => null],
            'enquiry' => ['label' => 'Your qualified enquiry form', 'path' => '/studios/'.$business->slug.'/enquire', 'type' => 'enquiry', 'id' => null],
        ];
        foreach (BlogPost::withoutGlobalScope('business')->where('business_id', $business->id)->publiclyVisible()->with(['publicRevision', 'business'])->get() as $p) {
            $items['article:'.$p->id] = ['label' => 'Article · '.$p->publicRevision->title, 'path' => $p->public_url, 'type' => 'article', 'id' => $p->id];
        }
        foreach (Reel::withoutGlobalScope('business')->where('business_id', $business->id)->publiclyVisible()->get() as $v) {
            $items['reel:'.$v->id] = ['label' => 'Video · '.$v->title, 'path' => $v->public_url, 'type' => 'reel', 'id' => $v->id];
        }

        return $items;
    }
}
