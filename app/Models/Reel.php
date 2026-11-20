<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Reel extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id', 'disk', 'blocked_at', 'moderation_note', 'video_path', 'cover_path', 'mime', 'bytes', 'duration', 'rights_confirmed_at'];

    protected function casts(): array
    {
        return ['blocked_at' => 'datetime', 'rights_confirmed_at' => 'datetime', 'duration' => 'float'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function scopePubliclyVisible($q)
    {
        return $q->where('status', 'published')->whereNull('blocked_at')->whereNotNull('rights_confirmed_at')->whereHas('business', fn ($b) => $b->published());
    }

    public function getVideoUrlAttribute(): string
    {
        return '/reel-media/'.$this->id.'/video';
    }

    public function getCoverUrlAttribute(): string
    {
        return '/reel-media/'.$this->id.'/cover';
    }

    public function getPublicUrlAttribute(): string
    {
        return '/reels/'.$this->id;
    }

    public function getKindLabelAttribute(): string
    {
        return ['completed' => 'Completed project', 'render' => '3D render', 'concept' => 'Concept / motion study'][$this->kind] ?? 'Studio video';
    }
}
