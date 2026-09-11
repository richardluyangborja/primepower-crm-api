<?php

namespace App\Http\Resources;

use App\Enums\ClientSurveyStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\Communication;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\StageHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $repId = $request->integer('rep_id') ?: null;
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        $scope = $this->buildScope($user, $repId);
        $reminderScope = $this->buildScope($user, $repId, 'user_id');
        $communicationScope = $this->buildScope($user, $repId, 'user_id', true);

        return [
            'scope' => [
                'rep_id' => $repId,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'role' => $user?->role instanceof \BackedEnum ? $user->role->value : $user?->role,
            ],
            'summary' => $this->summary($scope, $reminderScope, $from, $to),
            'leads' => $this->leads($scope, $from, $to),
            'clients' => $this->clients($scope, $from, $to),
            'opportunities' => $this->opportunities($scope, $from, $to),
            'communications' => $this->communications($communicationScope, $from, $to),
            'reminders' => $this->reminders($reminderScope),
            'satisfaction' => $this->satisfaction($scope, $repId),
            'performance' => $this->performance($user),
        ];
    }

    private function buildScope(?User $user, ?int $repId, string $column = 'assigned_to_id', bool $includeUnassigned = false): \Closure
    {
        return function (Builder $query) use ($user, $repId, $column, $includeUnassigned) {
            if (! $user) {
                return $query;
            }

            // Admin and manager both view the whole dataset; the optional
            // rep filter narrows either to a single representative.
            if ($user->isAdmin() || $user->isManager()) {
                return $repId ? $query->where($column, $repId) : $query;
            }

            return $query->where(function (Builder $q) use ($user, $column, $includeUnassigned) {
                $q->whereIn($column, $user->visibleUserIds());

                if ($includeUnassigned) {
                    $q->orWhereNull($column);
                }
            });
        };
    }

    private function summary(\Closure $scope, \Closure $reminderScope, $from, $to): array
    {
        $leadQuery = Lead::query();
        $scope($leadQuery);
        $this->applyDateRange($leadQuery, 'created_at', $from, $to);
        $totalLeads = $leadQuery->count();

        $clientQuery = Client::query();
        $scope($clientQuery);
        $this->applyDateRange($clientQuery, 'client_since', $from, $to);
        $totalClients = $clientQuery->count();

        $oppQuery = Opportunity::query();
        $scope($oppQuery);
        $this->applyDateRange($oppQuery, 'created_at', $from, $to);
        $totalOpportunities = $oppQuery->count();
        $wonOpportunities = (clone $oppQuery)->where('stage', OpportunityStage::WON)->count();
        $totalContractValue = (clone $oppQuery)->where('stage', OpportunityStage::WON)->sum('estimated_contract_value');

        $reminderQuery = Reminder::query();
        $reminderScope($reminderQuery);
        $activeReminders = $reminderQuery->where('is_completed', false)->count();

        $lost = (clone $oppQuery)->where('stage', OpportunityStage::LOST)->count();
        $closed = $wonOpportunities + $lost;
        $winRate = $closed > 0 ? round(($wonOpportunities / $closed) * 100, 1) : 0.0;

        return [
            'total_leads' => $totalLeads,
            'total_clients' => $totalClients,
            'total_opportunities' => $totalOpportunities,
            'won_opportunities' => $wonOpportunities,
            'lost_opportunities' => $lost,
            'win_rate' => $winRate,
            'total_contract_value' => (float) $totalContractValue,
            'active_reminders' => $activeReminders,
            'conversion_rate' => $totalLeads > 0
                ? round(($wonOpportunities / $totalLeads) * 100, 1)
                : 0.0,
        ];
    }

    private function leads(\Closure $scope, $from, $to): array
    {
        $query = Lead::query();
        $scope($query);
        $this->applyDateRange($query, 'created_at', $from, $to);

        $byStatus = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusData = [];
        foreach (LeadStatus::cases() as $status) {
            $statusData[] = [
                'status' => $status->value,
                'count' => $byStatus[$status->value] ?? 0,
            ];
        }

        $bySource = (clone $query)->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count')
            ->pluck('count', 'source');

        $sourceData = [];
        foreach ($bySource as $source => $count) {
            $sourceData[] = [
                'source' => $source,
                'count' => $count,
            ];
        }

        $monthly = (clone $query)->select(
            DB::raw($this->monthExpression('created_at').' as month'),
            DB::raw('count(*) as count')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        $monthlyData = [];
        foreach ($monthly as $month => $count) {
            $monthlyData[] = [
                'month' => $month,
                'count' => $count,
            ];
        }

        return [
            'by_status' => $statusData,
            'by_source' => $sourceData,
            'by_month' => $monthlyData,
        ];
    }

    private function clients(\Closure $scope, $from, $to): array
    {
        $query = Client::query();
        $scope($query);
        $this->applyDateRange($query, 'client_since', $from, $to);

        $active = (clone $query)->where('status', 'active')->count();
        $inactive = (clone $query)->where('status', 'inactive')->count();

        $byIndustry = (clone $query)->join('companies', 'clients.company_id', '=', 'companies.id')
            ->select('companies.industry', DB::raw('count(*) as count'))
            ->groupBy('companies.industry')
            ->orderByDesc('count')
            ->pluck('count', 'companies.industry');

        $industryData = [];
        foreach ($byIndustry as $industry => $count) {
            $industryData[] = [
                'industry' => $industry,
                'count' => $count,
            ];
        }

        $monthly = (clone $query)->select(
            DB::raw($this->monthExpression('client_since').' as month'),
            DB::raw('count(*) as count')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        $monthlyData = [];
        foreach ($monthly as $month => $count) {
            $monthlyData[] = [
                'month' => $month,
                'count' => $count,
            ];
        }

        return [
            'active' => $active,
            'inactive' => $inactive,
            'by_month' => $monthlyData,
            'by_industry' => $industryData,
        ];
    }

    private function opportunities(\Closure $scope, $from, $to): array
    {
        $query = Opportunity::query();
        $scope($query);
        $this->applyDateRange($query, 'created_at', $from, $to);

        $byStage = (clone $query)->select('stage', DB::raw('count(*) as count'))
            ->groupBy('stage')
            ->pluck('count', 'stage');

        $stageData = [];
        foreach (OpportunityStage::cases() as $stage) {
            $stageData[] = [
                'stage' => $stage->value,
                'count' => $byStage[$stage->value] ?? 0,
            ];
        }

        $valueByStage = (clone $query)->select('stage', DB::raw('sum(estimated_contract_value) as total'))
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $valueData = [];
        foreach (OpportunityStage::cases() as $stage) {
            $valueData[] = [
                'stage' => $stage->value,
                'value' => (float) ($valueByStage[$stage->value] ?? 0),
            ];
        }

        $avgDealSize = (clone $query)->where('stage', OpportunityStage::WON)->avg('estimated_contract_value');

        $monthlyWon = (clone $query)->where('stage', OpportunityStage::WON)
            ->select(
                DB::raw($this->monthExpression('updated_at').' as month'),
                DB::raw('count(*) as count'),
                DB::raw('sum(estimated_contract_value) as total_value')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyWonData = [];
        foreach ($monthlyWon as $row) {
            $monthlyWonData[] = [
                'month' => $row->month,
                'count' => (int) $row->count,
                'total_value' => (float) $row->total_value,
            ];
        }

        // All opportunities created per month (count + value) — the pipeline trend.
        $monthly = (clone $query)->select(
            DB::raw($this->monthExpression('created_at').' as month'),
            DB::raw('count(*) as count'),
            DB::raw('sum(estimated_contract_value) as total_value')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $trendData = [];
        foreach ($monthly as $row) {
            $trendData[] = [
                'month' => $row->month,
                'count' => (int) $row->count,
                'value' => (float) $row->total_value,
            ];
        }

        $winLoss = [];
        foreach ([OpportunityStage::WON, OpportunityStage::LOST] as $outcome) {
            $winLoss[] = [
                'stage' => $outcome->value,
                'count' => (int) ($byStage[$outcome->value] ?? 0),
                'value' => (float) ($valueByStage[$outcome->value] ?? 0),
            ];
        }

        $avgTimeInStage = $this->avgTimeInStage($scope);

        return [
            'by_stage' => $stageData,
            'value_by_stage' => $valueData,
            'monthly_won' => $monthlyWonData,
            'trend' => $trendData,
            'win_loss' => $winLoss,
            'avg_deal_size' => (float) ($avgDealSize ?? 0),
            'avg_time_in_stage_days' => $avgTimeInStage,
            'total_opportunities' => $query->count(),
        ];
    }

    private function avgTimeInStage(\Closure $scope): array
    {
        $opportunityIds = Opportunity::query()
            ->when(true, $scope)
            ->pluck('id');

        if ($opportunityIds->isEmpty()) {
            return [];
        }

        $histories = StageHistory::query()
            ->whereIn('opportunity_id', $opportunityIds)
            ->orderBy('opportunity_id')
            ->orderBy('created_at')
            ->get(['opportunity_id', 'to_stage', 'created_at']);

        $durations = [];
        $previous = null;
        foreach ($histories as $row) {
            if ($previous && $previous->opportunity_id === $row->opportunity_id) {
                $stage = $previous->to_stage;
                $seconds = $row->created_at->diffInSeconds($previous->created_at, false);
                $days = abs($seconds) / 86400.0;
                $durations[$stage] ??= [];
                $durations[$stage][] = $days;
            }
            $previous = $row;
        }

        $result = [];
        foreach ($durations as $stage => $samples) {
            $result[] = [
                'stage' => $stage,
                'avg_days' => round(array_sum($samples) / count($samples), 1),
            ];
        }

        return $result;
    }

    private function communications(\Closure $scope, $from, $to): array
    {
        $query = Communication::query();
        $scope($query);
        $this->applyDateRange($query, 'created_at', $from, $to);

        $total = (clone $query)->count();

        $byType = (clone $query)->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type');

        $typeData = [];
        foreach ($byType as $type => $count) {
            $typeData[] = [
                'type' => $type,
                'count' => $count,
            ];
        }

        $byDirection = (clone $query)->select('direction', DB::raw('count(*) as count'))
            ->groupBy('direction')
            ->pluck('count', 'direction');

        $directionData = [];
        foreach ($byDirection as $direction => $count) {
            $directionData[] = [
                'direction' => $direction,
                'count' => $count,
            ];
        }

        $monthly = (clone $query)->select(
            DB::raw($this->monthExpression('created_at').' as month'),
            DB::raw('count(*) as count')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        $monthlyData = [];
        foreach ($monthly as $month => $count) {
            $monthlyData[] = [
                'month' => $month,
                'count' => $count,
            ];
        }

        return [
            'total' => $total,
            'by_type' => $typeData,
            'by_direction' => $directionData,
            'by_month' => $monthlyData,
        ];
    }

    private function reminders(\Closure $scope): array
    {
        $query = Reminder::query();
        $scope($query);

        $total = (clone $query)->count();
        $completed = (clone $query)->where('is_completed', true)->count();
        $pending = (clone $query)->where('is_completed', false)->count();
        $overdue = (clone $query)->where('is_completed', false)->where('due_date', '<', now())->count();
        $dueSoon = (clone $query)->where('is_completed', false)->whereBetween('due_date', [now(), now()->addDays(7)])->count();

        $byPriority = (clone $query)->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $priorityData = [];
        foreach ($byPriority as $priority => $count) {
            $priorityData[] = [
                'priority' => $priority,
                'count' => $count,
            ];
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
            'by_priority' => $priorityData,
        ];
    }

    private function satisfaction(\Closure $scope, ?int $repId): array
    {
        $query = Client::query()->with('surveys');
        $scope($query);
        /** @var Collection<int, Client> $clients */
        $clients = $query->get();

        $totalSurveys = 0;
        $completedSurveys = 0;
        $totalScore = 0;
        $scoredSurveys = 0;
        $scoreDistribution = [0, 0, 0, 0, 0];

        foreach ($clients as $client) {
            $clientSurveys = $client->surveys;
            $totalSurveys += $clientSurveys->count();
            $clientCompleted = $clientSurveys->where('status', 'completed');
            $completedSurveys += $clientCompleted->count();

            foreach ($clientCompleted as $survey) {
                if ($survey->average_score !== null) {
                    $totalScore += $survey->average_score;
                    $scoredSurveys++;
                }
            }

            $latest = $clientCompleted->sortByDesc('completed_at')->first();
            if ($latest && $latest->average_score !== null) {
                $score = (float) $latest->average_score;
                if ($score >= 4.5) {
                    $scoreDistribution[4]++;
                } elseif ($score >= 3.5) {
                    $scoreDistribution[3]++;
                } elseif ($score >= 2.5) {
                    $scoreDistribution[2]++;
                } elseif ($score >= 1.5) {
                    $scoreDistribution[1]++;
                } else {
                    $scoreDistribution[0]++;
                }
            }
        }

        $avgScore = $scoredSurveys > 0 ? round($totalScore / $scoredSurveys, 1) : null;
        $responseRate = $totalSurveys > 0 ? round(($completedSurveys / $totalSurveys) * 100, 1) : 0.0;

        $perRep = $this->satisfactionPerRep($repId);

        return [
            'total_surveys' => $totalSurveys,
            'completed_surveys' => $completedSurveys,
            'pending_surveys' => $totalSurveys - $completedSurveys,
            'response_rate' => $responseRate,
            'average_score' => $avgScore,
            'score_distribution' => [
                ['label' => '1-1.9', 'count' => $scoreDistribution[0]],
                ['label' => '2-2.9', 'count' => $scoreDistribution[1]],
                ['label' => '3-3.9', 'count' => $scoreDistribution[2]],
                ['label' => '4-4.9', 'count' => $scoreDistribution[3]],
                ['label' => '5', 'count' => $scoreDistribution[4]],
            ],
            'trend' => $this->satisfactionTrend($clients),
            'by_question' => $this->satisfactionByQuestion($clients),
            'per_rep' => $perRep,
        ];
    }

    /**
     * Average completed-survey score per month, oldest first.
     */
    private function satisfactionTrend(Collection $clients): array
    {
        $byMonth = [];
        foreach ($clients as $client) {
            foreach ($client->surveys as $survey) {
                if ($survey->status !== ClientSurveyStatus::COMPLETED
                    || $survey->average_score === null
                    || $survey->completed_at === null) {
                    continue;
                }
                $month = $survey->completed_at->format('Y-m');
                $byMonth[$month][] = (float) $survey->average_score;
            }
        }
        ksort($byMonth);

        $trend = [];
        foreach ($byMonth as $month => $scores) {
            $trend[] = [
                'month' => $month,
                'average_score' => round(array_sum($scores) / count($scores), 1),
            ];
        }

        return $trend;
    }

    /**
     * Average score per survey question across completed surveys. Responses
     * are keyed by question id (q1..q5) with no template text, so each is
     * labelled by its position.
     */
    private function satisfactionByQuestion(Collection $clients): array
    {
        $scoresByQuestion = [];
        foreach ($clients as $client) {
            foreach ($client->surveys as $survey) {
                if ($survey->status !== ClientSurveyStatus::COMPLETED || empty($survey->responses)) {
                    continue;
                }
                foreach ($survey->responses as $response) {
                    $question = is_array($response) ? ($response['question_id'] ?? null) : null;
                    $score = is_array($response) && isset($response['score']) ? (float) $response['score'] : null;
                    if ($question !== null && $score !== null) {
                        $scoresByQuestion[$question][] = $score;
                    }
                }
            }
        }
        ksort($scoresByQuestion);

        $byQuestion = [];
        foreach ($scoresByQuestion as $question => $scores) {
            $position = preg_replace('/\D+/', '', (string) $question);
            $byQuestion[] = [
                'question' => $question,
                'label' => $position !== '' ? 'Question '.$position : $question,
                'average_score' => round(array_sum($scores) / count($scores), 1),
            ];
        }

        return $byQuestion;
    }

    private function satisfactionPerRep(?int $repId): array
    {
        $query = Client::query()
            ->join('client_surveys', 'clients.id', '=', 'client_surveys.client_id')
            ->where('client_surveys.status', 'completed')
            ->whereNotNull('client_surveys.average_score');

        if ($repId) {
            $query->where('clients.assigned_to_id', $repId);
        }

        $rows = $query
            ->select('clients.assigned_to_id as rep_id', DB::raw('count(*) as surveys'), DB::raw('AVG(client_surveys.average_score) as avg_score'))
            ->groupBy('clients.assigned_to_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $user = $row->rep_id ? User::find($row->rep_id) : null;
            $result[] = [
                'rep_id' => $row->rep_id,
                'rep_name' => $user?->name ?? 'Unassigned',
                'surveys' => (int) $row->surveys,
                'average_score' => $row->avg_score !== null ? round((float) $row->avg_score, 1) : null,
            ];
        }

        return $result;
    }

    /**
     * Per-sales-rep KPI comparison. Admin and manager get every active rep —
     * the cross-rep view — while a sales rep gets their own single row.
     */
    private function performance(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->isAdmin() || $user->isManager()) {
            $reps = User::query()
                ->where('role', UserRole::SALES_REP)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);

            return $reps
                ->map(fn ($rep) => $this->repPerformanceRow($rep->id, $rep->name))
                ->values()
                ->all();
        }

        return [$this->repPerformanceRow($user->id, $user->name)];
    }

    /**
     * Build the KPI row for a single sales rep (open pipeline + satisfaction).
     */
    private function repPerformanceRow(int $repId, string $repName): array
    {
        $oppQuery = Opportunity::query()->where('assigned_to_id', $repId);
        $total = (clone $oppQuery)->count();
        $won = (clone $oppQuery)->where('stage', OpportunityStage::WON)->count();
        $lost = (clone $oppQuery)->where('stage', OpportunityStage::LOST)->count();
        $closed = $won + $lost;

        $surveyQuery = ClientSurvey::query()
            ->join('clients', 'client_surveys.client_id', '=', 'clients.id')
            ->where('clients.assigned_to_id', $repId);
        $sent = (clone $surveyQuery)->count();
        $completed = (clone $surveyQuery)->where('client_surveys.status', ClientSurveyStatus::COMPLETED->value)->count();

        return [
            'rep_id' => $repId,
            'rep_name' => $repName,
            'open_opportunities' => max(0, $total - $won - $lost),
            'pipeline_value' => round((float) (clone $oppQuery)
                ->whereNotIn('stage', [OpportunityStage::WON, OpportunityStage::LOST])
                ->sum('estimated_contract_value'), 2),
            'won_count' => $won,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0,
            'average_score' => $this->repAverageScore($repId),
            'response_rate' => $sent > 0 ? round(($completed / $sent) * 100, 1) : 0.0,
        ];
    }

    /**
     * Average score across the rep's completed surveys, or null when none.
     */
    private function repAverageScore(int $repId): ?float
    {
        $avg = ClientSurvey::query()
            ->join('clients', 'client_surveys.client_id', '=', 'clients.id')
            ->where('clients.assigned_to_id', $repId)
            ->where('client_surveys.status', ClientSurveyStatus::COMPLETED->value)
            ->whereNotNull('client_surveys.average_score')
            ->avg('client_surveys.average_score');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    private function applyDateRange(Builder $query, string $column, $from, $to): void
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }

    /**
     * Portable "YYYY-MM" truncation expression.
     *
     * SQLite (tests) uses strftime, PostgreSQL uses to_char, MySQL uses
     * DATE_FORMAT — there is no single function across all three drivers.
     *
     * The column name is interpolated bare (lowercase, snake_case); quoting
     * differs per driver so we let the DBMS resolve it as-is.
     */
    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'mysql' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "strftime('%Y-%m', {$column})",
        };
    }
}
