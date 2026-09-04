<?php

namespace App\Console\Commands;

use App\Support\EscalationEngine;
use Illuminate\Console\Command;

class EvaluateEscalations extends Command
{
    protected $signature = 'escalations:evaluate';

    protected $description = 'Evaluate escalation rules and fire configured actions.';

    public function handle(EscalationEngine $engine): int
    {
        $fired = $engine->evaluate();

        $this->info("Escalation engine evaluated; {$fired} action(s) fired.");

        return self::SUCCESS;
    }
}
