<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClientSatisfactionDetailResource;
use App\Http\Resources\ClientSatisfactionSummaryResource;
use App\Http\Resources\ClientSurveyResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientSurvey;
use Illuminate\Http\Request;

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
        $survey = ClientSurvey::create([
            'client_id' => $client->id,
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
