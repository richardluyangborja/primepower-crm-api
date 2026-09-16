<?php

namespace App\Http\Requests;

use App\Enums\ReminderPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'priority' => ['sometimes', Rule::enum(ReminderPriority::class)],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'incomplete'])],
            'assigned_to_name' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'string', 'uuid', 'exists:companies,id'],
            'related_to_type' => ['nullable', 'string', 'max:255'],
            'related_to_id' => ['nullable', 'string', 'uuid'],
            'recurrence_rule' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
        ];
    }
}
