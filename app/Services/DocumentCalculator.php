<?php

namespace App\Services;

use App\Support\BillingDetails;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

final class DocumentCalculator
{
    public function calculate(array $items, array $seller, string $place): array
    {
        $totals = array_fill_keys(['subtotal_minor', 'discount_minor', 'taxable_minor', 'cgst_minor', 'sgst_minor', 'igst_minor', 'total_minor'], 0);
        $lines = [];
        foreach ($items as $i => $item) {
            $qty = Money::scaled($item['quantity'] ?? '', 3, 99999999);
            $rate = Money::scaled($item['rate'] ?? '', 2, 1000000000);
            $discount = Money::scaled($item['discount'] ?? '0');
            $bps = (int) ($item['tax_bps'] ?? 0);
            if (! $qty || ! in_array($bps, BillingDetails::RATES, true)) {
                throw ValidationException::withMessages(['items.'.$i => 'Choose a positive quantity and a supported GST rate.']);
            }
            if (($seller['tax_basis'] ?? 'none') === 'none' && $bps) {
                throw ValidationException::withMessages(['items.'.$i.'.tax_bps' => 'GST cannot be charged with a non-GST billing setup.']);
            }
            $gross = Money::divide($qty * $rate, 1000);
            if ($discount > $gross) {
                throw ValidationException::withMessages(['items.'.$i.'.discount' => 'Discount cannot exceed the line amount.']);
            }$net = $gross - $discount;
            $base = $net;
            $cgst = $sgst = $igst = 0;
            if ($bps) {
                if (($seller['tax_basis'] ?? 'none') === 'inclusive') {
                    $base = Money::divide($net * 10000, 10000 + $bps);
                    $tax = $net - $base;
                    if ((string) ($seller['state'] ?? '') === $place) {
                        $cgst = intdiv($tax, 2);
                        $sgst = $tax - $cgst;
                    } else {
                        $igst = $tax;
                    }
                } elseif ((string) ($seller['state'] ?? '') === $place) {
                    $cgst = $sgst = Money::divide($base * $bps, 20000);
                } else {
                    $igst = Money::divide($base * $bps, 10000);
                }
            }
            $line = ['description' => $item['description'], 'category' => $item['category'], 'unit' => $item['unit'], 'quantity' => (string) $item['quantity'], 'rate' => (string) $item['rate'], 'discount' => (string) ($item['discount'] ?? '0'), 'hsn_sac' => $item['hsn_sac'] ?? null, 'tax_bps' => $bps, 'subtotal_minor' => $gross, 'discount_minor' => $discount, 'taxable_minor' => $base, 'cgst_minor' => $cgst, 'sgst_minor' => $sgst, 'igst_minor' => $igst, 'total_minor' => $base + $cgst + $sgst + $igst];
            foreach ($totals as $key => $_) {
                $totals[$key] += $line[$key];
            }$lines[] = $line;
        }
        if ($totals['total_minor'] < 1 || $totals['total_minor'] > 999999999999) {
            throw ValidationException::withMessages(['items' => 'Document total must be positive and within the supported limit.']);
        }

return ['items' => $lines] + $totals;
    }
}
