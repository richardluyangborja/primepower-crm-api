<?php

namespace App\Actions\Ai;

use App\Actions\Analytics\OpportunityAnalytics;
use App\Actions\Analytics\RepPerformanceAnalytics;
use App\Actions\Analytics\SatisfactionAnalytics;
use App\Enums\AiReportType;
use App\Models\ActionSuggestionSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Compose the prompt sent to the external Groq AI service for AI reports.
 *
 * The digest is numbers-only: it never carries company names, client names,
 * representative names, emails, or any other identifying detail, so no
 * sensitive data leaves the backend. Suggested actions are collapsed into
 * counts by type rather than the raw flagged items.
 */
class ComposeAiReportPrompt
{
    /**
     * Rule-based suggestion types may read like internal identifiers; map
     * them to plain wording for the prompt.
     */
    private const SUGGESTION_LABELS = [
        'stalled' => 'stalled opportunity follow-ups',
        'closing' => 'closing-soon opportunity follow-ups',
        'date' => 'opportunities missing an expected close date',
        'review' => 'general review items',
        'low_score' => 'low-score client follow-ups',
        'at_risk' => 'at-risk client check-ins',
        'pending' => 'pending survey follow-ups',
    ];

    private const SYSTEM_MESSAGE = <<<'TXT'
You compose CRM business reports from aggregated analytics. Write the report using ONLY the data supplied below. Do not invent figures, names, or facts.

Output a plain-text report with exactly these five sections, in this order:
1. Executive Summary
2. Key Findings
3. Areas Requiring Attention
4. Suggested Actions
5. Summary

Keep formatting minimal: short headings, simple bullet lists, and blank lines between sections. Do not use markdown symbols (*, #, **). Never include company names, client names, employee names, email addresses, or any other identifying details. Refer to entities generically, for example "a key opportunity" or "the lowest-scoring account".
TXT;

    private Collection $settings;

    /**
     * @return array{system: string, prompt: string}
     */
    public function compose(AiReportType $type, User $user, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $this->settings = collect(ActionSuggestionSetting::pluck('value', 'key')->all());

        return [
            'system' => self::SYSTEM_MESSAGE,
            'prompt' => match ($type) {
                AiReportType::OPPORTUNITY => $this->opportunityPrompt($user, $from, $to),
                AiReportType::SATISFACTION => $this->satisfactionPrompt($user, $from, $to),
                AiReportType::REP_PERFORMANCE => $this->repPerformancePrompt($user, $from, $to),
            },
        ];
    }

    private function opportunityPrompt(User $user, ?CarbonInterface $from, ?CarbonInterface $to): string
    {
        $metrics = app(OpportunityAnalytics::class)
            ->analyze($user, $this->settings->all(), $from, $to)['metrics'];
        $suggestions = collect(app(OpportunityAnalytics::class)
            ->analyze($user, $this->settings->all(), $from, $to)['suggested_actions']);

        $lines = [
            'REPORT TOPIC: Opportunity pipeline performance',
            'PERIOD: '.$this->periodLabel($from, $to),
            'DATA:',
            '- Total open opportunities: '.$metrics['total_open'],
            '- Total open value: '.$this->money($metrics['total_value']),
            '- Weighted value: '.$this->money($metrics['weighted_value']),
            '- Won: '.$metrics['won_count'].' ('.$this->money($metrics['won_value']).')',
            '- Lost: '.$metrics['lost_count'].' ('.$this->money($metrics['lost_value']).')',
        ];

        foreach ($metrics['per_stage'] as $stage) {
            $lines[] = '- Stage: '.$stage['label'].' — '.$stage['count'].' deal'.($stage['count'] === 1 ? '' : 's').' ('.$this->money($stage['value']).')';
        }

        $attention = [
            'Stalled opportunities ('.$this->setting('opportunity_stalled_days', 14).' or more days in stage): '.count($metrics['stalled']),
            'Expected to close within '.$this->setting('opportunity_closing_soon_days', 30).' days: '.count($metrics['closing_soon']),
            'High-value open deals missing an expected close date: '.count(array_filter($metrics['top_deals'], fn (array $deal) => $deal['close_date'] === null)),
        ];

        $lines = array_merge($lines, [
            'AREAS REQUIRING ATTENTION:',
            ...array_map(fn (string $line) => '- '.$line, $attention),
            'SUGGESTED ACTIONS (rule-based, counts by type):',
            '- '.$this->suggestionsSummary($suggestions),
        ]);

        return implode(PHP_EOL, $lines);
    }

    private function satisfactionPrompt(User $user, ?CarbonInterface $from, ?CarbonInterface $to): string
    {
        $analysis = app(SatisfactionAnalytics::class)->analyze($user, $this->settings->all(), $from, $to);
        $metrics = $analysis['metrics'];
        $suggestions = collect($analysis['suggested_actions']);

        $lines = [
            'REPORT TOPIC: Client satisfaction',
            'PERIOD: '.$this->periodLabel($from, $to),
            'DATA:',
            '- Clients: '.$metrics['clients'],
            '- Surveys sent: '.$metrics['surveys_sent'],
            '- Surveys completed: '.$metrics['surveys_completed'],
            '- Pending surveys: '.$metrics['pending_count'],
            '- Response rate: '.$metrics['response_rate'].'%',
            '- Average score: '.($metrics['average_score'] ?? 'no completed surveys').' / 5',
        ];

        foreach ($metrics['score_bands'] as $band) {
            $lines[] = '- '.$band['band'].' satisfaction: '.$band['count'].' completed survey'.($band['count'] === 1 ? '' : 's');
        }

        $attention = [
            'Clients marked at risk: '.$metrics['at_risk_count'],
            'Clients whose latest score is low: '.count($metrics['low_score']),
            'Clients with pending surveys: '.count($metrics['pending']),
        ];

        $lines = array_merge($lines, [
            'AREAS REQUIRING ATTENTION:',
            ...array_map(fn (string $line) => '- '.$line, $attention),
            'SUGGESTED ACTIONS (rule-based, counts by type):',
            '- '.$this->suggestionsSummary($suggestions),
        ]);

        return implode(PHP_EOL, $lines);
    }

    private function repPerformancePrompt(User $user, ?CarbonInterface $from, ?CarbonInterface $to): string
    {
        $result = app(RepPerformanceAnalytics::class)->rows($user, $from, $to);

        $lines = [
            'REPORT TOPIC: Sales representative performance',
            'PERIOD: '.$this->periodLabel($from, $to),
            'DATA:',
            '- Active representatives: '.$result['rep_count'],
        ];

        foreach ($result['reps'] as $rep) {
            $lines[] = '- '.$rep['rep'].': '.$rep['open_opportunities'].' open ('.$this->money($rep['pipeline_value']).' pipeline), '
                .$rep['won_count'].' won, '.$rep['win_rate'].'% win rate, '
                .'avg satisfaction '.($rep['average_score'] ?? 'n/a').'/5, '
                .$rep['response_rate'].'% survey response';
        }

        if ($result['rep_count'] > 0) {
            $winRates = array_column($result['reps'], 'win_rate');
            $lines[] = '- Win-rate spread: '.min($winRates).'% to '.max($winRates).'% (average '.round(array_sum($winRates) / $result['rep_count'], 1).'%)';
            $lines[] = '- Combined open pipeline: '.$this->money(array_sum(array_column($result['reps'], 'pipeline_value')));
        }

        return implode(PHP_EOL, $lines);
    }

    private function suggestionsSummary(Collection $suggestions): string
    {
        if ($suggestions->isEmpty()) {
            return 'none in the selected period';
        }

        $parts = $suggestions
            ->countBy(fn (array $suggestion) => $suggestion['type'])
            ->map(function (int $count, string $type) {
                $label = self::SUGGESTION_LABELS[$type] ?? str_replace('_', ' ', $type);

                return $count.' '.$label;
            });

        return implode('; ', $parts->all());
    }

    private function periodLabel(?CarbonInterface $from, ?CarbonInterface $to): string
    {
        if ($from === null && $to === null) {
            return 'all time';
        }

        return $from?->toDateString().' to '.($to?->toDateString() ?? 'now');
    }

    private function setting(string $key, int $default): int
    {
        $value = $this->settings->get($key);

        return $value === null ? $default : (int) $value;
    }

    private function money(float $value): string
    {
        return number_format($value, 0);
    }
}
