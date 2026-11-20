<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use BelongsToBusiness;

    public const STAGES = ['Planned', 'Design', 'Execution', 'Handover', 'Closed'];

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'handover_at' => 'datetime'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
