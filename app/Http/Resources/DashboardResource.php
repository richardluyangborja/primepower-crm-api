<?php

namespace App\Http\Resources;

use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->summary(),
            'leads' => $this->leads(),
            'clients' => $this->clients(),
            'opportunities' => $this->opportunities(),
            'communications' => $this->communications(),
            'reminders' => $this->reminders(),
            'satisfaction' => $this->satisfaction(),
        ];
    }

    private function summary(): array
    {
        $totalLeads = Lead::count();
        $totalClients = Client::count();
        $totalOpportunities = Opportunity::count();
        $wonOpportunities = Opportunity::where('stage', OpportunityStage::WON)->count();
        $totalContractValue = Opportunity::where('stage', OpportunityStage::WON)->sum('estimated_contract_value');
        $activeReminders = Reminder::where('is_completed', false)->count();

        return [
            'total_leads' => $totalLeads,
            'total_clients' => $totalClients,
            'total_opportunities' => $totalOpportunities,
            'won_opportunities' => $wonOpportunities,
            'total_contract_value' => $totalContractValue,
            'active_reminders' => $activeReminders,
            'conversion_rate' => $totalLeads > 0
                ? round(($wonOpportunities / $totalLeads) * 100, 1)
                : 0,
        ];
    }

    private function leads(): array
    {
        $byStatus = Lead::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusData = [];
        foreach (LeadStatus::cases() as $status) {
            $statusData[] = [
                'status' => $status->value,
                'count' => $byStatus[$status->value] ?? 0,
            ];
        }

        $monthly = Lead::select(
            DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
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

        $bySource = Lead::select('source', DB::raw('count(*) as count'))
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

        return [
            'by_status' => $statusData,
            'by_month' => $monthlyData,
            'by_source' => $sourceData,
        ];
    }

    private function clients(): array
    {
        $activeClients = Client::where('status', 'active')->count();
        $inactiveClients = Client::where('status', 'inactive')->count();

        $monthly = Client::select(
            DB::raw("TO_CHAR(client_since, 'YYYY-MM') as month"),
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

        $byIndustry = Client::join('companies', 'clients.company_id', '=', 'companies.id')
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

        return [
            'active' => $activeClients,
            'inactive' => $inactiveClients,
            'by_month' => $monthlyData,
            'by_industry' => $industryData,
        ];
    }

    private function opportunities(): array
    {
        $byStage = Opportunity::select('stage', DB::raw('count(*) as count'))
            ->groupBy('stage')
            ->pluck('count', 'stage');

        $stageData = [];
        foreach (OpportunityStage::cases() as $stage) {
            $stageData[] = [
                'stage' => $stage->value,
                'count' => $byStage[$stage->value] ?? 0,
            ];
        }

        $valueByStage = Opportunity::select('stage', DB::raw('sum(estimated_contract_value) as total'))
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $valueData = [];
        foreach (OpportunityStage::cases() as $stage) {
            $valueData[] = [
                'stage' => $stage->value,
                'value' => (float) ($valueByStage[$stage->value] ?? 0),
            ];
        }

        $monthlyWon = Opportunity::where('stage', OpportunityStage::WON)
            ->select(
                DB::raw("TO_CHAR(updated_at, 'YYYY-MM') as month"),
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
                'count' => $row->count,
                'total_value' => (float) $row->total_value,
            ];
        }

        $avgDealSize = Opportunity::where('stage', OpportunityStage::WON)
            ->avg('estimated_contract_value');

        return [
            'by_stage' => $stageData,
            'value_by_stage' => $valueData,
            'monthly_won' => $monthlyWonData,
            'avg_deal_size' => (float) ($avgDealSize ?? 0),
        ];
    }

    private function communications(): array
    {
        $totalCommunications = Communication::count();

        $byType = Communication::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type');

        $typeData = [];
        foreach ($byType as $type => $count) {
            $typeData[] = [
                'type' => $type,
                'count' => $count,
            ];
        }

        $byDirection = Communication::select('direction', DB::raw('count(*) as count'))
            ->groupBy('direction')
            ->pluck('count', 'direction');

        $directionData = [];
        foreach ($byDirection as $direction => $count) {
            $directionData[] = [
                'direction' => $direction,
                'count' => $count,
            ];
        }

        $monthly = Communication::select(
            DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
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
            'total' => $totalCommunications,
            'by_type' => $typeData,
            'by_direction' => $directionData,
            'by_month' => $monthlyData,
        ];
    }

    private function reminders(): array
    {
        $totalReminders = Reminder::count();
        $completedReminders = Reminder::where('is_completed', true)->count();
        $pendingReminders = Reminder::where('is_completed', false)->count();

        $byPriority = Reminder::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $priorityData = [];
        foreach ($byPriority as $priority => $count) {
            $priorityData[] = [
                'priority' => $priority,
                'count' => $count,
            ];
        }

        $overdueReminders = Reminder::where('is_completed', false)
            ->where('due_date', '<', now())
            ->count();

        $dueSoonReminders = Reminder::where('is_completed', false)
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->count();

        return [
            'total' => $totalReminders,
            'completed' => $completedReminders,
            'pending' => $pendingReminders,
            'overdue' => $overdueReminders,
            'due_soon' => $dueSoonReminders,
            'by_priority' => $priorityData,
        ];
    }

    private function satisfaction(): array
    {
        $clients = Client::with('surveys')->get();

        $totalSurveys = 0;
        $completedSurveys = 0;
        $totalScore = 0;
        $scoredSurveys = 0;

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
        }

        $avgScore = $scoredSurveys > 0 ? round($totalScore / $scoredSurveys, 1) : null;

        $scoreDistribution = [0, 0, 0, 0, 0];
        foreach ($clients as $client) {
            $latestSurvey = $client->surveys
                ->where('status', 'completed')
                ->sortByDesc('completed_at')
                ->first();

            if ($latestSurvey && $latestSurvey->average_score !== null) {
                $score = (float) $latestSurvey->average_score;
                if ($score >= 4.5) $scoreDistribution[4]++;
                elseif ($score >= 3.5) $scoreDistribution[3]++;
                elseif ($score >= 2.5) $scoreDistribution[2]++;
                elseif ($score >= 1.5) $scoreDistribution[1]++;
                else $scoreDistribution[0]++;
            }
        }

        $distributionData = [
            ['label' => '1-1.9', 'count' => $scoreDistribution[0]],
            ['label' => '2-2.9', 'count' => $scoreDistribution[1]],
            ['label' => '3-3.9', 'count' => $scoreDistribution[2]],
            ['label' => '4-4.9', 'count' => $scoreDistribution[3]],
            ['label' => '5', 'count' => $scoreDistribution[4]],
        ];

        return [
            'total_surveys' => $totalSurveys,
            'completed_surveys' => $completedSurveys,
            'average_score' => $avgScore,
            'score_distribution' => $distributionData,
        ];
    }
}
