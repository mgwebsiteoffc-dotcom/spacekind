<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class WorkerAttendance extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['worker_id', 'work_date', 'first_project_id', 'second_project_id', 'absent', 'note'];

    protected function casts(): array
    {
        return ['absent' => 'boolean', 'first_project_id' => 'integer', 'second_project_id' => 'integer',
            'revision' => 'integer', 'daily_paise' => 'integer', 'earned_paise' => 'integer'];
    }

    public function label(): string
    {
        return match (true) {
            $this->absent => 'Absent',
            (bool) ($this->first_project_id && $this->second_project_id) => 'Full day',
            (bool) ($this->first_project_id || $this->second_project_id) => 'Half day',
            default => 'Not marked',
        };
    }
}
