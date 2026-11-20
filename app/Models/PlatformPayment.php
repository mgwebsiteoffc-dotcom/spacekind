<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PlatformPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'reversed_at' => 'datetime'];
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
