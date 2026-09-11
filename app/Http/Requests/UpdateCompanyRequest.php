<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'industry' => ['sometimes', 'string', 'max:100'],
            'address' => ['sometimes', 'string', 'max:500'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'email', 'max:255'],
            'website' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
