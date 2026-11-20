<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\BillingSetting;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Models\Client;
use App\Models\CustomerPayment;
use App\Models\DocumentSequence;
use App\Models\DocumentShare;
use App\Models\FinancialDocument;
use App\Models\PaymentAllocation;
use App\Models\Project;
use App\Services\FinancialPdf;
use App\Services\FinancialWorkflow;
use App\Support\Audit;
use App\Support\BillingDetails;
use App\Support\Money;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    private function query(Request $r)
    {
        $v = $r->validate(['kind' => ['nullable', Rule::in(['quotation', 'invoice', 'receipt'])], 'q' => 'nullable|string|max:80', 'client_id' => 'nullable|integer', 'project_id' => 'nullable|integer', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($r->filled('from'), 'after_or_equal:from')], 'fy' => ['nullable', 'regex:/^[0-9]{4}-[0-9]{2}$/D'], 'status' => ['nullable', Rule::in(['draft', 'issued', 'void', 'unpaid', 'part-paid', 'paid', 'overdue', 'accepted', 'rejected', 'expired', 'sent'])]]);
        $q = FinancialDocument::with(['client', 'project'])->where('kind', $v['kind'] ?? 'invoice');
        foreach (['client_id', 'project_id'] as $key) {
            if (! empty($v[$key])) {
                $q->where($key, $v[$key]);
            }
        }
        if (! empty($v['q'])) {
            $q->where(fn ($b) => $b->where('number', 'like', '%'.$v['q'].'%')->orWhere('customer->name', 'like', '%'.$v['q'].'%'));
        }
        if (! empty($v['from'])) {
            $q->whereDate('document_date', '>=', $v['from']);
        }if (! empty($v['to'])) {
            $q->whereDate('document_date', '<=', $v['to']);
        }
        if (! empty($v['fy'])) {
            if (substr((string) ((int) substr($v['fy'], 0, 4) + 1), -2) !== substr($v['fy'], -2)) {
                throw ValidationException::withMessages(['fy' => 'Use a consecutive financial year, for example 2026-27.']);
            }$q->whereBetween('document_date', [substr($v['fy'], 0, 4).'-04-01', ((int) substr($v['fy'], 0, 4) + 1).'-03-31']);
        }
        $s = $v['status'] ?? '';
        $paid = 'COALESCE((SELECT SUM(pa.amount_minor) FROM payment_allocations pa WHERE pa.business_id=financial_documents.business_id AND pa.invoice_id=financial_documents.id AND pa.reversed_at IS NULL),0)';
        if (in_array($s, ['draft', 'issued', 'void'])) {
            $q->where('status', $s);
        } elseif (in_array($s, ['unpaid', 'part-paid', 'paid', 'overdue'])) {
            $q->where('kind', 'invoice')->where('status', 'issued');
            if ($s === 'paid') {
                $q->whereRaw($paid.' = total_minor');
            } else {
                $q->whereRaw($paid.' < total_minor');
            }if ($s === 'unpaid') {
                $q->whereRaw($paid.' = 0');
            }if ($s === 'part-paid') {
                $q->whereRaw($paid.' > 0');
            }if ($s === 'overdue') {
                $q->whereDate('due_date', '<', today());
            }
        } elseif ($s === 'sent') {
            $q->where('kind', 'quotation')->where('status', 'issued')->whereNotNull('sent_at')->whereNull('decision')->whereDate('due_date', '>=', today());
        } elseif (in_array($s, ['accepted', 'rejected'])) {
            $q->where('decision', $s)->where('status', 'issued');
        } elseif ($s === 'expired') {
            $q->where('kind', 'quotation')->where('status', 'issued')->whereNull('decision')->whereDate('due_date', '<', today());
        }

        return $q->latest('id');
    }

    public function index(Request $r)
    {
        $documents = $this->query($r)->paginate(15)->withQueryString();
        $issued = FinancialDocument::where('kind', 'invoice')->where('status', 'issued');

        return view('finance.index', ['documents' => $documents, 'kind' => $r->input('kind', 'invoice'), 'clients' => Client::orderBy('name')->get(), 'projects' => Project::orderBy('name')->get(), 'billed' => (clone $issued)->sum('total_minor'), 'paid' => PaymentAllocation::whereNull('reversed_at')->sum('amount_minor'), 'advance' => CustomerPayment::whereNull('reversed_at')->sum('amount_minor') - PaymentAllocation::whereNull('reversed_at')->sum('amount_minor')]);
    }

    public function create(Request $r)
    {
        $kind = $r->validate(['kind' => ['required', Rule::in(['quotation', 'invoice'])]])['kind'];

        return $this->editor($r, null, $kind);
    }

    public function edit(Request $r, FinancialDocument $document)
    {
        abort_unless($document->status === 'draft' && $document->kind !== 'receipt', 404);

        return $this->editor($r, $document, $document->kind);
    }

    private function editor(Request $r, ?FinancialDocument $d, string $kind)
    {
        $clients = Client::orderBy('name')->get();
        $projects = Project::orderBy('name')->get();
        $selected = $d?->client_id ?? $r->integer('client_id');
        $project = $d?->project_id ?? $r->integer('project_id');
        if ($project) {
            $p = Project::findOrFail($project);
            $selected = $p->client_id;
        }if ($selected) {
            Client::findOrFail($selected);
        }

return view('finance.editor', ['document' => $d, 'kind' => $kind, 'clients' => $clients, 'projects' => $projects, 'selectedClient' => $selected, 'selectedProject' => $project, 'catalogue' => CatalogueItem::where('is_active', true)->orderBy('name')->get(), 'seller' => app(FinancialWorkflow::class)->seller(), 'states' => BillingDetails::STATES]);
    }

    private function data(Request $r, ?FinancialDocument $d = null): array
    {
        return $r->validate(['kind' => ['required', Rule::in(['quotation', 'invoice'])], 'draft_key' => 'required|uuid', 'lock_version' => $d ? 'required|integer|min:1' : 'nullable|integer', 'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('business_id', app(Tenant::class)->id())], 'project_id' => 'nullable|integer', 'reference' => 'nullable|string|max:120', 'document_date' => 'required|date_format:Y-m-d|after_or_equal:2000-04-01|before_or_equal:today', 'due_date' => 'required|date_format:Y-m-d|before:2100-01-01|after_or_equal:document_date', 'customer' => 'required|array', ...BillingDetails::rules('customer.'), 'place_of_supply' => ['required', 'string', 'size:2', Rule::in(array_map('strval', array_keys(BillingDetails::STATES)))], 'items' => 'required|array|min:1|max:50', 'items.*.description' => 'required|string|max:240', 'items.*.category' => ['required', Rule::in(['material', 'labour', 'service'])], 'items.*.unit' => 'required|string|max:20', 'items.*.quantity' => ['required', 'regex:/^\d{1,5}(?:\.\d{1,3})?$/D'], 'items.*.rate' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'], 'items.*.discount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'], 'items.*.tax_bps' => ['required', 'integer', Rule::in(BillingDetails::RATES)], 'items.*.hsn_sac' => ['nullable', 'regex:/^[0-9]{4,8}$/D'], 'terms' => 'nullable|string|max:4000', 'notes' => 'nullable|string|max:2000']);
    }

    public function store(Request $r, FinancialWorkflow $f)
    {
        $d = $f->draft($this->data($r));

        return redirect('/workspace/finance/documents/'.$d->id)->with('success', 'Draft saved. Review, then issue to allocate a permanent number. Nothing was sent.');
    }

    public function update(Request $r, FinancialDocument $document, FinancialWorkflow $f)
    {
        $d = $f->draft($this->data($r, $document), $document);

        return redirect('/workspace/finance/documents/'.$d->id)->with('success', 'Draft saved. Totals are calculated on the server.');
    }

    public function show(FinancialDocument $document)
    {
        $document->load(['client', 'project', 'shares', 'allocations']);

        return view('finance.show', ['document' => $document, 'payments' => CustomerPayment::where('client_id', $document->client_id)->with('receipt')->latest('id')->get(), 'availableInvoices' => FinancialDocument::where('client_id', $document->client_id)->where('kind', 'invoice')->where('status', 'issued')->get(), 'history' => AuditEvent::where('subject', 'FinancialDocument:'.$document->id)->latest('id')->limit(20)->get()]);
    }

    public function issue(Request $r, FinancialDocument $document, FinancialWorkflow $f)
    {
        $v = $r->validate(['lock_version' => 'required|integer|min:1', 'confirm' => 'accepted']);
        $f->issue($document, $v['lock_version']);

        return back()->with('success', 'Document issued and frozen. It has not been sent and no payment was collected.');
    }

    public function sent(Request $r, FinancialDocument $document, FinancialWorkflow $f)
    {
        $v = $r->validate(['note' => 'required|string|min:5|max:500']);
        $f->sent($document, $v['note']);

        return back()->with('success', 'Quotation marked sent using your recorded context. Spacekind did not send or verify delivery.');
    }

    public function decision(Request $r, FinancialDocument $document, FinancialWorkflow $f)
    {
        $v = $r->validate(['decision' => ['required', Rule::in(['accepted', 'rejected'])], 'note' => 'required|string|min:5|max:500']);
        $f->decision($document, $v['decision'], $v['note']);

        return back()->with('success', 'Studio-recorded decision saved; not a digital signature.');
    }

    public function convert(FinancialDocument $document, FinancialWorkflow $f)
    {
        $d = $f->invoiceFromQuote($document);

        return redirect('/workspace/finance/documents/'.$d->id)->with('success', 'Invoice draft prepared from the accepted quotation. Review before issuing.');
    }

    public function copy(FinancialDocument $document, FinancialWorkflow $f)
    {
        $d = $f->copy($document);

        return redirect('/workspace/finance/documents/'.$d->id.'/edit')->with('success', 'New draft copied. The original record is unchanged.');
    }

    public function void(Request $r, FinancialDocument $document, FinancialWorkflow $f)
    {
        $v = $r->validate(['reason' => 'required|string|min:5|max:500']);
        $f->void($document, $v['reason']);

        return back()->with('success', 'Record voided with history retained. No bank refund was made.');
    }

    public function reminder(Request $r, FinancialDocument $document)
    {
        abort_unless($document->kind === 'invoice' && $document->status === 'issued', 422);
        $v = $r->validate(['reminder_at' => 'nullable|date', 'reminder_note' => 'nullable|required_with:reminder_at|string|max:180']);
        $document->update($v);
        Audit::record('finance.reminder_saved', $document);

        return back()->with('success', 'In-app invoice reminder saved. No message/email is scheduled.');
    }

    public function pdf(FinancialDocument $document, FinancialPdf $pdf)
    {
        return $this->pdfResponse($document, $pdf->render($document));
    }

    private function pdfResponse(FinancialDocument $d, string $bytes)
    {
        Audit::record('finance.pdf_downloaded', $d);

        return response($bytes)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="'.str_replace('/', '-', $d->number ?? 'draft-'.$d->id).'.pdf"')->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function share(Request $r, FinancialDocument $document, FinancialWorkflow $f)
    {
        $v = $r->validate(['days' => ['required', 'integer', Rule::in([1, 7, 30])], 'confirm' => 'accepted']);

        return back()->with('share_url', $f->share($document, $v['days']))->with('success', 'Private share link created. Anyone holding it can view this document until expiry/revocation.');
    }

    public function revoke(FinancialDocument $document, DocumentShare $share)
    {
        abort_unless($share->document_id === $document->id, 404);
        $share->update(['revoked_at' => now()]);
        Audit::record('finance.share_revoked', $document, ['share_id' => $share->id]);

        return back()->with('success', 'Share link revoked. Downloaded copies cannot be recalled.');
    }

    public function shared(Request $r, string $token, FinancialPdf $pdf)
    {
        $s = DocumentShare::withoutGlobalScope('business')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();
        $business = Business::where('account_status', 'pilot')->findOrFail($s->business_id);

        return app(Tenant::class)->run($business, function () use ($r, $s, $pdf) {
            $d = FinancialDocument::where('status', 'issued')->findOrFail($s->document_id);

            return $r->routeIs('financial.share.pdf') ? response($pdf->render($d))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="'.str_replace('/', '-', $d->number).'.pdf"')->header('Cache-Control', 'no-store, private') : response()->view('finance.shared', ['document' => $d, 'share' => $s])->header('Cache-Control', 'no-store, private');
        });
    }

    public function settings()
    {
        return view('finance.settings', ['setting' => BillingSetting::first(), 'states' => BillingDetails::STATES]);
    }

    public function saveSettings(Request $r)
    {
        $v = $r->validate([...BillingDetails::rules(), 'tax_basis' => ['required', Rule::in(['none', 'exclusive', 'inclusive'])], 'quotation_prefix' => ['required', 'regex:/^[A-Z][A-Z0-9]{0,4}$/D'], 'invoice_prefix' => ['required', 'regex:/^[A-Z][A-Z0-9]{0,4}$/D'], 'receipt_prefix' => ['required', 'regex:/^[A-Z][A-Z0-9]{0,4}$/D'], 'bank_details' => 'nullable|string|max:600', 'terms' => 'nullable|string|max:4000', 'reviewed' => 'accepted', 'logo' => 'nullable|image|mimes:jpg,jpeg|max:1024|dimensions:min_width=32,min_height=32,max_width=1600,max_height=1600']);
        BillingDetails::check($v, true);
        if (count(array_unique([$v['quotation_prefix'], $v['invoice_prefix'], $v['receipt_prefix']])) !== 3) {
            throw ValidationException::withMessages(['invoice_prefix' => 'Use distinct prefixes for quotations, invoices and receipts.']);
        }DB::transaction(function () use ($r, $v) {
            Business::whereKey(app(Tenant::class)->id())->lockForUpdate()->firstOrFail();
            $setting = BillingSetting::firstOrNew([]);
            $old = $setting->data ?? [];
            foreach (['quotation', 'invoice', 'receipt'] as $kind) {
                if (isset($old[$kind.'_prefix']) && $old[$kind.'_prefix'] !== $v[$kind.'_prefix'] && DocumentSequence::where('kind', $kind)->where('last_number', '>', 0)->exists()) {
                    throw ValidationException::withMessages([$kind.'_prefix' => 'A prefix already used for issued records cannot be changed in this build.']);
                }
            }$setting->data = Arr::except($v, ['logo', 'reviewed']);
            if ($r->hasFile('logo')) {
                $setting->logo_path = $r->file('logo')->storeAs('billing-logos/'.app(Tenant::class)->id(), Str::uuid().'.jpg', 'local');
            }$setting->save();
            Audit::record('finance.billing_setup_saved', $setting);
        });

        return back()->with('success', 'Billing setup saved. Issued document snapshots remain unchanged. GST registration/treatment still needs professional review.');
    }

    public function catalogue()
    {
        return view('finance.catalogue', ['items' => CatalogueItem::orderByDesc('is_active')->latest('id')->get()]);
    }

    public function saveCatalogue(Request $r)
    {
        $v = $r->validate(['id' => 'nullable|integer', 'name' => 'required|string|max:160', 'category' => ['required', Rule::in(['material', 'labour', 'service'])], 'unit' => 'required|string|max:20', 'rate' => 'required|string', 'tax_bps' => ['required', 'integer', Rule::in(BillingDetails::RATES)], 'hsn_sac' => ['nullable', 'regex:/^[0-9]{4,8}$/D'], 'is_active' => 'nullable|boolean']);
        $item = ! empty($v['id']) ? CatalogueItem::findOrFail($v['id']) : new CatalogueItem;
        $item->fill(Arr::except($v, ['id', 'rate']) + ['rate_minor' => Money::scaled($v['rate'], 2, 1000000000), 'is_active' => $r->boolean('is_active')]);
        $item->save();
        Audit::record('finance.catalogue_saved', $item);

        return back()->with('success', 'Catalogue saved. Existing quotation/invoice lines do not change.');
    }

    public function payments(Request $r)
    {
        $r->validate(['client_id' => 'nullable|integer', 'project_id' => 'nullable|integer']);
        $q = CustomerPayment::with(['client', 'receipt']);
        foreach (['client_id', 'project_id'] as $key) {
            if ($r->filled($key)) {
                $q->where($key, $r->integer($key));
            }
        }

return view('finance.payments', ['payments' => $q->latest('id')->paginate(20), 'clients' => Client::orderBy('name')->get(), 'projects' => Project::orderBy('name')->get(), 'invoices' => FinancialDocument::where('kind', 'invoice')->where('status', 'issued')->get()]);
    }

    public function recordPayment(Request $r, FinancialWorkflow $f)
    {
        $v = $r->validate(['submission_key' => 'required|uuid', 'client_id' => 'required|integer', 'project_id' => 'nullable|integer', 'invoice_id' => 'nullable|integer', 'amount' => 'required|string', 'paid_on' => 'required|date_format:Y-m-d|after_or_equal:2000-04-01|before_or_equal:today', 'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'cheque', 'other'])], 'reference' => 'nullable|required_unless:method,cash|string|max:120', 'note' => 'nullable|string|max:500']);
        $p = $f->recordPayment($v);

        return redirect('/workspace/finance/documents/'.$p->receipt_id)->with('success', 'Payment recorded and numbered receipt created. This is not bank/gateway verification.');
    }

    public function allocate(Request $r, CustomerPayment $payment, FinancialWorkflow $f)
    {
        $v = $r->validate(['submission_key' => 'required|uuid', 'invoice_id' => 'required|integer', 'amount' => 'required|string']);
        $f->allocate($payment, $v);

        return back()->with('success', 'Recorded advance allocated. No new payment or receipt was created.');
    }

    public function reverse(Request $r, CustomerPayment $payment, FinancialWorkflow $f)
    {
        $v = $r->validate(['reason' => 'required|string|min:5|max:500']);
        $f->reverse($payment, $v['reason']);

        return back()->with('success', 'Payment entry reversed; receipt marked void and allocations reversed. No money was refunded.');
    }

    public function export(Request $r)
    {
        $q = $this->query($r);
        $business = app(Tenant::class)->business();
        Audit::record('finance.register_exported');

        return response()->streamDownload(function () use ($q, $business) {
            app(Tenant::class)->run($business, function () use ($q) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Number', 'Date', 'Client', 'Project', 'Status', 'Taxable INR', 'GST INR', 'Total INR', 'Paid INR', 'Balance INR', 'Due / valid until'], ',', '"', '');
                foreach ($q->limit(5000)->get() as $d) {
                    $safe = fn ($x) => preg_match('/^[\s]*[=+@-]/u', (string) $x) ? "'".$x : $x;
                    fputcsv($out, array_map($safe, [$d->number ?? $d->label, $d->document_date->toDateString(), $d->customer['name'], $d->project?->name ?? '', $d->display_status, Money::decimal($d->taxable_minor), Money::decimal($d->cgst_minor + $d->sgst_minor + $d->igst_minor), Money::decimal($d->total_minor), Money::decimal($d->paid_minor), Money::decimal($d->balance_minor), $d->due_date?->toDateString()]), ',', '"', '');
                }fclose($out);
            });
        }, 'studio-register.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    public function api()
    {
        return response()->json(['data' => FinancialDocument::where('kind','invoice')->latest('id')->paginate(30)->through(fn ($d) => ['id' => $d->id, 'number' => $d->number, 'client_id' => $d->client_id, 'project_id' => $d->project_id, 'date' => $d->document_date->toDateString(), 'due_date' => $d->due_date?->toDateString(), 'status' => $d->display_status, 'currency' => 'INR', 'total_minor' => $d->total_minor, 'paid_minor' => $d->paid_minor, 'balance_minor' => $d->balance_minor])]);
    }
}
