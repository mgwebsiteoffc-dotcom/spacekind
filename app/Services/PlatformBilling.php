<?php

namespace App\Services;

use App\Models\PlatformBillingSetting;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformSequence;
use App\Support\Audit;
use App\Support\BillingDetails;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlatformBilling
{
    public const SERVICES = ['software_yearly' => ['label' => 'Standard software · All five modules, phased · Annual', 'rate_minor' => 499900], 'campaign_monthly' => ['label' => 'Optional campaign management · Meta spend separate · Monthly', 'rate_minor' => 299900]];

    public function preview(PlatformInvoice $d): array
    {
        $seller = PlatformBillingSetting::find(1)?->data ?? [];
        BillingDetails::ready($seller);
        $calc = app(DocumentCalculator::class)->calculate([['description' => self::SERVICES[$d->service]['label'], 'category' => 'service', 'quantity' => (string) $d->quantity, 'unit' => $d->service === 'software_yearly' ? 'year' : 'month', 'rate' => Money::decimal(self::SERVICES[$d->service]['rate_minor']), 'discount' => '0', 'tax_bps' => (int) ($seller['default_tax_bps'] ?? 0), 'hsn_sac' => $seller['hsn_sac'] ?? null]], $seller, $d->place_of_supply);

        return ['totals' => $calc, 'hash' => hash('sha256', json_encode($seller, JSON_THROW_ON_ERROR))];
    }

    public function issue(PlatformInvoice $d, string $reviewHash): void
    {
        DB::transaction(function () use ($d, $reviewHash) {
            $settings = PlatformBillingSetting::whereKey(1)->lockForUpdate()->first();
            $seller = $settings?->data ?? [];
            BillingDetails::ready($seller);
            if (! hash_equals(hash('sha256', json_encode($seller, JSON_THROW_ON_ERROR)), $reviewHash)) {
                throw ValidationException::withMessages(['invoice' => 'Platform billing settings changed. Reload and review the draft totals before issuing.']);
            }$d = PlatformInvoice::lockForUpdate()->findOrFail($d->id);
            if ($d->status === 'issued') {
                return;
            }if ($d->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Only drafts can be issued.']);
            }$rate = self::SERVICES[$d->service]['rate_minor'];
            $calc = app(DocumentCalculator::class)->calculate([['description' => self::SERVICES[$d->service]['label'], 'category' => 'service', 'quantity' => (string) $d->quantity, 'unit' => $d->service === 'software_yearly' ? 'year' : 'month', 'rate' => Money::decimal($rate), 'discount' => '0', 'tax_bps' => (int) ($seller['default_tax_bps'] ?? 0), 'hsn_sac' => $seller['hsn_sac'] ?? null]], $seller, $d->place_of_supply);
            $fy = Money::year($d->document_date->toDateString());
            $seq = PlatformSequence::firstOrCreate(['financial_year' => $fy], ['last_number' => 0]);
            $seq = PlatformSequence::lockForUpdate()->findOrFail($seq->id);
            if ($seq->last_number >= 99999) {
                throw ValidationException::withMessages(['invoice' => 'Sequence exhausted.']);
            }$seq->increment('last_number');
            $snapshot = ['seller' => $seller, 'customer' => $d->customer, 'items' => $calc['items'], 'totals' => collect($calc)->except('items')->all(), 'tax_basis' => $seller['tax_basis'], 'place_of_supply' => $d->place_of_supply, 'terms' => $seller['terms'] ?? null, 'notes' => 'Platform service invoice only. Does not activate a subscription, charge a gateway or fund Meta advertising. Service period '.$d->period_start->toDateString().' to '.$d->period_end->toDateString().'.', 'reference' => null, 'demo' => (bool) config('spacekind.demo')];
            $d->update(['status' => 'issued', 'number' => 'SK/'.substr($fy, 2, 2).substr($fy, -2).'/'.str_pad($seq->last_number, 5, '0', STR_PAD_LEFT), 'total_minor' => $calc['total_minor'], 'snapshot' => $snapshot]);
            Audit::record('platform.invoice_issued', $d, ['total_minor' => $d->total_minor]);
        });
    }

    public function payment(PlatformInvoice $d, array $v): void
    {
        DB::transaction(function () use ($d, $v) {
            PlatformBillingSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $d = PlatformInvoice::lockForUpdate()->findOrFail($d->id);
            PlatformCheckoutService::requireClosed($d);
            $amount = Money::scaled($v['amount']);
            if ($p = PlatformPayment::where('submission_key', $v['submission_key'])->first()) {
                if ($p->platform_invoice_id !== $d->id || $p->amount_minor !== $amount || $p->paid_on->toDateString() !== $v['paid_on'] || $p->reference !== $v['reference']) {
                    throw ValidationException::withMessages(['payment' => 'Payment retry key already used.']);
                }

return;
            }if ($d->status !== 'issued' || $amount < 1 || $amount > $d->balance_minor) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount no greater than the platform invoice balance.']);
            }$p = PlatformPayment::create(['platform_invoice_id' => $d->id, 'submission_key' => $v['submission_key'], 'amount_minor' => $amount, 'paid_on' => $v['paid_on'], 'reference' => $v['reference']]);
            Audit::record('platform.payment_recorded', $d, ['platform_payment_id' => $p->id, 'amount_minor' => $amount]);
        });
    }
}
