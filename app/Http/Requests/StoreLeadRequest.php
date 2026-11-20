<?php

namespace App\Http\Requests;

use App\Support\LeadRules;
use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->business_id;
    }

    public function rules(): array
    {
        return LeadRules::contact() + [
            'source' => ['required', Rule::in(config('spacekind.sources'))],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('business_id', app(Tenant::class)->id())->where('is_active', true)],
            'contact_consent' => ['nullable', 'boolean'],
        ];
    }
}
