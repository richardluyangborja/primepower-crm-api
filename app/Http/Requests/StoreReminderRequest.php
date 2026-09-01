<?php

namespace App\Http\Requests;

use App\Enums\ReminderPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'related_to_type' => ['required', Rule::in(['lead', 'client', 'opportunity'])],
            'related_to_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
            'priority' => ['required', Rule::enum(ReminderPriority::class)],
            'is_completed' => ['sometimes', 'boolean'],
            'completed_at' => ['nullable', 'date'],
            'assigned_to_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
