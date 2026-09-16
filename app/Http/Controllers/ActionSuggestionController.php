<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\OpportunityAnalytics;
use App\Actions\Analytics\SatisfactionAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionSuggestionController extends Controller
{
    /**
     * Default thresholds used for rule-based action suggestions (replaces the
     * former admin-editable settings table; mirror of the analytics engines'
     * class-constant defaults).
     */
    public const DEFAULT_SETTINGS = [
        'opportunity_stalled_days' => 14,
        'opportunity_closing_soon_days' => 30,
        'opportunity_max_stalled_actions' => 3,
        'opportunity_max_closing_actions' => 3,
        'opportunity_max_missing_date_actions' => 2,
        'satisfaction_low_score_threshold' => 2.5,
        'satisfaction_high_score_threshold' => 4.0,
        'satisfaction_max_low_score_actions' => 2,
        'satisfaction_max_at_risk_actions' => 2,
        'satisfaction_max_pending_actions' => 2,
        'action_cap' => 8,
    ];

    /**
     * Return rule-based action suggestions for opportunity pipeline and
     * client satisfaction, scoped to whatever the authenticated user can see.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = self::DEFAULT_SETTINGS;
        $user = $request->user();

        $opportunity = app(OpportunityAnalytics::class)->analyze($user, $settings);
        $satisfaction = app(SatisfactionAnalytics::class)->analyze($user, $settings);

        return response()->json([
            'data' => [
                'opportunity' => $opportunity,
                'satisfaction' => $satisfaction,
            ],
        ]);
    }
}
