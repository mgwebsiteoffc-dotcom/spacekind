<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['name', 'trade'];

    public function rates(): HasMany
    {
        return $this->hasMany(WorkerRate::class)->orderByDesc('effective_from');
    }
}
