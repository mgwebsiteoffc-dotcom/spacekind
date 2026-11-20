<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CampaignLink extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['utm' => 'array'];
    }

    public function getShareUrlAttribute(): string
    {
        return url($this->destination_path).'?'.http_build_query(collect($this->utm)->mapWithKeys(fn ($v, $k) => ['utm_'.$k => $v])->all(), '', '&', PHP_QUERY_RFC3986);
    }
}
