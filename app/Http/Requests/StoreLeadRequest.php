<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company.name' => [
                'required',
                'string',
                'max:255',
            ],

            'company.industry' => [
                'required',
                'string',
                'max:100',
            ],

            'company.address' => [
                'required',
                'string',
                'max:500',
            ],

            'company.phone' => [
                'required',
                'string',
                'max:50',
            ],

            'company.email' => [
                'required',
                'email',
                'max:255',
            ],

            'company.website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'primary_contact.first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'primary_contact.last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'primary_contact.title' => [
                'required',
                'string',
                'max:100',
            ],

            'primary_contact.email' => [
                'required',
                'email',
                'max:255',
            ],

            'primary_contact.phone' => [
                'required',
                'string',
                'max:50',
            ],

            'source' => [
                'required',
                'string',
                'max:100',
            ],

            'assigned_to_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::SALES_REP->value),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
