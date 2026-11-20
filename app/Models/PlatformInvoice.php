<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PlatformInvoice extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function ($d) {
            if ($d->getRawOriginal('status') !== 'draft' && array_diff(array_keys($d->getDirty()), ['status', 'void_reason', 'updated_at'])) {
                throw ValidationException::withMessages(['invoice' => 'Issued platform invoice details are immutable.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['invoice' => 'Platform invoices are retained; use void.']));
    }

    protected function casts(): array
    {
        return ['customer' => 'array', 'snapshot' => 'array', 'document_date' => 'date', 'due_date' => 'date', 'period_start' => 'date', 'period_end' => 'date'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function payments()
    {
        return $this->hasMany(PlatformPayment::class);
    }

    public function getPaidMinorAttribute(): int
    {
        return (int) $this->payments()->whereNull('reversed_at')->sum('amount_minor');
    }

    public function getBalanceMinorAttribute(): int
    {
        return $this->status !== 'issued' ? 0 : $this->total_minor - $this->paid_minor;
    }
}
