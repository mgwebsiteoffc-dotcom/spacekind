<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Business;
use App\Models\Reel;

class PublicEntryContext
{
    public function resolve(Business $business, mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^(article|reel):([1-9][0-9]{0,17})$/', $value, $m)) {
            return null;
        }
        [$all,$kind,$id] = $m;
        $q = $kind === 'article' ? BlogPost::withoutGlobalScope('business')->publiclyVisible()->with('publicRevision') : Reel::withoutGlobalScope('business')->publiclyVisible();
        $record = $q->where('business_id', $business->id)->find($id);
        if (! $record) {
            return null;
        }

        return ['kind' => $kind, 'id' => (int) $record->id, 'title' => $kind === 'article' ? $record->publicRevision->title : $record->title, 'path' => $record->public_url];
    }
}
