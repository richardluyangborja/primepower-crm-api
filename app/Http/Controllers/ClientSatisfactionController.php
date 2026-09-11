<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClientSatisfactionDetailResource;
use App\Http\Resources\ClientSatisfactionSummaryResource;
use App\Http\Resources\ClientSurveyResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\SurveyTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Stringable;
use Illuminate\Validation\Rule;

class ClientSatisfactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $clients = Client::query()
            ->with([
                'company.primaryContact',
                'surveys',
            ])
            ->when(
                $user !== null && ! $user->isAdmin(),
                fn ($query) => $query->whereIn('assigned_to_id', $user->visibleUserIds())
            )
            ->when($request->filled('q'), fn ($query) => $this->applySearch($query, $request->string('q')))
            ->when($request->filled('trend'), fn ($query) => $this->applyTrendFilter($query, (string) $request->string('trend')))
            ->when($request->filled('score'), fn ($query) => $this->applyScoreFilter($query, (string) $request->string('score')))
            ->when($request->filled('from'), fn ($query) => $this->applySurveyDateFrom($query, $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $this->applySurveyDateTo($query, $request->date('to')))
            ->latest()
            ->paginate(15);

        return ClientSatisfactionSummaryResource::collection($clients);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $clients = Client::query()
            ->where('assigned_to_id', $userId)
            ->with([
                'company.primaryContact',
                'surveys',
            ])
            ->when($request->filled('q'), fn ($query) => $this->applySearch($query, $request->string('q')))
            ->when($request->filled('trend'), fn ($query) => $this->applyTrendFilter($query, (string) $request->string('trend')))
            ->when($request->filled('score'), fn ($query) => $this->applyScoreFilter($query, (string) $request->string('score')))
            ->when($request->filled('from'), fn ($query) => $this->applySurveyDateFrom($query, $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $this->applySurveyDateTo($query, $request->date('to')))
            ->latest()
            ->paginate(15);

        return ClientSatisfactionSummaryResource::collection($clients);
    }

    public function show(Request $request, Client $client)
    {
        $this->authorize('view', $client);

        $client->load([
            'company.primaryContact',
            'surveys',
        ]);

        return new ClientSatisfactionDetailResource($client);
    }

    public function store(Request $request, Client $client)
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'template_id' => ['nullable', 'integer', Rule::exists('survey_templates', 'id')],
        ]);

        $templateVersion = null;
        if (! empty($validated['template_id'])) {
            $templateVersion = SurveyTemplate::find($validated['template_id'])
                ?->currentVersion;
        }

        $survey = ClientSurvey::create([
            'client_id' => $client->id,
            'template_version_id' => $templateVersion?->id,
            'token' => 'srv_'.bin2hex(random_bytes(16)),
            'status' => 'pending',
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client Satisfaction',
            'action' => 'Survey Created',
            'subject_type' => 'ClientSurvey',
            'subject_id' => (string) $survey->id,
            'subject_name' => $client->company?->name ?? "Client #{$client->id}",
            'description' => "A satisfaction survey was created for client '{$client->company?->name}'."
                .($request->user() ? " Sent by {$request->user()->name}." : ''),
            'metadata' => [
                'client_name' => $client->company?->name,
                'survey_token' => $survey->token,
                'status' => $survey->status,
            ],
        ]);

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));

        return response()->json([
            'data' => [
                'survey' => new ClientSurveyResource($survey),
                'link' => "{$frontendUrl}/survey/{$survey->token}",
            ],
        ]);
    }

    public function destroy(Request $request, Client $client, ClientSurvey $survey)
    {
        if ($survey->client_id !== $client->id) {
            abort(404, 'Survey not found for this client');
        }

        $this->authorize('update', $survey->client);

        $surveyToken = $survey->token;
        $surveyStatus = $survey->status;

        $survey->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client Satisfaction',
            'action' => 'Survey Deleted',
            'subject_type' => 'ClientSurvey',
            'subject_id' => (string) $survey->id,
            'subject_name' => $client->company?->name ?? "Client #{$client->id}",
            'description' => "A satisfaction survey for client '{$client->company?->name}' was deleted.",
            'metadata' => [
                'client_name' => $client->company?->name,
                'survey_token' => $surveyToken,
                'status' => $surveyStatus,
            ],
        ]);

        return response()->json([
            'message' => 'Survey deleted successfully',
        ]);
    }

    private function applySearch(Builder $query, Stringable $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->whereHas('company', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('company.primaryContact', function ($cq) use ($search) {
                    $cq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
        });
    }

    private function applyTrendFilter(Builder $query, string $trend): Builder
    {
        $operator = match ($trend) {
            'up' => '>',
            'down' => '<',
            'stable' => '=',
            default => null,
        };

        if ($operator === null) {
            return $query;
        }

        return $query->whereRaw(
            "{$this->latestScoreSubquery()} {$operator} {$this->previousScoreSubquery()}"
        );
    }

    private function applyScoreFilter(Builder $query, string $score): Builder
    {
        $subquery = $this->averageScoreSubquery();

        return match ($score) {
            'ge4' => $query->whereRaw("{$subquery} >= 4"),
            'ge3' => $query->whereRaw("{$subquery} >= 3 AND {$subquery} < 4"),
            'lt3' => $query->whereRaw("{$subquery} < 3"),
            default => $query,
        };
    }

    private function applySurveyDateFrom(Builder $query, Carbon $from): Builder
    {
        return $query->whereHas('surveys', function (Builder $q) use ($from) {
            $q->where('status', 'completed')
                ->whereDate('completed_at', '>=', $from);
        });
    }

    private function applySurveyDateTo(Builder $query, Carbon $to): Builder
    {
        return $query->whereHas('surveys', function (Builder $q) use ($to) {
            $q->where('status', 'completed')
                ->whereDate('completed_at', '<=', $to);
        });
    }

    private function averageScoreSubquery(): string
    {
        return '(select avg(average_score) from client_surveys where client_id = clients.id and status = \'completed\')';
    }

    private function latestScoreSubquery(): string
    {
        return '(select s.average_score from client_surveys s where s.client_id = clients.id and s.status = \'completed\' order by s.completed_at desc limit 1)';
    }

    private function previousScoreSubquery(): string
    {
        return '(select s.average_score from client_surveys s where s.client_id = clients.id and s.status = \'completed\' order by s.completed_at desc limit 1 offset 1)';
    }
}
