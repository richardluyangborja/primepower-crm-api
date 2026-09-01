<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WinOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            'reason.max' => 'The reason cannot exceed 5000 characters.',
        ];
    }
}
