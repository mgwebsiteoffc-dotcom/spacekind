<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RazorpayCredential extends Model
{
    protected $fillable = ['mode', 'key_id', 'key_secret'];

    protected $hidden = ['key_secret'];

    protected function casts(): array
    {
        return ['key_secret' => 'encrypted'];
    }
}
