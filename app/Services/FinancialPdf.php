<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\FinancialDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

final class FinancialPdf
{
    public function render(FinancialDocument $document): string
    {
        $s = $document->snapshot ?? ['seller' => app(FinancialWorkflow::class)->seller(), 'customer' => $document->customer, 'items' => $document->items, 'totals' => $document->only(['subtotal_minor', 'discount_minor', 'taxable_minor', 'cgst_minor', 'sgst_minor', 'igst_minor', 'total_minor']), 'terms' => $document->terms, 'notes' => $document->notes, 'reference' => $document->reference, 'place_of_supply' => $document->place_of_supply, 'tax_basis' => app(FinancialWorkflow::class)->seller()['tax_basis'] ?? 'none', 'logo_path' => BillingSetting::first()?->logo_path, 'demo' => (bool) config('spacekind.demo')];

        return $this->bytes(view('finance.pdf', ['document' => $document, 's' => $s, 'logo' => $this->logo($s['logo_path'] ?? null)])->render());
    }

    public function logo(?string $path): ?string
    {
        return $path && preg_match('#^billing-logos/[0-9]+/[A-Za-z0-9.-]+\.jpg$#D', $path) && Storage::disk('local')->exists($path) ? 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($path)) : null;
    }

    public function bytes(string $html): string
    {
        $dir = storage_path('app/private/pdf-runtime');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $o = new Options;
        $o->set('isRemoteEnabled', false);
        $o->set('isPhpEnabled', false);
        $o->set('isJavascriptEnabled', false);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('chroot', [$dir]);
        $o->set('tempDir', $dir);
        $o->set('fontCache', $dir);
        $pdf = new Dompdf($o);
        $pdf->setPaper('A4');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }
}
