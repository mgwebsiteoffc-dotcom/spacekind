<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class WorkerRate extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['effective_from', 'daily_paise'];
}
