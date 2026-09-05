<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClientSatisfactionDetailResource;
use App\Http\Resources\ClientSatisfactionSummaryResource;
use App\Http\Resources\ClientSurveyResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\SurveyTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientSatisfactionController extends Controller
{
    public function index()
    {
        $clients = Client::query()
            ->with([
                'company.primaryContact',
                'surveys',
            ])
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
            ->latest()
            ->paginate(15);

        return ClientSatisfactionSummaryResource::collection($clients);
    }

    public function show(Client $client)
    {
        $client->load([
            'company.primaryContact',
            'surveys',
        ]);

        return new ClientSatisfactionDetailResource($client);
    }

    public function store(Request $request, Client $client)
    {
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

    public function storeManual(Request $request, Client $client)
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'template_id' => ['nullable', 'integer', Rule::exists('survey_templates', 'id')],
            'responses' => ['required', 'array', 'min:1'],
            'responses.*.question_id' => ['required', 'string'],
            'responses.*.score' => ['required', 'integer', 'min:1', 'max:5'],
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'respondent_position' => ['nullable', 'string', 'max:255'],
            'feedback' => ['nullable', 'string'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $templateVersion = null;
        if (! empty($validated['template_id'])) {
            $templateVersion = SurveyTemplate::find($validated['template_id'])
                ?->currentVersion;
        }

        $responses = $validated['responses'];
        $scores = array_column($responses, 'score');
        $averageScore = count($scores) > 0
            ? round(array_sum($scores) / count($scores), 2)
            : null;

        $survey = ClientSurvey::create([
            'client_id' => $client->id,
            'template_version_id' => $templateVersion?->id,
            'token' => 'srv_'.bin2hex(random_bytes(16)),
            'status' => 'completed',
            'responses' => $responses,
            'average_score' => $averageScore,
            'completed_at' => $validated['completed_at'] ?? now(),
            'respondent_name' => $validated['respondent_name'] ?? null,
            'respondent_position' => $validated['respondent_position'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client Satisfaction',
            'action' => 'Survey Recorded (Manual)',
            'subject_type' => 'ClientSurvey',
            'subject_id' => (string) $survey->id,
            'subject_name' => $client->company?->name ?? "Client #{$client->id}",
            'description' => "A satisfaction survey was recorded manually for client '{$client->company?->name}'."
                .($request->user() ? " by {$request->user()->name}." : '.'),
            'metadata' => [
                'client_name' => $client->company?->name,
                'average_score' => $survey->average_score,
                'responses_count' => count($responses),
                'completed_at' => $survey->completed_at?->toDateTimeString(),
            ],
        ]);

        return new ClientSurveyResource($survey);
    }

    public function destroy(Client $client, ClientSurvey $survey)
    {
        if ($survey->client_id !== $client->id) {
            abort(404, 'Survey not found for this client');
        }

        if ($survey->status === 'completed') {
            abort(422, 'Cannot delete a completed survey');
        }

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
}
