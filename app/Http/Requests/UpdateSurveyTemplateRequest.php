<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSurveyTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy is enforced in the controller via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'questions' => ['sometimes', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string', 'max:50'],
            'questions.*.text' => ['required', 'string', 'max:500'],
            'questions.*.category' => ['required', 'string', 'max:100'],
        ];
    }
}
