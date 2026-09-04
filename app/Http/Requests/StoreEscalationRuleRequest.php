<?php

namespace App\Http\Requests;

use App\Enums\EscalationAction;
use App\Enums\EscalationCondition;
use App\Enums\ReminderPriority;
use Illuminate\Foundation\Http\FormRequest;

class StoreEscalationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy is enforced in the controller via $this->authorize()
    }

    public function rules(): array
    {
        $entityType = $this->input('entity_type');
        $actionType = $this->input('action_type');
        $action = EscalationAction::tryFrom((string) $actionType);
        $createsReminder = $action?->createsReminder() ?? false;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', 'in:lead,client,opportunity,reminder'],
            'condition' => ['required', 'string', 'in:'.implode(',', array_column(EscalationCondition::cases(), 'value'))],
            'threshold_days' => ['required', 'integer', 'min:1'],
            'action_type' => ['required', 'string', 'in:'.implode(',', array_column(EscalationAction::cases(), 'value'))],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($createsReminder) {
            $rules['reminder_title'] = ['required', 'string', 'max:255'];
            $rules['reminder_priority'] = ['required', 'string', 'in:'.implode(',', array_column(ReminderPriority::cases(), 'value'))];
            $rules['reminder_due_in_days'] = ['required', 'integer', 'min:1'];
        } else {
            $rules['reminder_title'] = ['nullable', 'string', 'max:255'];
            $rules['reminder_priority'] = ['nullable', 'string', 'in:'.implode(',', array_column(ReminderPriority::cases(), 'value'))];
            $rules['reminder_due_in_days'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $condition = EscalationCondition::tryFrom((string) $this->input('condition'));
            $entityType = $this->input('entity_type');

            if ($condition && $entityType && $condition->entityType() !== $entityType) {
                $validator->errors()->add(
                    'condition',
                    "The condition '{$condition->value}' does not apply to entity type '{$entityType}'."
                );
            }

            // You cannot create a reminder on top of an existing reminder.
            if ($entityType === 'reminder') {
                $action = EscalationAction::tryFrom((string) $this->input('action_type'));
                if ($action && $action->createsReminder()) {
                    $validator->errors()->add(
                        'action_type',
                        'Creating a reminder is not supported for the "reminder" entity; choose "notify_manager".'
                    );
                }
            }
        });
    }
}
