<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $guarded = ['id', 'approval_status', 'account_status', 'is_demo', 'reviewed_at'];

    protected function casts(): array
    {
        return ['project_highlights' => 'array', 'awards' => 'array', 'established_year' => 'integer', 'completed_project_count' => 'integer', 'services' => 'array', 'tags' => 'array', 'is_demo' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function galleryImages()
    {
        return $this->hasMany(GalleryImage::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function scopePublished($q)
    {
        return $q->where('approval_status', 'approved')->where('account_status', 'pilot');
    }

    public function getServiceLabelAttribute(): string
    {
        return config('spacekind.services.'.$this->service, 'Interior design');
    }

    public function getCoverUrlAttribute(): string
    {
        return '/assets/interiors/'.(in_array($this->cover_key, config('spacekind.cover_keys')) ? $this->cover_key : 'hero').'.webp';
    }
}
