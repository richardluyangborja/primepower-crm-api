<?php

namespace App\Http\Controllers;

use App\Actions\Ai\GeminiAiClient;
use App\Actions\Analytics\OpportunityAnalytics;
use App\Actions\Analytics\SatisfactionAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AnalyticsController extends Controller
{
    public function __construct(private readonly GeminiAiClient $ai) {}

    /**
     * Verify the AI integration: that it is configured and reachable.
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorize('view', GeminiAiClient::class);

        if (! $this->ai->configured()) {
            return response()->json([
                'configured' => false,
                'reachable' => false,
                'message' => 'AI is not configured. Set AI_API_KEY in the environment.',
            ], 422);
        }

        $startedAt = hrtime(true);

        try {
            $reply = $this->ai->chat('Reply with the single word: OK');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'configured' => true,
                'reachable' => false,
                'message' => 'Could not reach Gemini: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'configured' => true,
            'reachable' => true,
            'model' => config('ai.model'),
            'reply' => mb_substr($reply, 0, 40),
            'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }

    /**
     * Deterministic pipeline analytics + suggested actions, scoped to whatever
     * opportunities the requesting user can see.
     */
    public function opportunities(Request $request): JsonResponse
    {
        $this->authorize('viewOpportunities', GeminiAiClient::class);

        return response()->json([
            'data' => app(OpportunityAnalytics::class)->analyze($request->user()),
        ]);
    }

    /**
     * Deterministic satisfaction analytics + suggested actions, scoped to
     * whatever clients the requesting user can see.
     */
    public function satisfaction(Request $request): JsonResponse
    {
        $this->authorize('viewSatisfaction', GeminiAiClient::class);

        return response()->json([
            'data' => app(SatisfactionAnalytics::class)->analyze($request->user()),
        ]);
    }
}
