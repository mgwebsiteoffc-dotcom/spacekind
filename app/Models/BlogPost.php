<?php

namespace App\Models;

use App\Models\Concerns\OwnedPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BlogPost extends Model
{
    use OwnedPublication;

    protected $guarded = ['id', 'business_id', 'created_by', 'is_visible', 'visible_from', 'archived_at'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'visible_from' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function revisions()
    {
        return $this->hasMany(BlogRevision::class);
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(BlogRevision::class)->latestOfMany();
    }

    public function publicRevision(): HasOne
    {
        return $this->hasOne(BlogRevision::class)->ofMany(['publish_at' => 'max', 'id' => 'max'], fn ($q) => $q->where('status', 'approved')->where('publish_at', '<=', now()));
    }

    public function scopePubliclyVisible($q)
    {
        return $q->where('is_visible', true)->whereNull('archived_at')->where('visible_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('business_id')->orWhereHas('business', fn ($b) => $b->published()))
            ->whereHas('publicRevision');
    }

    public function getPublicUrlAttribute(): string
    {
        return $this->business_id ? '/studios/'.$this->business->slug.'/articles/'.$this->slug : '/blog/'.$this->slug;
    }

    public function getPublisherNameAttribute(): string
    {
        return $this->business?->name ?? 'Spacekind editorial';
    }
}
