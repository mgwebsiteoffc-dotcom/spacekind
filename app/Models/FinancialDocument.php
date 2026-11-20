<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FinancialDocument extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected $attributes = ['status' => 'draft', 'lock_version' => 1];

    protected function casts(): array
    {
        return ['customer' => 'array', 'items' => 'array', 'snapshot' => 'array', 'document_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'voided_at' => 'datetime', 'decided_at' => 'datetime', 'sent_at' => 'datetime', 'reminder_at' => 'datetime', 'total_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function ($d) {
            if ($d->getRawOriginal('status') !== 'draft' && array_diff(array_keys($d->getDirty()), ['status', 'voided_at', 'void_reason', 'decision', 'decided_at', 'decision_note', 'reminder_at', 'reminder_note', 'sent_at', 'sent_note', 'updated_at'])) {
                throw ValidationException::withMessages(['document' => 'Issued document details are immutable. Void/copy to correct; history is retained.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['document' => 'Financial records are retained; use the audited void workflow.']));
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function shares()
    {
        return $this->hasMany(DocumentShare::class, 'document_id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id');
    }

    public function receiptPayment()
    {
        return $this->hasOne(CustomerPayment::class, 'receipt_id');
    }

    public function getPaidMinorAttribute(): int
    {
        return $this->kind === 'invoice' ? (int) $this->allocations()->whereNull('reversed_at')->sum('amount_minor') : 0;
    }

    public function getBalanceMinorAttribute(): int
    {
        return $this->kind !== 'invoice' || $this->status !== 'issued' ? 0 : max(0, $this->total_minor - $this->paid_minor);
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status !== 'issued') {
            return $this->status;
        }if ($this->kind === 'quotation') {
            return $this->decision ?: ($this->due_date?->isBefore(today()) ? 'expired' : ($this->sent_at ? 'sent' : 'issued'));
        }if ($this->kind === 'receipt') {
            return $this->receiptPayment?->reversed_at ? 'reversed' : 'recorded';
        }if ($this->balance_minor === 0) {
            return 'paid';
        }if ($this->due_date?->isBefore(today())) {
            return 'overdue';
        }

return $this->paid_minor > 0 ? 'part-paid' : 'unpaid';
    }

    public function getLabelAttribute(): string
    {
        return $this->number ?? 'Draft #'.$this->id;
    }
}
