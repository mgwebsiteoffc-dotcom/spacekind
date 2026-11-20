<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['entry_context' => 'array', 'utm' => 'array'];
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
