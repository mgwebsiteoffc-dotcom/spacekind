<?php

namespace App\Services;

use App\Models\PlatformCheckout;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\RazorpayCredential;
use App\Support\Audit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformCheckoutService
{
    public static function pending(PlatformInvoice $invoice): ?PlatformCheckout
    {
        return PlatformCheckout::where('platform_invoice_id', $invoice->id)->where('state', 'pending')->first();
    }

    // Call under the invoice lock before any manual change to the outstanding balance.
    public static function requireClosed(PlatformInvoice $invoice): void
    {
        if (self::pending($invoice)) {
            throw ValidationException::withMessages(['checkout' => 'Refresh the pending Razorpay checkout first. Cancel the link in Razorpay if you need to change this bill.']);
        }
    }

    public function handle(PlatformInvoice $invoice, bool $pay): ?string
    {
        abort_if(config('spacekind.demo') || $invoice->business->is_demo, 403);
        // Commit the stable provider reference BEFORE any network request, even on an ambiguous timeout.
        $attempt = DB::transaction(function () use ($invoice, $pay) {
            $bill = PlatformInvoice::lockForUpdate()->findOrFail($invoice->id);
            if ($existing = self::pending($bill)) {
                return $existing;
            }
            $mode = config('spacekind.razorpay_mode');
            abort_unless($pay && in_array($mode, ['test', 'live'], true) && $bill->status === 'issued' && $bill->balance_minor > 0, 409);
            abort_unless(RazorpayCredential::where('mode', $mode)->lockForUpdate()->first(), 409);
            return PlatformCheckout::create(['platform_invoice_id' => $bill->id, 'reference' => (string) Str::uuid(), 'amount_minor' => $bill->balance_minor, 'mode' => $mode]);
        });

        try {
            return DB::transaction(function () use ($attempt, $invoice, $pay) {
                $bill = PlatformInvoice::lockForUpdate()->findOrFail($invoice->id);
                $a = PlatformCheckout::lockForUpdate()->findOrFail($attempt->id);
                if ($a->state !== 'pending') {
                    return null;
                }
                $key = RazorpayCredential::where('mode', $a->mode)->firstOrFail();
                $api = Http::baseUrl('https://api.razorpay.com/v1')->withBasicAuth($key->key_id, $key->key_secret)->acceptJson()->withoutRedirecting()->connectTimeout(3)->timeout(8);
                $canPay = $pay && config('spacekind.razorpay_mode') === $a->mode;
                if ($a->provider_id) {
                    $r = $api->get('payment_links/'.$a->provider_id);
                    $link = $r->successful() ? $r->json() : null;
                } else {
                    $r = $api->get('payment_links', ['reference_id' => $a->reference]);
                    if (! $r->successful() || ! is_array($links = $r->json('payment_links'))) {
                        $this->unavailable();
                    }
                    $link = $links[0] ?? null;
                    if (! $link && ! $canPay && $a->created_at->lt(now()->subHour())) {
                        $a->update(['state' => 'expired']);
                        return null;
                    }
                    if (! $link && $canPay) {
                        $r = $api->post('payment_links', ['reference_id' => $a->reference, 'amount' => (int) $a->amount_minor, 'currency' => 'INR', 'accept_partial' => false, 'expire_by' => now()->addMinutes(30)->timestamp, 'description' => 'Spacekind service invoice '.$bill->number, 'notify' => ['sms' => false, 'email' => false], 'reminder_enable' => false]);
                        $link = $r->successful() ? $r->json() : null;
                    }
                }
                if (! is_array($link) || ($link['reference_id'] ?? '') !== $a->reference || ($link['currency'] ?? '') !== 'INR' || ($link['amount'] ?? null) !== (int) $a->amount_minor || ! preg_match('/^plink_[a-zA-Z0-9]+$/D', $link['id'] ?? '') || ($a->provider_id && $link['id'] !== $a->provider_id)) {
                    $this->unavailable();
                }
                $a->update(['provider_id' => $link['id']]);
                $status = $link['status'] ?? null;
                if ($status === 'paid' && ($link['amount_paid'] ?? null) === (int) $a->amount_minor) {
                    if ($a->mode === 'live') {
                        abort_unless($bill->status === 'issued' && $bill->balance_minor === (int) $a->amount_minor, 409);
                        PlatformPayment::create(['platform_invoice_id' => $bill->id, 'amount_minor' => $a->amount_minor, 'submission_key' => $a->reference, 'paid_on' => today(), 'reference' => $link['id']]);
                    }
                    $a->update(['state' => 'paid']);
                    Audit::record('platform.checkout_paid', $bill, ['mode' => $a->mode, 'reference' => $link['id']]);
                } elseif (in_array($status, ['expired', 'cancelled'], true) && ($link['amount_paid'] ?? null) === 0) {
                    $a->update(['state' => $status]);
                } elseif ($status !== 'created') {
                    $this->unavailable();
                } elseif ($canPay) {
                    $url = $link['short_url'] ?? '';
                    if (! preg_match('~^https://(?:rzp\.io|rzp\.io\.in)/[a-zA-Z0-9_/-]+$~D', $url)) {
                        $this->unavailable();
                    }
                    return $url;
                }
                return null;
            });
        } catch (ConnectionException) {
            $this->unavailable();
        }
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['checkout' => 'Razorpay could not confirm this checkout. Refresh after checking the merchant account. No payment was recorded.']);
    }
}
