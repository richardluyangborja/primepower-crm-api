<?php

namespace App\Actions\Analytics;

use App\Enums\ClientSurveyStatus;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deterministic satisfaction analytics. Computes quantitative metrics and
 * rule-based suggested actions from client surveys and at-risk flags — no
 * AI call. The UI labels these actions as "Auto-suggested".
 */
class SatisfactionAnalytics
{
    /** How many items each list carries into the analytics. */
    private const LIST_LIMIT = 8;

    /** Cap on how many suggested actions we return. */
    private const ACTION_CAP = 8;

    /** Scores at or above this count as "High" satisfaction. */
    private const HIGH_SCORE = 4;

    /** Scores at or above this (and below HIGH) count as "Medium". */
    private const MEDIUM_SCORE = 3;

    private array $settings = [];

    /**
     * Analyze satisfaction as the user can see it.
     *
     * @param  array<string, string|int|float>|null  $settings  Threshold overrides from action_suggestion_settings
     * @param  CarbonInterface|null  $from  Optional survey created-at window lower bound.
     * @param  CarbonInterface|null  $to  Optional survey created-at window upper bound.
     * @return array{generated_at: string, empty: bool, metrics: array, suggested_actions: array}
     */
    public function analyze(User $user, ?array $settings = null, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $this->settings = $settings ?? [];
        $clients = $this->loadClients($user);

        $surveys = $this->surveysInRange($clients->flatMap->surveys, $from, $to);
        $completed = $surveys->where('status', ClientSurveyStatus::COMPLETED);
        $pending = $surveys->where('status', ClientSurveyStatus::PENDING);

        $scored = $completed->filter(fn (ClientSurvey $s) => $s->average_score !== null);
        $atRisk = $clients->where('at_risk', true);

        $metrics = [
            'clients' => $clients->count(),
            'surveys_sent' => $surveys->count(),
            'surveys_completed' => $completed->count(),
            'pending_count' => $pending->count(),
            'response_rate' => $surveys->isEmpty()
                ? 0.0
                : round($completed->count() / $surveys->count() * 100, 1),
            'average_score' => $scored->isEmpty()
                ? null
                : round((float) $scored->avg('average_score'), 1),
            'at_risk_count' => $atRisk->count(),
            'score_bands' => $this->scoreBands($completed),
            'by_owner' => $this->byOwner($clients),
            'at_risk' => $this->atRiskList($atRisk)->values()->all(),
            'low_score' => $this->lowScoreList($clients)->values()->all(),
            'pending' => $this->pendingList($clients)->values()->all(),
        ];

        $empty = $metrics['surveys_sent'] === 0 && $metrics['at_risk_count'] === 0;

        return [
            'generated_at' => now()->toIso8601String(),
            'empty' => $empty,
            'metrics' => $metrics,
            'suggested_actions' => $empty ? [] : $this->suggestActions($metrics),
        ];
    }

    private function loadClients(User $user): Collection
    {
        return Client::with(['company', 'assignedTo', 'surveys'])
            ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('assigned_to_id', $user->visibleUserIds()))
            ->latest()
            ->get();
    }

    /**
     * Restrict surveys to a created-at window, when one is given.
     */
    private function surveysInRange(Collection $surveys, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        if ($from === null && $to === null) {
            return $surveys;
        }

        return $surveys->filter(fn (ClientSurvey $survey) => $survey->created_at
            && ($from === null || $survey->created_at->gte($from->startOfDay()))
            && ($to === null || $survey->created_at->lte($to->endOfDay())));
    }

    /**
     * Completed surveys bucketed by score band — always three rows so the
     * chart axis stays stable.
     */
    private function scoreBands(Collection $completed): array
    {
        $highScore = (float) $this->setting('satisfaction_high_score_threshold', self::HIGH_SCORE);
        $mediumScore = (float) $this->setting('satisfaction_low_score_threshold', self::MEDIUM_SCORE);

        $counts = $completed->countBy(function (ClientSurvey $s) use ($highScore, $mediumScore) {
            $score = (float) $s->average_score;

            return $score >= $highScore ? 'High' : ($score >= $mediumScore ? 'Medium' : 'Low');
        });

        return [
            ['band' => 'High', 'count' => $counts['High'] ?? 0],
            ['band' => 'Medium', 'count' => $counts['Medium'] ?? 0],
            ['band' => 'Low', 'count' => $counts['Low'] ?? 0],
        ];
    }

    private function byOwner(Collection $clients): array
    {
        return $clients->groupBy(fn (Client $c) => $c->assignedTo?->name ?? 'Unassigned')
            ->map(fn (Collection $group, string $owner) => [
                'owner' => $owner,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    private function atRiskList(Collection $atRisk): Collection
    {
        return $atRisk->map(fn (Client $c) => [
            'id' => $c->id,
            'company' => $c->company?->name ?? '—',
            'owner' => $c->assignedTo?->name ?? 'Unassigned',
            'reason' => $c->at_risk_reason ?? 'No reason provided',
            'score' => $this->latestScore($c),
        ])->sortBy('score')
            ->take(self::LIST_LIMIT);
    }

    /**
     * Companies whose most recent completed survey scores below Medium.
     */
    private function lowScoreList(Collection $clients): Collection
    {
        $lowThreshold = (float) $this->setting('satisfaction_low_score_threshold', self::MEDIUM_SCORE);

        return $clients
            ->filter(fn (Client $c) => ($score = $this->latestScore($c)) !== null && $score < $lowThreshold)
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'company' => $c->company?->name ?? '—',
                'owner' => $c->assignedTo?->name ?? 'Unassigned',
                'score' => $this->latestScore($c),
            ])
            ->sortBy('score')
            ->take(self::LIST_LIMIT);
    }

    private function pendingList(Collection $clients): Collection
    {
        return $clients
            ->filter(fn (Client $c) => $c->surveys->where('status', ClientSurveyStatus::PENDING)->isNotEmpty())
            ->map(function (Client $c) {
                $pending = $c->surveys->where('status', ClientSurveyStatus::PENDING);

                return [
                    'id' => $c->id,
                    'company' => $c->company?->name ?? '—',
                    'owner' => $c->assignedTo?->name ?? 'Unassigned',
                    'count' => $pending->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(self::LIST_LIMIT);
    }

    /**
     * Average score of the client's most recent completed survey, or null.
     */
    private function latestScore(Client $client): ?float
    {
        $latest = $client->surveys
            ->where('status', ClientSurveyStatus::COMPLETED)
            ->sortByDesc('completed_at')
            ->first();

        return $latest?->average_score !== null ? (float) $latest->average_score : null;
    }

    /**
     * Rule-based "Auto-suggested" actions, deterministic and data-driven.
     */
    private function suggestActions(array $metrics): array
    {
        $actions = [];
        $maxLowScore = (int) $this->setting('satisfaction_max_low_score_actions', 2);
        $maxAtRisk = (int) $this->setting('satisfaction_max_at_risk_actions', 2);
        $maxPending = (int) $this->setting('satisfaction_max_pending_actions', 2);
        $actionCap = (int) $this->setting('action_cap', self::ACTION_CAP);

        foreach (array_slice($metrics['low_score'], 0, $maxLowScore) as $client) {
            $actions[] = $this->action(
                'low_score',
                null,
                "Follow up on {$client['company']}'s low score",
                'Latest average score '.$client['score'].'/5 ('.$client['owner'].').',
                $client['id'],
                $client['company']
            );
        }

        foreach (array_slice($metrics['at_risk'], 0, $maxAtRisk) as $client) {
            $actions[] = $this->action(
                'at_risk',
                null,
                "Check in with {$client['company']}",
                'Marked at risk — '.$client['reason'].'.',
                $client['id'],
                $client['company']
            );
        }

        foreach (array_slice($metrics['pending'], 0, $maxPending) as $client) {
            $n = $client['count'];
            $actions[] = $this->action(
                'pending',
                null,
                "Chase pending survey for {$client['company']}",
                $n.' survey'.($n === 1 ? '' : 's').' waiting ('.$client['owner'].').',
                $client['id'],
                $client['company']
            );
        }

        if ($actions === []) {
            $actions[] = $this->action(
                'review',
                null,
                'Review satisfaction coverage',
                'No low scores, at-risk clients, or pending surveys to chase. Keep the pulse.'
            );
        }

        return array_slice($actions, 0, $actionCap);
    }

    private function action(string $type, ?string $title, string $suggest, string $reason, ?string $id = null, string $company = ''): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'suggest' => $suggest,
            'reason' => $reason,
            'id' => $id,
            'company' => $company,
        ];
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
