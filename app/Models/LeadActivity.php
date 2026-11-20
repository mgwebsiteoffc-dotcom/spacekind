<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
