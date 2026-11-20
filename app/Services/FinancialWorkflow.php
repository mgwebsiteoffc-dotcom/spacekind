<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Business;
use App\Models\Client;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\DocumentShare;
use App\Models\FinancialDocument;
use App\Models\PaymentAllocation;
use App\Models\Project;
use App\Support\Audit;
use App\Support\BillingDetails;
use App\Support\Money;
use App\Support\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FinancialWorkflow
{
    public function seller(): array
    {
        return BillingSetting::first()?->data ?? [];
    }

    private function lockBusiness(): void
    {
        Business::whereKey(app(Tenant::class)->id())->lockForUpdate()->firstOrFail();
    }

    private function parties(int $clientId, ?int $projectId): void
    {
        Client::findOrFail($clientId);
        if ($projectId) {
            Project::where('client_id', $clientId)->findOrFail($projectId);
        }
    }

    public function draft(array $v, ?FinancialDocument $document = null): FinancialDocument
    {
        return DB::transaction(function () use ($v, $document) {
            $this->lockBusiness();
            $this->parties((int) $v['client_id'], ! empty($v['project_id']) ? (int) $v['project_id'] : null);
            if ($document) {
                $document = FinancialDocument::lockForUpdate()->findOrFail($document->id);
                if ($document->status !== 'draft' || $document->kind === 'receipt') {
                    throw ValidationException::withMessages(['document' => 'Only editable quotation/invoice drafts can change.']);
                }if ((int) $v['lock_version'] !== $document->lock_version) {
                    throw ValidationException::withMessages(['document' => 'A newer draft exists. Reload before saving.']);
                }
            } elseif ($existing = FinancialDocument::where('draft_key', $v['draft_key'])->first()) {
                if ($existing->kind !== $v['kind'] || $existing->client_id !== (int) $v['client_id']) {
                    throw ValidationException::withMessages(['draft_key' => 'This draft key already belongs to a different record.']);
                }

return $existing;
            }
            $customer = $v['customer'];
            BillingDetails::check($customer);
            $seller = $this->seller();
            $calc = app(DocumentCalculator::class)->calculate($v['items'], $seller, $v['place_of_supply']);
            $attrs = Arr::only($v, ['client_id', 'project_id', 'reference', 'document_date', 'due_date', 'place_of_supply', 'terms', 'notes']);
            $attrs['project_id'] = $v['project_id'] ?? null;
            $attrs['customer'] = $customer;
            if (! $document) {
                $attrs += ['kind' => $v['kind'], 'draft_key' => $v['draft_key']];
            } else {
                $attrs['lock_version'] = $document->lock_version + 1;
            }
            $document ??= new FinancialDocument;
            $document->fill($attrs + $calc);
            $document->save();
            Audit::record('finance.draft_saved', $document, ['version' => $document->lock_version]);

            return $document;
        });
    }

    private function number(string $kind, string $date, array $seller): array
    {
        $fy = Money::year($date);
        $s = DocumentSequence::firstOrCreate(['kind' => $kind, 'financial_year' => $fy], ['last_number' => 0]);
        $s = DocumentSequence::lockForUpdate()->findOrFail($s->id);
        if ($s->last_number >= 99999) {
            throw ValidationException::withMessages(['number' => 'The supported financial-year sequence is exhausted.']);
        }$s->increment('last_number');
        $prefix = $seller[$kind.'_prefix'] ?? ['quotation' => 'QTN', 'invoice' => 'INV', 'receipt' => 'RCT'][$kind];

        return ['number' => $prefix.'/'.substr($fy, 2, 2).substr($fy, -2).'/'.str_pad($s->last_number, 5, '0', STR_PAD_LEFT), 'financial_year' => $fy];
    }

    public function issue(FinancialDocument $doc, int $version): FinancialDocument
    {
        return DB::transaction(function () use ($doc, $version) {
            $this->lockBusiness();
            $doc = FinancialDocument::lockForUpdate()->findOrFail($doc->id);
            if ($doc->status === 'issued') {
                return $doc;
            }
            if ($doc->status !== 'draft' || $doc->kind === 'receipt' || $version !== $doc->lock_version) {
                throw ValidationException::withMessages(['document' => 'Only the current draft can be issued.']);
            }$seller = $this->seller();
            BillingDetails::ready($seller);
            $calc = app(DocumentCalculator::class)->calculate($doc->items, $seller, $doc->place_of_supply);
            // Tax setup changes after drafting require a deliberate save/review of the new totals.
            foreach (['total_minor', 'cgst_minor', 'sgst_minor', 'igst_minor', 'taxable_minor'] as $key) {
                if ((int) $doc->$key !== $calc[$key]) {
                    throw ValidationException::withMessages(['document' => 'Billing/tax settings changed. Save and review the draft again before issuing.']);
                }
            }
            $nums = $this->number($doc->kind, $doc->document_date->toDateString(), $seller);
            $snapshot = ['seller' => $seller, 'logo_path' => BillingSetting::first()?->logo_path, 'customer' => $doc->customer, 'items' => $calc['items'], 'totals' => Arr::except($calc, ['items']), 'terms' => $doc->terms, 'notes' => $doc->notes, 'reference' => $doc->reference, 'place_of_supply' => $doc->place_of_supply, 'tax_basis' => $seller['tax_basis'], 'demo' => (bool) config('spacekind.demo')];
            $doc->fill($nums + ['status' => 'issued', 'issued_at' => now(), 'snapshot' => $snapshot]);
            $doc->save();
            Audit::record('finance.document_issued', $doc, ['kind' => $doc->kind, 'total_minor' => $doc->total_minor]);

            return $doc;
        });
    }

    public function sent(FinancialDocument $doc, string $note): void
    {
        DB::transaction(function () use ($doc, $note) {
            $this->lockBusiness();
            $d = FinancialDocument::lockForUpdate()->findOrFail($doc->id);
            if ($d->kind !== 'quotation' || $d->status !== 'issued') {
                throw ValidationException::withMessages(['quotation' => 'Only issued quotations can be marked sent.']);
            }if (! $d->sent_at) {
                $d->update(['sent_at' => now(), 'sent_note' => $note]);
                Audit::record('finance.quotation_marked_sent', $d, ['note' => $note]);
            }
        });
    }

    public function decision(FinancialDocument $doc, string $decision, string $note): void
    {
        DB::transaction(function () use ($doc, $decision, $note) {
            $this->lockBusiness();
            $d = FinancialDocument::lockForUpdate()->findOrFail($doc->id);
            if ($d->kind !== 'quotation' || $d->status !== 'issued' || $d->due_date?->isBefore(today())) {
                throw ValidationException::withMessages(['decision' => 'Only a valid issued quotation can receive a recorded decision.']);
            }if (FinancialDocument::where('source_quote_id', $d->id)->exists()) {
                throw ValidationException::withMessages(['decision' => 'An invoice already references this quotation.']);
            }$d->update(['decision' => $decision, 'decided_at' => now(), 'decision_note' => $note]);
            Audit::record('finance.quote_decision', $d, ['decision' => $decision]);
        });
    }

    public function invoiceFromQuote(FinancialDocument $quote): FinancialDocument
    {
        return DB::transaction(function () use ($quote) {
            $this->lockBusiness();
            $q = FinancialDocument::lockForUpdate()->findOrFail($quote->id);
            if ($old = FinancialDocument::where('source_quote_id', $q->id)->first()) {
                return $old;
            }
            if ($q->kind !== 'quotation' || $q->status !== 'issued' || $q->decision !== 'accepted') {
                throw ValidationException::withMessages(['quotation' => 'Record acceptance of an issued quotation first.']);
            }$d = $this->copy($q, 'invoice');
            $d->source_quote_id = $q->id;
            $d->reference = $q->number;
            $d->save();
            Audit::record('finance.quote_converted', $q, ['invoice_id' => $d->id]);

            return $d;
        });
    }

    public function copy(FinancialDocument $doc, ?string $kind = null): FinancialDocument
    {
        if ($doc->kind === 'receipt') {
            throw ValidationException::withMessages(['document' => 'Receipts are generated only from recorded payments.']);
        }
        $attrs = $doc->only(['client_id', 'project_id', 'customer', 'place_of_supply', 'items', 'terms', 'notes', 'subtotal_minor', 'discount_minor', 'taxable_minor', 'cgst_minor', 'sgst_minor', 'igst_minor', 'total_minor']);
        $attrs += ['kind' => $kind ?? $doc->kind, 'draft_key' => (string) Str::uuid(), 'document_date' => today(), 'due_date' => today()->addDays(30), 'reference' => $doc->number ?: $doc->reference];
        $d = FinancialDocument::create($attrs);
        Audit::record('finance.document_copied', $d, ['copied_from' => $doc->id]);

        return $d;
    }

    public function void(FinancialDocument $doc, string $reason): void
    {
        DB::transaction(function () use ($doc, $reason) {
            $this->lockBusiness();
            $d = FinancialDocument::lockForUpdate()->findOrFail($doc->id);
            if ($d->kind === 'receipt') {
                throw ValidationException::withMessages(['document' => 'Reverse the payment instead; its receipt and allocations remain traceable.']);
            }if ($d->status === 'void') {
                return;
            }if ($d->kind === 'quotation' && FinancialDocument::where('source_quote_id', $d->id)->where('status', '!=', 'void')->exists()) {
                throw ValidationException::withMessages(['document' => 'Void the linked invoice draft/issued record first.']);
            }if ($d->kind === 'invoice' && $d->paid_minor > 0) {
                throw ValidationException::withMessages(['document' => 'Reverse recorded payments before voiding this invoice. This does not refund money.']);
            }$d->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => $reason]);
            $d->shares()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            Audit::record('finance.document_voided', $d, ['reason' => $reason]);
        });
    }

    public function recordPayment(array $v): CustomerPayment
    {
        return DB::transaction(function () use ($v) {
            $this->lockBusiness();
            $amount = Money::scaled($v['amount']);
            if ($amount < 1) {
                throw ValidationException::withMessages(['amount' => 'Record a positive amount.']);
            }
            $invoice = ! empty($v['invoice_id']) ? FinancialDocument::where('client_id', $v['client_id'])->where('kind', 'invoice')->lockForUpdate()->findOrFail($v['invoice_id']) : null;
            $project = ! empty($v['project_id']) ? (int) $v['project_id'] : $invoice?->project_id;
            $v['project_id'] = $project;
            if ($old = CustomerPayment::where('submission_key', $v['submission_key'])->first()) {
                if ($old->amount_minor !== $amount || $old->client_id !== (int) $v['client_id'] || $old->project_id !== $project || $old->method !== $v['method'] || $old->paid_on->toDateString() !== $v['paid_on'] || ($old->reference ?? '') !== ($v['reference'] ?? '') || ($old->note ?? '') !== ($v['note'] ?? '') || ($old->receipt->snapshot['payment']['invoice_id'] ?? null) !== $invoice?->id) {
                    throw ValidationException::withMessages(['submission_key' => 'This retry key already belongs to a different payment payload.']);
                }

return $old;
            }
            $this->parties((int) $v['client_id'], $project);
            $seller = $this->seller();
            BillingDetails::ready($seller);
            $client = Client::findOrFail($v['client_id']);
            if ($invoice) {
                $this->allocationAllowed($invoice, $amount, $project);
            }
            $customer = $invoice?->snapshot['customer'] ?? ['name' => $client->name, 'address' => $client->locality, 'state' => $seller['state'], 'phone' => $client->phone, 'email' => $client->email, 'gstin' => null];
            $snapshot = ['seller' => $seller, 'logo_path' => BillingSetting::first()?->logo_path, 'customer' => $customer, 'items' => [], 'totals' => ['total_minor' => $amount], 'terms' => null, 'notes' => $v['note'] ?? null, 'tax_basis' => 'none', 'place_of_supply' => $customer['state'] ?? $seller['state'], 'demo' => (bool) config('spacekind.demo'), 'payment' => ['paid_on' => $v['paid_on'], 'method' => $v['method'], 'reference' => $v['reference'] ?? null, 'invoice' => $invoice?->number, 'invoice_id' => $invoice?->id, 'is_advance' => ! $invoice]];
            $receipt = FinancialDocument::create($this->number('receipt', $v['paid_on'], $seller) + ['kind' => 'receipt', 'status' => 'issued', 'client_id' => $client->id, 'project_id' => $v['project_id'] ?? null, 'draft_key' => (string) Str::uuid(), 'document_date' => $v['paid_on'], 'customer' => $customer, 'items' => [], 'snapshot' => $snapshot, 'total_minor' => $amount, 'notes' => $v['note'] ?? null, 'issued_at' => now()]);
            $payment = CustomerPayment::create(['client_id' => $client->id, 'project_id' => $v['project_id'] ?? null, 'receipt_id' => $receipt->id, 'submission_key' => $v['submission_key'], 'amount_minor' => $amount, 'paid_on' => $v['paid_on'], 'method' => $v['method'], 'reference' => $v['reference'] ?? null, 'note' => $v['note'] ?? null]);
            if ($invoice) {
                $this->addAllocation($payment, $invoice, $amount, (string) Str::uuid());
            }Audit::record('finance.payment_recorded', $payment, ['amount_minor' => $amount, 'receipt_id' => $receipt->id, 'invoice_id' => $invoice?->id]);

            return $payment;
        });
    }

    private function allocationAllowed(FinancialDocument $invoice, int $amount, mixed $projectId): void
    {
        if ($invoice->status !== 'issued' || $invoice->kind !== 'invoice' || $amount > $invoice->balance_minor) {
            throw ValidationException::withMessages(['amount' => 'Allocation must not exceed the outstanding amount of an issued invoice.']);
        }if ($projectId && (int) $projectId !== (int) $invoice->project_id) {
            throw ValidationException::withMessages(['project_id' => 'The payment project must match the invoice project.']);
        }
    }

    private function addAllocation(CustomerPayment $p, FinancialDocument $d, int $amount, string $key): PaymentAllocation
    {
        return PaymentAllocation::create(['client_id' => $p->client_id, 'payment_id' => $p->id, 'invoice_id' => $d->id, 'amount_minor' => $amount, 'submission_key' => $key]);
    }

    public function allocate(CustomerPayment $p, array $v): void
    {
        DB::transaction(function () use ($p, $v) {
            $this->lockBusiness();
            $p = CustomerPayment::lockForUpdate()->findOrFail($p->id);
            if ($old = PaymentAllocation::where('submission_key', $v['submission_key'])->first()) {
                if ($old->payment_id !== $p->id || $old->invoice_id !== (int) $v['invoice_id'] || $old->amount_minor !== Money::scaled($v['amount'])) {
                    throw ValidationException::withMessages(['submission_key' => 'This allocation retry key is already in use.']);
                }

return;
            }$amount = Money::scaled($v['amount']);
            if ($amount < 1 || $p->reversed_at || $amount > $p->available_minor) {
                throw ValidationException::withMessages(['amount' => 'Allocation exceeds the available recorded advance.']);
            }$d = FinancialDocument::where('client_id', $p->client_id)->where('kind', 'invoice')->lockForUpdate()->findOrFail($v['invoice_id']);
            $this->allocationAllowed($d, $amount, $p->project_id);
            $this->addAllocation($p, $d, $amount, $v['submission_key']);
            Audit::record('finance.advance_allocated', $p, ['invoice_id' => $d->id, 'amount_minor' => $amount]);
        });
    }

    public function reverse(CustomerPayment $p, string $reason): void
    {
        DB::transaction(function () use ($p, $reason) {
            $this->lockBusiness();
            $p = CustomerPayment::lockForUpdate()->findOrFail($p->id);
            if ($p->reversed_at) {
                return;
            }$p->update(['reversed_at' => now(), 'reversal_reason' => $reason]);
            $p->allocations()->whereNull('reversed_at')->update(['reversed_at' => now()]);
            $p->receipt->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => 'Recorded payment reversed: '.$reason]);
            $p->receipt->shares()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            Audit::record('finance.payment_reversed', $p, ['reason' => $reason]);
        });
    }

    public function share(FinancialDocument $d, int $days): string
    {
        if ($d->status !== 'issued') {
            throw ValidationException::withMessages(['share' => 'Only issued, non-void documents can be shared.']);
        }$token = Str::random(64);
        $s = DocumentShare::create(['document_id' => $d->id, 'token_hash' => hash('sha256',$token), 'expires_at' => now()->addDays($days)]);
        Audit::record('finance.share_created',$d,['share_id' => $s->id, 'expires_at' => $s->expires_at->toIso8601String()]);

        return url('/document-share/'.$token);
    }
}
