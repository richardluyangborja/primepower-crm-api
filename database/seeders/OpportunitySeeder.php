<?php

namespace Database\Seeders;

use App\Enums\OpportunityStage;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\StageHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $maria = User::where('email', 'maria@primepower.com')->firstOrFail();
        $juan = User::where('email', 'juan@primepower.com')->firstOrFail();

        $abc = Company::where(
            'name',
            'ABC Manufacturing Corporation'
        )->firstOrFail();

        $prime = Company::where(
            'name',
            'Prime Logistics Solutions'
        )->firstOrFail();

        $metro = Company::where(
            'name',
            'Metro Retail Corporation'
        )->firstOrFail();

        $abcLead = Lead::where('company_id', $abc->id)->firstOrFail();
        $primeLead = Lead::where('company_id', $prime->id)->firstOrFail();

        $metroClient = Client::where(
            'company_id',
            $metro->id
        )->firstOrFail();

        $abcOpp = Opportunity::create([
            'company_id' => $abc->id,
            'lead_id' => $abcLead->id,
            'assigned_to_id' => $maria->id,
            'title' => 'Manufacturing Workforce Contract',
            'description' => 'Manpower requirements for production operations.',
            'stage' => OpportunityStage::NEGOTIATION,
            'manpower_requirement' => 50,
            'estimated_contract_value' => 1250000,
            'expected_close_date' => now()->addDays(20),
        ]);

        StageHistory::create([
            'opportunity_id' => $abcOpp->id,
            'user_id' => $maria->id,
            'from_stage' => null,
            'to_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'reason' => 'Initial inquiry from production manager.',
            'created_at' => now()->subDays(60),
        ]);

        StageHistory::create([
            'opportunity_id' => $abcOpp->id,
            'user_id' => $maria->id,
            'from_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'to_stage' => OpportunityStage::DISCUSSION->value,
            'reason' => 'First meeting scheduled with HR and operations.',
            'created_at' => now()->subDays(45),
        ]);

        StageHistory::create([
            'opportunity_id' => $abcOpp->id,
            'user_id' => $maria->id,
            'from_stage' => OpportunityStage::DISCUSSION->value,
            'to_stage' => OpportunityStage::PROPOSAL->value,
            'reason' => 'Client requested formal proposal for 50 workers.',
            'created_at' => now()->subDays(30),
        ]);

        StageHistory::create([
            'opportunity_id' => $abcOpp->id,
            'user_id' => $maria->id,
            'from_stage' => OpportunityStage::PROPOSAL->value,
            'to_stage' => OpportunityStage::NEGOTIATION->value,
            'reason' => 'Client reviewing contract terms with legal team.',
            'created_at' => now()->subDays(15),
        ]);

        $primeOpp = Opportunity::create([
            'company_id' => $prime->id,
            'lead_id' => $primeLead->id,
            'assigned_to_id' => $juan->id,
            'title' => 'Warehouse Staffing Services',
            'description' => 'Initial staffing opportunity for warehouse operations.',
            'stage' => OpportunityStage::DISCUSSION,
            'manpower_requirement' => 30,
            'estimated_contract_value' => 750000,
            'expected_close_date' => now()->addDays(35),
        ]);

        StageHistory::create([
            'opportunity_id' => $primeOpp->id,
            'user_id' => $juan->id,
            'from_stage' => null,
            'to_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'reason' => 'New lead captured from website inquiry.',
            'created_at' => now()->subDays(10),
        ]);

        StageHistory::create([
            'opportunity_id' => $primeOpp->id,
            'user_id' => $juan->id,
            'from_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'to_stage' => OpportunityStage::DISCUSSION->value,
            'reason' => 'Initial call completed, client interested.',
            'created_at' => now()->subDays(5),
        ]);

        $metroAdditionalOpp = Opportunity::create([
            'company_id' => $metro->id,
            'client_id' => $metroClient->id,
            'assigned_to_id' => $juan->id,
            'title' => 'Additional Store Staffing',
            'description' => 'Additional manpower requirement for new retail locations.',
            'stage' => OpportunityStage::PROPOSAL,
            'manpower_requirement' => 20,
            'estimated_contract_value' => 500000,
            'expected_close_date' => now()->addDays(15),
        ]);

        StageHistory::create([
            'opportunity_id' => $metroAdditionalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => null,
            'to_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'reason' => 'Existing client requested additional staffing.',
            'created_at' => now()->subDays(40),
        ]);

        StageHistory::create([
            'opportunity_id' => $metroAdditionalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'to_stage' => OpportunityStage::DISCUSSION->value,
            'reason' => 'Meeting held to discuss store locations and timelines.',
            'created_at' => now()->subDays(25),
        ]);

        StageHistory::create([
            'opportunity_id' => $metroAdditionalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => OpportunityStage::DISCUSSION->value,
            'to_stage' => OpportunityStage::PROPOSAL->value,
            'reason' => 'Formal proposal submitted for 20 additional staff.',
            'created_at' => now()->subDays(15),
        ]);

        $metroSeasonalOpp = Opportunity::create([
            'company_id' => $metro->id,
            'client_id' => $metroClient->id,
            'assigned_to_id' => $juan->id,
            'title' => 'Seasonal Staffing',
            'description' => 'Seasonal manpower requirement.',
            'stage' => OpportunityStage::LOST,
            'manpower_requirement' => 15,
            'estimated_contract_value' => 300000,
            'expected_close_date' => now()->subDays(10),
            'lost_reason' => 'Client postponed the requirement.',
        ]);

        StageHistory::create([
            'opportunity_id' => $metroSeasonalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => null,
            'to_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'reason' => 'Client reached out for seasonal staffing needs.',
            'created_at' => now()->subMonths(3),
        ]);

        StageHistory::create([
            'opportunity_id' => $metroSeasonalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => OpportunityStage::INITIAL_CONTACT->value,
            'to_stage' => OpportunityStage::DISCUSSION->value,
            'reason' => 'Initial requirements gathering call.',
            'created_at' => now()->subMonths(2)->addDays(25),
        ]);

        StageHistory::create([
            'opportunity_id' => $metroSeasonalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => OpportunityStage::DISCUSSION->value,
            'to_stage' => OpportunityStage::PROPOSAL->value,
            'reason' => 'Proposal submitted for 15 seasonal staff.',
            'created_at' => now()->subMonths(2),
        ]);

        StageHistory::create([
            'opportunity_id' => $metroSeasonalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => OpportunityStage::PROPOSAL->value,
            'to_stage' => OpportunityStage::NEGOTIATION->value,
            'reason' => 'Client requested revised pricing.',
            'created_at' => now()->subMonths(1)->addDays(20),
        ]);

        StageHistory::create([
            'opportunity_id' => $metroSeasonalOpp->id,
            'user_id' => $juan->id,
            'from_stage' => OpportunityStage::NEGOTIATION->value,
            'to_stage' => OpportunityStage::LOST->value,
            'reason' => 'Client postponed the requirement indefinitely.',
            'created_at' => now()->subDays(12),
        ]);
    }
}
