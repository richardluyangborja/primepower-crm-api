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
