<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['attribution', 'quote', 'permission_confirmed_at', 'is_visible'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }
}
