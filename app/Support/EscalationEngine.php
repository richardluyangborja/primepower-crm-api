<?php

namespace App\Support;

use App\Enums\ClientStatus;
use App\Enums\EscalationAction;
use App\Enums\EscalationCondition;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\ReminderPriority;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\EscalationRule;
use App\Models\EscalationTrigger;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\EscalationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EscalationEngine
{
    /**
     * Evaluate every active rule and return the number of actions fired.
     */
    public function evaluate(): int
    {
        $total = 0;

        foreach (EscalationRule::query()->active()->get() as $rule) {
            $total += $this->evaluateRule($rule);
        }

        return $total;
    }

    public function evaluateRule(EscalationRule $rule): int
    {
        $fired = 0;

        foreach ($this->matchingEntities($rule) as $entity) {
            $trigger = EscalationTrigger::firstOrCreate(
                [
                    'escalation_rule_id' => $rule->id,
                    'entity_type' => $rule->entity_type,
                    'entity_id' => $entity->getKey(),
                ],
                ['triggered_at' => now()]
            );

            // Already fired for this entity (idempotent under the unique index).
            if (! $trigger->wasRecentlyCreated) {
                continue;
            }

            $this->execute($rule, $entity);
            $fired++;
        }

        return $fired;
    }

    /**
     * Entities that currently match the rule's condition.
     *
     * @return Collection<int, Model>
     */
    public function matchingEntities(EscalationRule $rule): Collection
    {
        $threshold = max(1, (int) $rule->threshold_days);
        $condition = $rule->condition instanceof EscalationCondition
            ? $rule->condition
            : EscalationCondition::tryFrom((string) $rule->condition);

        return match ($condition) {
            EscalationCondition::INACTIVE_LEAD => $this->inactiveLeads($threshold),
            EscalationCondition::INACTIVE_CLIENT => $this->inactiveClients($threshold),
            EscalationCondition::STALE_OPPORTUNITY => $this->staleOpportunities($threshold),
            EscalationCondition::OVERDUE_REMINDER => $this->overdueReminders($threshold),
            default => collect(),
        };
    }

    protected function inactiveLeads(int $threshold): Collection
    {
        $cutoff = now()->subDays($threshold);

        return Lead::query()
            ->whereIn('status', [LeadStatus::NEW, LeadStatus::QUALIFIED])
            ->whereDoesntHave('client')
            ->whereDoesntHave(
                'company.communications',
                fn ($q) => $q->where('created_at', '>=', $cutoff)
            )
            ->get();
    }

    protected function inactiveClients(int $threshold): Collection
    {
        $cutoff = now()->subDays($threshold);

        return Client::query()
            ->where('status', ClientStatus::ACTIVE)
            ->whereDoesntHave(
                'company.communications',
                fn ($q) => $q->where('created_at', '>=', $cutoff)
            )
            ->get();
    }

    protected function staleOpportunities(int $threshold): Collection
    {
        $cutoff = now()->subDays($threshold);

        return Opportunity::query()
            ->whereNotIn('stage', [OpportunityStage::WON, OpportunityStage::LOST])
            ->get()
            ->filter(function (Opportunity $opportunity) use ($cutoff) {
                $lastChange = $opportunity->stageHistories()
                    ->latest('created_at')
                    ->value('created_at');

                $lastActivity = $lastChange ?? $opportunity->created_at;

                return $lastActivity < $cutoff;
            })
            ->values();
    }

    protected function overdueReminders(int $threshold): Collection
    {
        $cutoff = Carbon::today()->subDays($threshold);

        return Reminder::query()
            ->where('is_completed', false)
            ->whereIn('status', ['pending', 'incomplete'])
            ->whereDate('due_date', '<=', $cutoff)
            ->get();
    }

    /**
     * Execute the rule's action against a single matching entity.
     */
    public function execute(EscalationRule $rule, Model $entity): void
    {
        $action = $rule->action_type instanceof EscalationAction
            ? $rule->action_type
            : EscalationAction::tryFrom((string) $rule->action_type);

        if (! $action) {
            return;
        }

        $createdReminder = null;

        if ($action->createsReminder() && $entity instanceof Lead) {
            $createdReminder = $this->createFollowUpReminder($rule, $entity);
        } elseif ($action->createsReminder() && $entity instanceof Client) {
            $createdReminder = $this->createFollowUpReminder($rule, $entity);
        } elseif ($action->createsReminder() && $entity instanceof Opportunity) {
            $createdReminder = $this->createFollowUpReminder($rule, $entity);
        }

        if ($action->notifiesManager()) {
            $this->notifyManager($rule, $entity, $createdReminder);
        }

        $this->audit($rule, $entity, $createdReminder);
    }

    protected function createFollowUpReminder(EscalationRule $rule, Lead|Client|Opportunity $entity): Reminder
    {
        $owner = $entity->assignedTo;

        return Reminder::create([
            'company_id' => $entity->company_id,
            'related_to_type' => $rule->entity_type,
            'related_to_id' => $entity->getKey(),
            'title' => $rule->reminder_title ?: "Follow up on {$rule->condition->label()}",
            'due_date' => Carbon::today()->addDays((int) $rule->reminder_due_in_days)->toDateString(),
            'priority' => $rule->reminder_priority ?? ReminderPriority::MEDIUM,
            'status' => 'pending',
            'is_completed' => false,
            'assigned_to_name' => $owner?->name,
            'user_id' => $owner?->id,
        ]);
    }

    protected function notifyManager(EscalationRule $rule, Model $entity, ?Reminder $createdReminder): void
    {
        $owner = $entity instanceof Reminder ? $entity->user : $entity->assignedTo;

        if (! $owner instanceof User) {
            return;
        }

        $recipient = $owner->manager ?: $owner;

        $relatedToType = $entity instanceof Reminder ? 'reminder' : $rule->entity_type;
        $relatedToId = $entity->getKey();
        $reminderId = $createdReminder?->id ?? ($entity instanceof Reminder ? $entity->id : null);

        $label = $rule->condition instanceof EscalationCondition
            ? $rule->condition->label()
            : (string) $rule->condition;

        $recipient->notify(new EscalationNotification(
            title: "Escalation: {$label}",
            message: "An '{$label}' rule matched for {$this->entityName($entity)} (rule '{$rule->name}').",
            reminderId: $reminderId,
            relatedToType: $relatedToType,
            relatedToId: $relatedToId,
        ));
    }

    protected function entityName(Model $entity): string
    {
        if (method_exists($entity, 'company') && $entity->company) {
            return $entity->company->name;
        }

        if ($entity instanceof Reminder) {
            return $entity->title;
        }

        return '#'.$entity->getKey();
    }

    protected function audit(EscalationRule $rule, Model $entity, ?Reminder $createdReminder): void
    {
        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Escalation',
            'action' => 'Triggered',
            'subject_type' => 'EscalationRule',
            'subject_id' => (string) $rule->id,
            'subject_name' => $rule->name,
            'description' => "Escalation rule '{$rule->name}' fired for {$this->entityName($entity)}.",
            'metadata' => [
                'entity_type' => $rule->entity_type,
                'entity_id' => $entity->getKey(),
                'condition' => $rule->condition instanceof EscalationCondition
                    ? $rule->condition->value
                    : (string) $rule->condition,
                'reminder_created_id' => $createdReminder?->id,
            ],
        ]);
    }
}
