<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->business_id;
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::in(Lead::STAGES)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('business_id', app(Tenant::class)->id())->where('is_active', true)],
            'next_follow_up_at' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'follow_up_task' => ['nullable', 'required_with:next_follow_up_at', 'string', 'max:180'],
            'lost_reason' => ['nullable', 'required_if:stage,Lost', 'string', 'max:240'],
        ];
    }
}
