<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class WorkerPayment extends Model
{
    use BelongsToBusiness;

    public const KINDS = ['advance' => 'Advance', 'wage' => 'Wage payment'];

    public const METHODS = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank transfer', 'cheque' => 'Cheque', 'other' => 'Other'];

    protected $fillable = ['worker_id', 'entry_key', 'project_id', 'kind', 'paid_on', 'amount_paise', 'method', 'reference', 'note'];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'project_id' => 'integer', 'reversed_at' => 'datetime'];
    }
}
