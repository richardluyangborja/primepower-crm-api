<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'sometimes',
                'integer',
                'exists:companies,id',
            ],

            'lead_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:leads,id',
            ],

            'assigned_to_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],

            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'stage' => [
                'sometimes',
                Rule::enum(OpportunityStage::class),
                function ($attribute, $value, $fail) {
                    if ($value === OpportunityStage::WON->value) {
                        $fail('Cannot set stage to WON through this endpoint. Use the win workflow instead.');
                    }
                },
            ],

            'estimated_contract_value' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'expected_close_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'lost_reason' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'manpower_requirement' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }
}
