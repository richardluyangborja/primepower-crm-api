<?php

namespace App\Http\Requests;

use App\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(ClientStatus::class),
                function ($attribute, $value, $fail) {
                    $client = $this->route('client');

                    if (! $client) {
                        return;
                    }

                    $currentStatus = $client->status;
                    $targetStatus = ClientStatus::from($value);

                    if ($currentStatus === $targetStatus) {
                        $fail('Client is already '.$targetStatus->value.'.');
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
            'status.required' => 'A target status is required.',
        ];
    }
}
