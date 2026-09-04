<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEscalationRuleRequest;
use App\Http\Requests\UpdateEscalationRuleRequest;
use App\Http\Resources\EscalationRuleResource;
use App\Models\AuditLog;
use App\Models\EscalationRule;
use Illuminate\Http\Request;

class EscalationRuleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', EscalationRule::class);

        $rules = EscalationRule::query()
            ->latest()
            ->get();

        return EscalationRuleResource::collection($rules);
    }

    public function store(StoreEscalationRuleRequest $request)
    {
        $this->authorize('create', EscalationRule::class);

        $rule = EscalationRule::create($this->validated($request));

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'EscalationRule',
            'action' => 'Created',
            'subject_type' => 'EscalationRule',
            'subject_id' => (string) $rule->id,
            'subject_name' => $rule->name,
            'description' => "Escalation rule '{$rule->name}' was created.",
            'metadata' => [
                'condition' => $rule->condition?->value ?? $rule->condition,
                'entity_type' => $rule->entity_type,
                'threshold_days' => $rule->threshold_days,
                'action_type' => $rule->action_type?->value ?? $rule->action_type,
            ],
        ]);

        return new EscalationRuleResource($rule);
    }

    public function update(UpdateEscalationRuleRequest $request, EscalationRule $escalationRule)
    {
        $this->authorize('update', $escalationRule);

        $escalationRule->update($this->validated($request));

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'EscalationRule',
            'action' => 'Updated',
            'subject_type' => 'EscalationRule',
            'subject_id' => (string) $escalationRule->id,
            'subject_name' => $escalationRule->name,
            'description' => "Escalation rule '{$escalationRule->name}' was updated.",
            'metadata' => [
                'condition' => $escalationRule->condition?->value ?? $escalationRule->condition,
                'entity_type' => $escalationRule->entity_type,
                'threshold_days' => $escalationRule->threshold_days,
                'action_type' => $escalationRule->action_type?->value ?? $escalationRule->action_type,
                'is_active' => $escalationRule->is_active,
            ],
        ]);

        return new EscalationRuleResource($escalationRule);
    }

    public function destroy(EscalationRule $escalationRule)
    {
        $this->authorize('delete', $escalationRule);

        $name = $escalationRule->name;
        $escalationRule->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'EscalationRule',
            'action' => 'Deleted',
            'subject_type' => 'EscalationRule',
            'subject_id' => (string) $escalationRule->id,
            'subject_name' => $name,
            'description' => "Escalation rule '{$name}' was deleted.",
        ]);

        return response()->noContent();
    }

    private function validated($request): array
    {
        return $request->validated();
    }
}
