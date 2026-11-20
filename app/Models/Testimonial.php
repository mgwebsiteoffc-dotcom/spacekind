<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['attribution', 'quote', 'show_on_home', 'permission_confirmed_at', 'is_visible'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'show_on_home' => 'boolean'];
    }
}
