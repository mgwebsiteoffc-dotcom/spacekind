<?php

namespace App\Http\Requests;

use App\Support\LeadRules;
use Illuminate\Foundation\Http\FormRequest;

class EnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = LeadRules::contact(true) + ['origin' => ['nullable', 'in:marketplace,profile'], 'contact_consent' => ['accepted'], 'attribution_consent' => ['nullable', 'boolean'], 'marketing_consent' => ['nullable', 'boolean'], 'submission_key' => ['required', 'uuid'], 'website' => ['nullable', 'max:0'], 'entry_context' => ['nullable', 'string', 'max:50', 'regex:/^(article|reel):[1-9][0-9]{0,17}$/']];
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $key) {
            $rules['utm_'.$key] = ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9 _\.\-]+$/', 'not_regex:/\d{8,}/'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['contact_consent.accepted' => 'Please agree to contact about this project. Marketing consent is optional.', 'floor_area.required' => 'Please add your approximate floor area in square feet.', 'phone.regex' => 'Enter a phone number using 8–19 digits, with an optional country code.', 'website.max' => 'Unable to submit this enquiry.', 'utm_*.regex' => 'Campaign labels must not contain personal data or URLs.'];
    }
}
