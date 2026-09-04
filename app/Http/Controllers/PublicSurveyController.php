<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClientSurveyResource;
use App\Models\ClientSurvey;
use Illuminate\Http\Request;

class PublicSurveyController extends Controller
{
    public function show(string $token)
    {
        $survey = ClientSurvey::with('client.company')->where('token', $token)->first();

        if (! $survey) {
            abort(404, 'Survey not found');
        }

        if ($survey->status === 'expired') {
            abort(410, 'Survey has expired');
        }

        return response()->json([
            'data' => [
                'token' => $survey->token,
                'status' => $survey->status->value,
                'company' => [
                    'name' => $survey->client->company->name,
                    'industry' => $survey->client->company->industry,
                ],
            ],
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $survey = ClientSurvey::where('token', $token)->first();

        if (! $survey) {
            abort(404, 'Survey not found');
        }

        if ($survey->status === 'completed') {
            abort(400, 'Survey already completed');
        }

        if ($survey->status === 'expired') {
            abort(410, 'Survey has expired');
        }

        $validated = $request->validate([
            'responses' => 'required|array|min:1',
            'responses.*.question_id' => 'required|string',
            'responses.*.score' => 'required|integer|min:1|max:5',
            'respondent_name' => 'nullable|string|max:255',
            'respondent_position' => 'nullable|string|max:255',
            'feedback' => 'nullable|string',
        ]);

        $responses = $validated['responses'];
        $scores = array_column($responses, 'score');
        $averageScore = round(array_sum($scores) / count($scores), 2);

        $survey->update([
            'responses' => $responses,
            'average_score' => $averageScore,
            'status' => 'completed',
            'completed_at' => now(),
            'respondent_name' => $validated['respondent_name'] ?? null,
            'respondent_position' => $validated['respondent_position'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        return response()->json([
            'message' => 'Survey submitted successfully',
            'data' => new ClientSurveyResource($survey->fresh()),
        ]);
    }
}
