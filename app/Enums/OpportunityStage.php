<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case INITIAL_CONTACT = 'initial_contact';
    case DISCUSSION = 'discussion';
    case PROPOSAL = 'proposal';
    case NEGOTIATION = 'negotiation';
    case CONTRACT_PROCESSING = 'contract_processing';
    case WON = 'won';
    case LOST = 'lost';

    public function validTransitions(): array
    {
        return match ($this) {
            self::INITIAL_CONTACT => [
                self::DISCUSSION,
                self::LOST,
            ],
            self::DISCUSSION => [
                self::PROPOSAL,
                self::LOST,
            ],
            self::PROPOSAL => [
                self::NEGOTIATION,
                self::LOST,
            ],
            self::NEGOTIATION => [
                self::CONTRACT_PROCESSING,
                self::LOST,
            ],
            self::CONTRACT_PROCESSING => [
                self::LOST,
            ],
            self::WON, self::LOST => [],
        };
    }
}
