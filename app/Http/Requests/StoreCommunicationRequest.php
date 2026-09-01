<?php

namespace App\Http\Requests;

use App\Enums\CommunicationDirection;
use App\Enums\CommunicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'integer',
                'exists:companies,id',
            ],

            'contact_id' => [
                'nullable',
                'integer',
                'exists:contacts,id',
            ],

            'type' => [
                'required',
                Rule::enum(CommunicationType::class),
            ],

            'direction' => [
                'required',
                Rule::enum(CommunicationDirection::class),
            ],

            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'duration_minutes' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'scheduled_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
