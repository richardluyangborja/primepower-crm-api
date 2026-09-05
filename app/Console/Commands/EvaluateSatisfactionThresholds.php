<?php

namespace App\Console\Commands;

use App\Support\SatisfactionThresholdEngine;
use Illuminate\Console\Command;

class EvaluateSatisfactionThresholds extends Command
{
    protected $signature = 'satisfaction:thresholds';

    protected $description = 'Evaluate client satisfaction scores and flag low-scoring clients as at risk.';

    public function handle(SatisfactionThresholdEngine $engine): int
    {
        $changed = $engine->evaluate();

        $this->info("Satisfaction thresholds evaluated; {$changed} client(s) changed at-risk status.");

        return self::SUCCESS;
    }
}
