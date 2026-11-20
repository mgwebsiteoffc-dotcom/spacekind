<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BillingDetails
{
    public const STATES = ['01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat', '26' => 'Dadra & Nagar Haveli and Daman & Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman & Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh'];

    public const RATES = [0, 500, 1200, 1800, 2800];

    public static function rules(string $prefix = ''): array
    {
        return [$prefix.'name' => 'required|string|max:120', $prefix.'address' => 'required|string|min:8|max:600', $prefix.'state' => ['required', 'string', 'size:2', Rule::in(array_map('strval', array_keys(self::STATES)))], $prefix.'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/D'], $prefix.'email' => 'nullable|email:rfc|max:150', $prefix.'phone' => 'nullable|string|max:25'];
    }

    public static function check(array $data, bool $seller = false): void
    {
        if (! empty($data['gstin']) && substr($data['gstin'], 0, 2) !== (string) $data['state']) {
            throw ValidationException::withMessages(['gstin' => 'GSTIN state prefix must match the selected billing state. Registration is not verified by this form.']);
        }
        if ($seller && ($data['tax_basis'] ?? 'none') !== 'none' && empty($data['gstin'])) {
            throw ValidationException::withMessages(['gstin' => 'GST billing requires an entered GSTIN and a reviewed tax basis.']);
        }
    }

    public static function ready(array $seller): void
    {
        foreach (['name', 'address', 'state', 'tax_basis'] as $key) {
            if (empty($seller[$key])) {
                throw ValidationException::withMessages(['billing' => 'Complete and review Billing setup before issuing documents.']);
            }
        }self::check($seller, true);
    }
}
