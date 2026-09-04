<?php

namespace App\Http\Requests;

use App\Enums\CommunicationDirection;
use App\Enums\CommunicationOutcome;
use App\Enums\CommunicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'sometimes',
                Rule::enum(CommunicationType::class),
            ],

            'direction' => [
                'sometimes',
                Rule::enum(CommunicationDirection::class),
            ],

            'outcome' => [
                'sometimes',
                'nullable',
                Rule::enum(CommunicationOutcome::class),
            ],

            'subject' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'duration_minutes' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            'scheduled_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }
}
