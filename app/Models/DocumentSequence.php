<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];
}
