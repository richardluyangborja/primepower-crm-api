<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\SatisfactionSetting;
use App\Notifications\SatisfactionAtRiskNotification;

class SatisfactionThresholdEngine
{
    private const DEFAULT_THRESHOLD = 3.0;

    private const DEFAULT_LOOKBACK = 1;

    /**
     * Evaluate every client's latest satisfaction score against the low-score
     * threshold and flip the at_risk flag on transitions.
     *
     * @return int number of clients whose at_risk status changed
     */
    public function evaluate(): int
    {
        $threshold = $this->setting('low_score_threshold', self::DEFAULT_THRESHOLD);
        $lookback = (int) $this->setting('at_risk_lookback_surveys', self::DEFAULT_LOOKBACK);

        $changed = 0;

        Client::query()
            ->with(['surveys' => fn ($q) => $q->where('status', 'completed')->orderBy('completed_at', 'desc')])
            ->whereHas('surveys', fn ($q) => $q->where('status', 'completed'))
            ->get()
            ->each(function (Client $client) use ($threshold, $lookback, &$changed) {
                $recent = $client->surveys->take(max(1, $lookback));
                $scores = $recent->map(fn ($s) => (float) $s->average_score)->filter();

                if ($scores->isEmpty()) {
                    return;
                }

                $signal = $scores->avg();
                $isLow = $signal <= $threshold;

                if ($isLow && ! $client->at_risk) {
                    $this->markAtRisk($client, $signal, $threshold);
                    $changed++;
                } elseif (! $isLow && $client->at_risk) {
                    $this->clearAtRisk($client);
                    $changed++;
                }
            });

        return $changed;
    }

    private function markAtRisk(Client $client, float $signal, float $threshold): void
    {
        $reason = sprintf('Latest survey scored %.2f/5 (threshold %.2f)', $signal, $threshold);

        $client->update([
            'at_risk' => true,
            'at_risk_reason' => $reason,
        ]);

        $owner = $client->assignedTo;
        if ($owner) {
            $owner->notify(new SatisfactionAtRiskNotification(
                title: 'Client at risk',
                message: "{$client->company?->name} is at risk: {$reason}.",
                clientId: $client->id,
            ));
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client Satisfaction',
            'action' => 'At Risk',
            'subject_type' => 'Client',
            'subject_id' => (string) $client->id,
            'subject_name' => $client->company?->name ?? "Client #{$client->id}",
            'description' => "Client '{$client->company?->name}' was flagged at risk. {$reason}",
            'metadata' => [
                'client_name' => $client->company?->name,
                'average_score' => round($signal, 2),
                'threshold' => $threshold,
            ],
        ]);
    }

    private function clearAtRisk(Client $client): void
    {
        $client->update([
            'at_risk' => false,
            'at_risk_reason' => null,
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Client Satisfaction',
            'action' => 'At Risk Resolved',
            'subject_type' => 'Client',
            'subject_id' => (string) $client->id,
            'subject_name' => $client->company?->name ?? "Client #{$client->id}",
            'description' => "Client '{$client->company?->name}' is no longer at risk.",
        ]);
    }

    private function setting(string $key, float|int $default): float|int
    {
        $row = SatisfactionSetting::where('key', $key)->first();

        if (! $row) {
            return $default;
        }

        return is_int($default) ? (int) $row->value : (float) $row->value;
    }
}
