<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\OpportunityAnalytics;
use App\Actions\Analytics\SatisfactionAnalytics;
use App\Models\ActionSuggestionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActionSuggestionController extends Controller
{
    /**
     * Allowed setting keys and their types.
     */
    private const ALLOWED_KEYS = [
        'opportunity_stalled_days' => 'int',
        'opportunity_closing_soon_days' => 'int',
        'opportunity_max_stalled_actions' => 'int',
        'opportunity_max_closing_actions' => 'int',
        'opportunity_max_missing_date_actions' => 'int',
        'satisfaction_low_score_threshold' => 'float',
        'satisfaction_high_score_threshold' => 'float',
        'satisfaction_max_low_score_actions' => 'int',
        'satisfaction_max_at_risk_actions' => 'int',
        'satisfaction_max_pending_actions' => 'int',
        'action_cap' => 'int',
    ];

    /**
     * Return rule-based action suggestions for opportunity pipeline and
     * client satisfaction, scoped to whatever the authenticated user can see.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = ActionSuggestionSetting::pluck('value', 'key')->all();
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

    /**
     * Return all action suggestion settings (admin only).
     */
    public function settings(): JsonResponse
    {
        $this->authorize('view', ActionSuggestionSetting::class);

        $settings = ActionSuggestionSetting::pluck('value', 'key')->all();

        return response()->json(['data' => $settings]);
    }

    /**
     * Update action suggestion settings (admin only).
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('update', ActionSuggestionSetting::class);

        $validator = Validator::make(
            $request->all(),
            array_fill_keys(array_keys(self::ALLOWED_KEYS), 'nullable|numeric|min:1')
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        foreach ($data as $key => $value) {
            if (in_array($key, array_keys(self::ALLOWED_KEYS), true)) {
                ActionSuggestionSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $value]
                );
            }
        }

        $settings = ActionSuggestionSetting::pluck('value', 'key')->all();

        return response()->json(['data' => $settings]);
    }
}
