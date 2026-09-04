<?php

namespace App\Http\Resources;

use App\Enums\EscalationAction;
use App\Enums\EscalationCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EscalationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $condition = $this->condition instanceof EscalationCondition
            ? $this->condition
            : EscalationCondition::tryFrom($this->condition);

        $action = $this->action_type instanceof EscalationAction
            ? $this->action_type
            : EscalationAction::tryFrom($this->action_type);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'entity_type' => $this->entity_type,
            'condition' => $condition?->value,
            'condition_label' => $condition?->label(),
            'threshold_days' => $this->threshold_days,
            'action_type' => $action?->value,
            'action_label' => $action?->label(),
            'reminder_title' => $this->reminder_title,
            'reminder_priority' => $this->reminder_priority?->value,
            'reminder_due_in_days' => $this->reminder_due_in_days,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
