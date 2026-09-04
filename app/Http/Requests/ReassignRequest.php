<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::SALES_REP->value),
            ],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
