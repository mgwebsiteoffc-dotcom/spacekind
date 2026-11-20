<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformBillingSetting extends Model
{
    protected $fillable = ['id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
