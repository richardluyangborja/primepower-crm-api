<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SatisfactionReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $this->authorizeReport($user);

        $clients = $this->scopedClients($user, $request);

        $rows = $this->perRepRows($clients);

        return response()->json([
            'data' => array_values($rows),
            'summary' => $this->summary($rows),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $this->authorizeReport($user);

        $clients = $this->scopedClients($user, $request);

        $rows = $this->perRepRows($clients);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client Satisfaction',
            'action' => 'Report Exported',
            'subject_type' => 'Client',
            'subject_id' => null,
            'subject_name' => 'Satisfaction report CSV',
            'description' => 'Satisfaction report CSV export requested.',
            'metadata' => $request->only(['rep_id']),
        ]);

        $fileName = 'satisfaction-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'rep_id',
                'rep_name',
                'clients',
                'surveys_sent',
                'surveys_completed',
                'response_rate',
                'average_score',
                'at_risk',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['rep_id'],
                    $row['rep_name'],
                    $row['clients_count'],
                    $row['surveys_sent'],
                    $row['surveys_completed'],
                    $row['response_rate'],
                    $row['average_score'],
                    $row['at_risk_count'],
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function authorizeReport(User $user): void
    {
        // Aggregated satisfaction reports are admin/manager only. Sales reps
        // use /satisfaction/mine (wired on the sales surface in a later pass).
        abort_unless(
            in_array($user->role, [UserRole::ADMIN, UserRole::MANAGER], true),
            403,
            'Satisfaction reports are restricted to administrators and managers.'
        );
    }

    private function scopedClients(User $user, Request $request): Collection
    {
        $repId = $request->integer('rep_id') ?: null;

        $query = Client::query()
            ->with(['surveys', 'assignedTo']);

        if ($repId) {
            $query->where('assigned_to_id', $repId);
        }

        if ($user->isManager()) {
            $query->whereIn('assigned_to_id', $user->visibleUserIds());
        }

        return $query->get();
    }

    /**
     * Group clients by their assigned representative and compute one report
     * row per rep (including unassigned clients as an "Unassigned" bucket).
     */
    private function perRepRows(Collection $clients): array
    {
        $grouped = $clients->groupBy(fn (Client $client) => $client->assigned_to_id ?? 'unassigned');

        $rows = [];

        foreach ($grouped as $repKey => $repClients) {
            $rep = $repKey === 'unassigned' ? null : User::find($repKey);

            $surveys = $repClients->flatMap->surveys;
            $total = $surveys->count();
            $completed = $surveys->where('status', 'completed');
            $completedCount = $completed->count();

            $scored = $completed->filter(fn ($s) => $s->average_score !== null);
            $averageScore = $scored->isEmpty()
                ? null
                : round($scored->avg('average_score'), 1);

            $rows[] = [
            'rep_id' => $rep?->id,
            'rep_name' => $rep?->name ?? 'Unassigned',
            'clients_count' => $repClients->count(),
                'surveys_sent' => $total,
                'surveys_completed' => $completedCount,
                'response_rate' => $total > 0 ? round(($completedCount / $total) * 100, 1) : 0.0,
                'average_score' => $averageScore,
                'at_risk_count' => $repClients->where('at_risk', true)->count(),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['rep_name'], $b['rep_name']));

        return $rows;
    }

    private function summary(array $rows): array
    {
        $clients = array_sum(array_column($rows, 'clients_count'));
        $sent = array_sum(array_column($rows, 'surveys_sent'));
        $completed = array_sum(array_column($rows, 'surveys_completed'));

        $scoredSurveys = 0;
        $scoreTotal = 0;
        foreach ($rows as $row) {
            if ($row['average_score'] !== null) {
                // Recompute from underlying completed surveys is more accurate,
                // but per-row averages are a reasonable report-level aggregate.
                $scoreTotal += $row['average_score'] * $row['surveys_completed'];
                $scoredSurveys += $row['surveys_completed'];
            }
        }

        return [
            'clients' => $clients,
            'surveys_sent' => $sent,
            'surveys_completed' => $completed,
            'response_rate' => $sent > 0 ? round(($completed / $sent) * 100, 1) : 0.0,
            'average_score' => $scoredSurveys > 0 ? round($scoreTotal / $scoredSurveys, 1) : null,
            'at_risk_clients' => array_sum(array_column($rows, 'at_risk_count')),
        ];
    }
}
