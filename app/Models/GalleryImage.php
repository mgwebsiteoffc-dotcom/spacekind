<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'rights_confirmed_at' => 'datetime'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function getUrlAttribute(): string
    {
        return '/media/'.$this->id;
    }

    public function getKindLabelAttribute(): string
    {
        return ['completed' => 'Completed project', 'render' => '3D render', 'concept' => 'Concept', 'reference' => 'Reference image', 'inspiration' => 'Inspiration'][$this->kind] ?? 'Portfolio image';
    }
}
