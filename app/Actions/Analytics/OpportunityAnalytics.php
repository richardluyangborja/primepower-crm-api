<?php

namespace App\Actions\Analytics;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deterministic pipeline analysis. Computes quantitative metrics and
 * rule-based suggested actions from deal stages and dates — no AI call.
 * The UI labels these actions as "Auto-suggested".
 */
class OpportunityAnalytics
{
    /**
     * Revenue likelihood per stage, used to compute weighted pipeline value.
     */
    private const STAGE_PROBABILITY = [
        'initial_contact' => 0.10,
        'discussion' => 0.25,
        'proposal' => 0.50,
        'negotiation' => 0.75,
        'contract_processing' => 0.90,
        'won' => 1.00,
        'lost' => 0.00,
    ];

    /** Days in the same stage before an open deal is considered stalled. */
    private const STALLED_DAYS = 14;

    /** Days within which an expected close date means "closing soon". */
    private const CLOSING_SOON_DAYS = 30;

    /** How many items each list carries into the analytics. */
    private const LIST_LIMIT = 8;

    /** Cap on how many suggested actions we return. */
    private const ACTION_CAP = 8;

    private array $settings = [];

    /**
     * Analyze the pipeline as the user can see it.
     *
     * @param  array<string, string|int|float>|null  $settings  Threshold overrides from action_suggestion_settings
     * @param  CarbonInterface|null  $from  Optional created-at window lower bound.
     * @param  CarbonInterface|null  $to  Optional created-at window upper bound.
     * @return array{generated_at: string, empty: bool, metrics: array, suggested_actions: array}
     */
    public function analyze(User $user, ?array $settings = null, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $this->settings = $settings ?? [];
        $metrics = $this->digest($this->opportunitiesInRange($this->loadOpportunities($user), $from, $to));

        return [
            'generated_at' => now()->toIso8601String(),
            'empty' => $metrics['total_open'] === 0,
            'metrics' => $metrics,
            'suggested_actions' => $metrics['total_open'] === 0
                ? []
                : $this->suggestActions($metrics),
        ];
    }

    private function loadOpportunities(User $user): Collection
    {
        return Opportunity::with(['company', 'assignedTo', 'stageHistories'])
            ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('assigned_to_id', $user->visibleUserIds()))
            ->latest()
            ->get();
    }

    /**
     * Restrict opportunities to a created-at window, when one is given.
     */
    private function opportunitiesInRange(Collection $opportunities, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        if ($from === null && $to === null) {
            return $opportunities;
        }

        return $opportunities->filter(fn (Opportunity $opportunity) => $opportunity->created_at
            && ($from === null || $opportunity->created_at->gte($from->startOfDay()))
            && ($to === null || $opportunity->created_at->lte($to->endOfDay())));
    }

    /**
     * Reduce the pipeline to plain quant metrics.
     */
    private function digest(Collection $opportunities): array
    {
        $open = $opportunities->reject(
            fn (Opportunity $o) => $o->stage === OpportunityStage::WON || $o->stage === OpportunityStage::LOST
        );
        $won = $opportunities->where('stage', OpportunityStage::WON);
        $lost = $opportunities->where('stage', OpportunityStage::LOST);

        $perStage = collect(OpportunityStage::cases())
            ->map(fn (OpportunityStage $stage) => [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $opportunities->where('stage', $stage)->count(),
                'value' => round((float) $opportunities->where('stage', $stage)->sum('estimated_contract_value'), 2),
            ])
            ->reject(fn (array $row) => $row['count'] === 0 && $row['value'] == 0)
            ->values()
            ->all();

        return [
            'total_open' => $open->count(),
            'total_value' => round((float) $open->sum('estimated_contract_value'), 2),
            'weighted_value' => round(
                (float) $open->sum(fn (Opportunity $o) => (float) $o->estimated_contract_value * self::probability($o)),
                2
            ),
            'won_count' => $won->count(),
            'won_value' => round((float) $won->sum('estimated_contract_value'), 2),
            'lost_count' => $lost->count(),
            'lost_value' => round((float) $lost->sum('estimated_contract_value'), 2),
            'per_stage' => $perStage,
            'stalled' => $this->stalledList($open),
            'closing_soon' => $this->closingSoonList($open),
            'top_deals' => $open
                ->sortByDesc('estimated_contract_value')
                ->take(self::LIST_LIMIT)
                ->map(fn (Opportunity $o) => $this->item($o, closeDate: $o->expected_close_date?->toDateString()))
                ->values()
                ->all(),
            'by_owner' => $open
                ->groupBy(fn (Opportunity $o) => $o->assignedTo?->name ?? 'Unassigned')
                ->map(fn (Collection $group, string $owner) => [
                    'owner' => $owner,
                    'count' => $group->count(),
                    'value' => round((float) $group->sum('estimated_contract_value'), 2),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
        ];
    }

    private function stalledList(Collection $open): array
    {
        $stalledDays = $this->setting('opportunity_stalled_days', self::STALLED_DAYS);

        return $open
            ->filter(fn (Opportunity $o) => $this->daysInStage($o) >= $stalledDays)
            ->sortByDesc(fn (Opportunity $o) => $this->daysInStage($o))
            ->take(self::LIST_LIMIT)
            ->map(fn (Opportunity $o) => $this->item($o, days: $this->daysInStage($o)))
            ->values()
            ->all();
    }

    private function closingSoonList(Collection $open): array
    {
        $closingSoonDays = $this->setting('opportunity_closing_soon_days', self::CLOSING_SOON_DAYS);

        return $open
            ->filter(fn (Opportunity $o) => $o->expected_close_date
                && $o->expected_close_date->between(now()->startOfDay(), now()->addDays($closingSoonDays)->endOfDay()))
            ->sortBy('expected_close_date')
            ->take(self::LIST_LIMIT)
            ->map(fn (Opportunity $o) => $this->item($o, closeDate: $o->expected_close_date->toDateString()))
            ->values()
            ->all();
    }

    private function item(Opportunity $o, ?int $days = null, ?string $closeDate = null): array
    {
        return [
            'id' => $o->id,
            'title' => $o->title,
            'company' => $o->company?->name ?? '—',
            'stage' => $o->stage->label(),
            'days' => $days,
            'close_date' => $closeDate,
            'value' => round((float) $o->estimated_contract_value, 2),
            'owner' => $o->assignedTo?->name ?? 'Unassigned',
        ];
    }

    private function daysInStage(Opportunity $o): int
    {
        $enteredAt = $o->stageHistories->first()?->created_at ?? $o->updated_at;

        return $enteredAt ? max(0, (int) $enteredAt->diffInDays(now())) : 0;
    }

    private function probability(Opportunity $o): float
    {
        return self::STAGE_PROBABILITY[$o->stage->value] ?? 0.0;
    }

    /**
     * Rule-based "Auto-suggested" actions, deterministic and data-driven.
     */
    private function suggestActions(array $metrics): array
    {
        $actions = [];
        $maxStalled = (int) $this->setting('opportunity_max_stalled_actions', 3);
        $maxClosing = (int) $this->setting('opportunity_max_closing_actions', 3);
        $maxMissingDate = (int) $this->setting('opportunity_max_missing_date_actions', 2);
        $actionCap = (int) $this->setting('action_cap', self::ACTION_CAP);

        foreach (array_slice($metrics['stalled'], 0, $maxStalled) as $deal) {
            $actions[] = $this->action(
                type: 'stalled',
                title: $deal['title'],
                suggest: "Follow up on {$deal['title']}",
                reason: 'In '.$deal['stage'].' for '.$deal['days'].' days without movement.',
                id: $deal['id'],
                value: $deal['value']
            );
        }

        foreach (array_slice($metrics['closing_soon'], 0, $maxClosing) as $deal) {
            $actions[] = $this->action(
                type: 'closing',
                title: $deal['title'],
                suggest: "Reach out to close {$deal['title']}",
                reason: 'Expected close by '.$deal['close_date'].' ('.$deal['company'].').',
                id: $deal['id'],
                value: $deal['value']
            );
        }

        foreach (array_slice($metrics['top_deals'], 0, $maxMissingDate) as $deal) {
            if ($deal['close_date'] === null) {
                $actions[] = $this->action(
                    type: 'date',
                    title: $deal['title'],
                    suggest: "Set an expected close date on {$deal['title']}",
                    reason: 'No close date on a '.$this->money($deal['value']).' deal ('.$deal['company'].').',
                    id: $deal['id'],
                    value: $deal['value']
                );
            }
        }

        if ($actions === []) {
            $actions[] = $this->action(
                'review',
                null,
                'Review pipeline coverage',
                'No stalled or closing-soon deals. Keep adding detail and pushing stages forward.'
            );
        }

        return array_slice($actions, 0, $actionCap);
    }

    private function action(string $type, ?string $title, string $suggest, string $reason, ?string $id = null, float $value = 0.0): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'suggest' => $suggest,
            'reason' => $reason,
            'id' => $id,
            'value' => round($value, 2),
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 0);
    }

    private function setting(string $key, mixed $default): mixed
    {
        $value = $this->settings[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return is_int($default) ? (int) $value : (float) $value;
    }
}
