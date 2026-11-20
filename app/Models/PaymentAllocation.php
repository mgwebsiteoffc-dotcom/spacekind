<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PaymentAllocation extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['reversed_at' => 'datetime', 'amount_minor' => 'integer'];
    }

    public function invoice()
    {
        return $this->belongsTo(FinancialDocument::class, 'invoice_id');
    }

    protected static function booted(): void
    {
        static::updating(function ($p) {
            if (array_diff(array_keys($p->getDirty()), ['reversed_at', 'updated_at'])) {
                throw ValidationException::withMessages(['payment' => 'Recorded payment/allocation details are immutable. Reverse with a reason instead.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['payment' => 'Payment history cannot be hard-deleted.']));
    }
}
