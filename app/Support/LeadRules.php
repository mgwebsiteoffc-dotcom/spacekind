<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class LeadRules
{
    public static function contact(bool $public = false): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s\-]{7,18}$/'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'city' => ['required', 'string', 'max:60'], 'locality' => ['required', 'string', 'max:120'],
            'floor_area' => [$public ? 'required' : 'nullable', 'integer', 'between:50,1000000'],
            'property_type' => ['required', Rule::in(config('spacekind.property_types'))],
            'configuration' => ['required', Rule::in(config('spacekind.configurations'))],
            'scope' => ['required', Rule::in(array_values(config('spacekind.services')))],
            'budget' => ['required', Rule::in(config('spacekind.budgets'))],
            'readiness' => ['required', Rule::in(config('spacekind.readiness'))],
            'brief' => ['required', 'string', 'min:10', 'max:2000'],
            'plan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ];
    }
}
