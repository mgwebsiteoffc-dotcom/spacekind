<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CustomerPayment extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'reversed_at' => 'datetime', 'amount_minor' => 'integer'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function receipt()
    {
        return $this->belongsTo(FinancialDocument::class, 'receipt_id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function getAvailableMinorAttribute(): int
    {
        return $this->reversed_at ? 0 : $this->amount_minor - (int) $this->allocations()->whereNull('reversed_at')->sum('amount_minor');
    }

    protected static function booted(): void
    {
        static::updating(function ($p) {
            if (array_diff(array_keys($p->getDirty()), ['reversed_at', 'reversal_reason', 'updated_at'])) {
                throw ValidationException::withMessages(['payment' => 'Recorded payment/allocation details are immutable. Reverse with a reason instead.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['payment' => 'Payment history cannot be hard-deleted.']));
    }
}
