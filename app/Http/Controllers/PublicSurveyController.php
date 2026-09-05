<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClientSurveyResource;
use App\Models\ClientSurvey;
use App\Models\SurveyTemplate;
use Illuminate\Http\Request;

class PublicSurveyController extends Controller
{
    private const DEFAULT_QUESTIONS = [
        ['id' => 'q1', 'text' => 'How satisfied are you with our communication?', 'category' => 'Communication'],
        ['id' => 'q2', 'text' => 'How would you rate the quality of our deliverables?', 'category' => 'Quality'],
        ['id' => 'q3', 'text' => 'How satisfied are you with our timeliness?', 'category' => 'Timeliness'],
        ['id' => 'q4', 'text' => 'How likely are you to recommend our services?', 'category' => 'Loyalty'],
        ['id' => 'q5', 'text' => 'How satisfied are you with our responsiveness?', 'category' => 'Support'],
    ];

    public function show(string $token)
    {
        $survey = ClientSurvey::with(['client.company', 'templateVersion'])->where('token', $token)->first();

        if (! $survey) {
            abort(404, 'Survey not found');
        }

        if ($survey->status === 'expired') {
            abort(410, 'Survey has expired');
        }

        $questions = $survey->templateVersion?->questions ?? $this->defaultQuestions();

        return response()->json([
            'data' => [
                'token' => $survey->token,
                'status' => $survey->status->value,
                'company' => [
                    'name' => $survey->client->company->name,
                    'industry' => $survey->client->company->industry,
                ],
                'questions' => $questions,
            ],
        ]);
    }

    private function defaultQuestions(): array
    {
        // Fall back to the "Default" template's current questions if one exists,
        // otherwise the built-in five.
        $default = SurveyTemplate::where('name', 'Default')->first();

        return $default?->currentVersion?->questions ?? self::DEFAULT_QUESTIONS;
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
