<?php

namespace App\Actions\Analytics;

use App\Enums\ClientSurveyStatus;
use App\Enums\OpportunityStage;
use App\Enums\UserRole;
use App\Models\ClientSurvey;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deterministic per-sales-rep performance digest used to feed AI reports.
 *
 * Reps are returned anonymized as "Rep 1..Rep N" (ordered by pipeline value)
 * so a composed prompt never contains names — only figures.
 */
class RepPerformanceAnalytics
{
    /**
     * @return array{generated_at: string, rep_count: int, reps: array}
     */
    public function rows(User $user, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $reps = User::query()
            ->where('role', UserRole::SALES_REP)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $rows = $reps
            ->map(fn (User $rep) => $this->row($rep, $from, $to))
            ->sortByDesc('pipeline_value')
            ->values()
            ->map(fn (array $row, int $index) => ['rep' => 'Rep '.($index + 1)] + $row)
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'rep_count' => count($rows),
            'reps' => $rows,
        ];
    }

    private function row(User $rep, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $opportunityQuery = Opportunity::query()->where('assigned_to_id', $rep->id);
        $this->applyRange($opportunityQuery, 'created_at', $from, $to);

        $total = (clone $opportunityQuery)->count();
        $won = (clone $opportunityQuery)->where('stage', OpportunityStage::WON)->count();
        $lost = (clone $opportunityQuery)->where('stage', OpportunityStage::LOST)->count();
        $closed = $won + $lost;

        $surveyQuery = ClientSurvey::query()
            ->join('clients', 'client_surveys.client_id', '=', 'clients.id')
            ->where('clients.assigned_to_id', $rep->id);
        $this->applyRange($surveyQuery, 'client_surveys.created_at', $from, $to);

        $sent = (clone $surveyQuery)->count();
        $completed = (clone $surveyQuery)
            ->where('client_surveys.status', ClientSurveyStatus::COMPLETED->value)
            ->count();

        return [
            'open_opportunities' => max(0, $total - $won - $lost),
            'pipeline_value' => round((float) (clone $opportunityQuery)
                ->whereNotIn('stage', [OpportunityStage::WON, OpportunityStage::LOST])
                ->sum('estimated_contract_value'), 2),
            'won_count' => $won,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0,
            'average_score' => $this->averageScore($rep->id, $from, $to),
            'response_rate' => $sent > 0 ? round(($completed / $sent) * 100, 1) : 0.0,
        ];
    }

    private function averageScore(string $repId, ?CarbonInterface $from, ?CarbonInterface $to): ?float
    {
        $query = ClientSurvey::query()
            ->join('clients', 'client_surveys.client_id', '=', 'clients.id')
            ->where('clients.assigned_to_id', $repId)
            ->where('client_surveys.status', ClientSurveyStatus::COMPLETED->value)
            ->whereNotNull('client_surveys.average_score');
        $this->applyRange($query, 'client_surveys.created_at', $from, $to);

        $average = $query->avg('client_surveys.average_score');

        return $average !== null ? round((float) $average, 1) : null;
    }

    private function applyRange(Builder $query, string $column, ?CarbonInterface $from, ?CarbonInterface $to): void
    {
        if ($from) {
            $query->whereDate($column, '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate($column, '<=', $to->toDateString());
        }
    }
}
