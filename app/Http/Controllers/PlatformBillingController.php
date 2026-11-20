<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Services\FinancialPdf;
use App\Services\PlatformCheckoutService;
use App\Services\PlatformBilling;
use App\Support\Audit;
use App\Support\BillingDetails;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformBillingController extends Controller
{
    public function index()
    {
        return view('finance.platform', ['invoices' => PlatformInvoice::with('business')->latest('id')->paginate(20), 'businesses' => Business::orderBy('name')->get(), 'settings' => PlatformBillingSetting::find(1)?->data ?? [], 'states' => BillingDetails::STATES]);
    }

    public function settings(Request $r)
    {
        $v = $r->validate([...BillingDetails::rules(), 'tax_basis' => ['required', Rule::in(['none', 'exclusive', 'inclusive'])], 'default_tax_bps' => ['required', 'integer', Rule::in(BillingDetails::RATES)], 'hsn_sac' => ['nullable', 'regex:/^[0-9]{4,8}$/D'], 'terms' => 'required|string|min:10|max:4000', 'reviewed' => 'accepted']);
        BillingDetails::check($v, true);
        if ($v['tax_basis'] === 'none' && $v['default_tax_bps']) {
            throw ValidationException::withMessages(['default_tax_bps' => 'Non-GST configuration must use 0%.']);
        }PlatformBillingSetting::updateOrCreate(['id' => 1], ['data' => Arr::except($v, 'reviewed')]);
        Audit::record('platform.billing_setup_saved');

        return back()->with('success', 'Platform billing basis saved. No gateway, subscription or ad spend was activated.');
    }

    public function store(Request $r)
    {
        $v = $r->validate(['business_id' => 'required|integer|exists:businesses,id', 'submission_key' => 'required|uuid', 'service' => ['required', Rule::in(array_keys(PlatformBilling::SERVICES))], 'quantity' => 'required|integer|min:1|max:12', 'document_date' => 'required|date_format:Y-m-d|after_or_equal:2000-04-01|before_or_equal:today', 'due_date' => 'required|date_format:Y-m-d|before:2100-01-01|after_or_equal:document_date', 'period_start' => 'required|date_format:Y-m-d|after_or_equal:2000-04-01|before:2100-01-01', 'period_end' => 'required|date_format:Y-m-d|before:2100-01-01|after_or_equal:period_start', 'customer' => 'required|array', ...BillingDetails::rules('customer.'), 'place_of_supply' => ['required', 'string', 'size:2', Rule::in(array_map('strval', array_keys(BillingDetails::STATES)))]]);
        BillingDetails::check($v['customer']);
        if ($v['service'] === 'software_yearly' && $v['quantity'] !== 1 && $v['quantity'] !== '1') {
            throw ValidationException::withMessages(['quantity' => 'Annual software is one standard yearly package, not per-user pricing.']);
        }$d = PlatformInvoice::firstOrCreate(['submission_key' => $v['submission_key']], Arr::except($v, 'submission_key'));
        foreach (['business_id', 'service', 'quantity', 'document_date', 'due_date', 'period_start', 'period_end', 'customer', 'place_of_supply'] as $key) {
            $value = in_array($key, ['document_date', 'due_date', 'period_start', 'period_end']) ? $d->$key->toDateString() : $d->$key;
            if ($value != $v[$key]) {
                throw ValidationException::withMessages(['submission_key' => 'This platform invoice retry key belongs to another payload.']);
            }
        }Audit::record('platform.invoice_draft_saved', $d);

        return redirect('/admin/billing/'.$d->id);
    }

    public function show(PlatformInvoice $invoice, PlatformBilling $billing)
    {
        $preview = null;
        try {
            if ($invoice->status === 'draft') {
                $preview = $billing->preview($invoice);
            }
        } catch (ValidationException $e) {
        }

return view('finance.platform-show', ['invoice' => $invoice->load(['business', 'payments']), 'owner' => false, 'preview' => $preview]);
    }

    public function issue(Request $r, PlatformInvoice $invoice, PlatformBilling $billing)
    {
        $v = $r->validate(['confirm' => 'accepted', 'review_hash' => 'required|string|size:64']);
        $billing->issue($invoice, $v['review_hash']);

        return back()->with('success', 'Platform invoice issued as an immutable record. No charge or subscription activation occurred.');
    }

    public function payment(Request $r, PlatformInvoice $invoice, PlatformBilling $billing)
    {
        $v = $r->validate(['submission_key' => 'required|uuid', 'amount' => 'required|string', 'paid_on' => 'required|date_format:Y-m-d|after_or_equal:2000-04-01|before_or_equal:today', 'reference' => 'required|string|min:3|max:120']);
        $billing->payment($invoice, $v);

        return back()->with('success', 'Separate platform payment reference recorded. This does not verify a bank transaction or activate service.');
    }

    public function reverse(Request $r, PlatformInvoice $invoice, PlatformPayment $payment)
    {
        abort_unless($payment->platform_invoice_id === $invoice->id, 404);
        $v = $r->validate(['reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($payment, $invoice, $v) {
            PlatformCheckoutService::requireClosed(PlatformInvoice::lockForUpdate()->findOrFail($invoice->id));
            $p = PlatformPayment::lockForUpdate()->findOrFail($payment->id);
            if (! $p->reversed_at) {
                $p->update(['reversed_at' => now(), 'reversal_reason' => $v['reason']]);
                Audit::record('platform.payment_reversed', $invoice, ['platform_payment_id' => $p->id, 'reason' => $v['reason']]);
            }
        });

        return back()->with('success', 'Platform payment entry reversed. No refund was made.');
    }

    public function void(Request $r, PlatformInvoice $invoice)
    {
        $v = $r->validate(['reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($invoice, $v) {
            $d = PlatformInvoice::lockForUpdate()->findOrFail($invoice->id);
            PlatformCheckoutService::requireClosed($d);
            if ($d->status === 'void') {
                return;
            }if ($d->paid_minor > 0) {
                throw ValidationException::withMessages(['invoice' => 'Reverse recorded platform payments before voiding.']);
            }$d->update(['status' => 'void', 'void_reason' => $v['reason']]);
            Audit::record('platform.invoice_voided', $d, ['reason' => $v['reason']]);
        });

        return back()->with('success', 'Platform invoice voided; history retained.');
    }

    public function checkout(int $invoice, string $action, PlatformCheckoutService $checkout)
    {
        $bill = PlatformInvoice::where('business_id', app(Tenant::class)->id())->where('status', 'issued')->findOrFail($invoice);
        $url = $checkout->handle($bill, $action === 'pay');
        return $url ? redirect()->away($url, 303) : back()->with('success', 'Razorpay status refreshed. Review the checkout status and balance below.');
    }

    public function refreshCheckout(PlatformInvoice $invoice, PlatformCheckoutService $checkout)
    {
        $checkout->handle($invoice, false);
        return back()->with('success', 'Razorpay status refreshed.');
    }

    public function ownerIndex()
    {
        return view('finance.subscription', ['invoices' => PlatformInvoice::where('business_id', app(Tenant::class)->id())->whereIn('status', ['issued', 'void'])->whereNotNull('number')->latest('id')->paginate(20)]);
    }

    public function ownerShow(int $invoice)
    {
        return view('finance.platform-show', ['invoice' => PlatformInvoice::where('business_id', app(Tenant::class)->id())->whereIn('status', ['issued', 'void'])->whereNotNull('number')->with('payments')->findOrFail($invoice), 'owner' => true]);
    }

    public function pdf(PlatformInvoice $invoice, FinancialPdf $pdf)
    {
        return $this->download($invoice, $pdf);
    }

    public function ownerPdf(int $invoice, FinancialPdf $pdf)
    {
        return $this->download(PlatformInvoice::where('business_id', app(Tenant::class)->id())->whereIn('status', ['issued', 'void'])->whereNotNull('number')->findOrFail($invoice), $pdf);
    }

    private function download(PlatformInvoice $d, FinancialPdf $pdf)
    {
        abort_unless($d->snapshot, 422);
        $document = (object) ['kind' => 'invoice', 'status' => $d->status, 'number' => $d->number, 'label' => $d->number, 'document_date' => $d->document_date, 'due_date' => $d->due_date, 'void_reason' => $d->void_reason];
        $bytes = $pdf->bytes(view('finance.pdf', ['document' => $document, 's' => $d->snapshot, 'logo' => null, 'platform' => true])->render());
        Audit::record('platform.invoice_pdf_downloaded', $d);

        return response($bytes)->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="'.str_replace('/','-',$d->number).'.pdf"')->header('Cache-Control','no-store, private');
    }
}
