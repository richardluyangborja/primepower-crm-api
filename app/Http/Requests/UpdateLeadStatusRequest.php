<?php

namespace App\Http\Requests;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => [
                'required',
                Rule::enum(LeadStatus::class),
                function ($attribute, $value, $fail) {
                    $lead = $this->route('lead');

                    if (! $lead) {
                        return;
                    }

                    $currentStatus = $lead->status;
                    $targetStatus = LeadStatus::from($value);

                    if ($targetStatus === LeadStatus::CONVERTED) {
                        $fail('Cannot set status to CONVERTED through this endpoint. Use the opportunity win workflow instead.');
                    }

                    if (! in_array($targetStatus, $currentStatus->validTransitions(), true)) {
                        $fail('Invalid status transition.');
                    }
                },
            ],

            'reason' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'to_status.required' => 'A target status is required.',
        ];
    }
}
