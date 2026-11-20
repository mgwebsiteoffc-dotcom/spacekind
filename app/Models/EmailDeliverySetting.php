<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDeliverySetting extends Model
{
    protected $fillable = ['host', 'port', 'encryption', 'username', 'smtp_password', 'from_address', 'from_name'];

    protected $hidden = ['smtp_password'];

    protected function casts(): array
    {
        return ['smtp_password' => 'encrypted', 'port' => 'integer'];
    }
}
