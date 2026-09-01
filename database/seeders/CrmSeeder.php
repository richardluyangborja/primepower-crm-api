<?php

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationType;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\ReminderPriority;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\Communication;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\StageHistory;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Daniel Balisi',
            'email' => 'daniel@primepower.com',
            'password' => 'password',
        ]);

        $maria = User::factory()->salesRep()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@primepower.com',
            'password' => 'password',
        ]);

        $juan = User::factory()->salesRep()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@primepower.com',
            'password' => 'password',
        ]);

        $carlos = User::factory()->salesRep()->create([
            'name' => 'Carlos Reyes',
            'email' => 'carlos@primepower.com',
            'password' => 'password',
        ]);

        $anna = User::factory()->salesRep()->create([
            'name' => 'Anna Lim',
            'email' => 'anna@primepower.com',
            'password' => 'password',
        ]);

        $miguel = User::factory()->salesRep()->create([
            'name' => 'Miguel Garcia',
            'email' => 'miguel@primepower.com',
            'password' => 'password',
        ]);

        $sofia = User::factory()->salesRep()->create([
            'name' => 'Sofia Mendoza',
            'email' => 'sofia@primepower.com',
            'password' => 'password',
        ]);

        User::factory()->manager()->create([
            'name' => 'Ana Reyes',
            'email' => 'ana@primepower.com',
            'password' => 'password',
        ]);

        $salesReps = [$maria, $juan, $carlos, $anna, $miguel, $sofia];

        $companies = [
            ['name' => 'ABC Manufacturing Corporation', 'industry' => 'Manufacturing', 'address' => 'Quezon City, Metro Manila', 'phone' => '+63 981 235 4500', 'email' => 'info@abcmanufacturing.example', 'website' => 'https://abcmanufacturing.example'],
            ['name' => 'Prime Logistics Solutions', 'industry' => 'Logistics', 'address' => 'Pasig City, Metro Manila', 'phone' => '+63 947 234 5600', 'email' => 'info@primelogistics.example', 'website' => 'https://primelogistics.example'],
            ['name' => 'Golden Foods Incorporated', 'industry' => 'Food & Beverage', 'address' => 'Manila, Metro Manila', 'phone' => '+63 942 856 7800', 'email' => 'info@goldenfoods.example', 'website' => 'https://goldenfoods.example'],
            ['name' => 'Metro Retail Corporation', 'industry' => 'Retail', 'address' => 'Makati City, Metro Manila', 'phone' => '+63 952 845 6700', 'email' => 'info@metroretail.example', 'website' => 'https://metroretail.example'],
            ['name' => 'Pacific Properties Group', 'industry' => 'Real Estate', 'address' => 'Taguig City, Metro Manila', 'phone' => '+63 921 867 8900', 'email' => 'info@pacificproperties.example', 'website' => 'https://pacificproperties.example'],
            ['name' => 'Summit Corporate Solutions', 'industry' => 'Business Services', 'address' => 'Bonifacio Global City, Metro Manila', 'phone' => '+63 917 876 5432', 'email' => 'info@summitcorp.example', 'website' => 'https://summitcorp.example'],
            ['name' => 'TechVenture Philippines', 'industry' => 'Technology', 'address' => 'Makati City, Metro Manila', 'phone' => '+63 918 123 4567', 'email' => 'info@techventure.example', 'website' => 'https://techventure.example'],
            ['name' => 'GreenEnergy Solutions', 'industry' => 'Energy', 'address' => 'Pasig City, Metro Manila', 'phone' => '+63 919 234 5678', 'email' => 'info@greenenergy.example', 'website' => 'https://greenenergy.example'],
            ['name' => 'Manila Health Systems', 'industry' => 'Healthcare', 'address' => 'Manila, Metro Manila', 'phone' => '+63 920 345 6789', 'email' => 'info@manilahealth.example', 'website' => 'https://manilahealth.example'],
            ['name' => 'Apex Financial Group', 'industry' => 'Finance', 'address' => 'Taguig City, Metro Manila', 'phone' => '+63 921 456 7890', 'email' => 'info@apexfinancial.example', 'website' => 'https://apexfinancial.example'],
            ['name' => 'Cebu Shipping Lines', 'industry' => 'Transportation', 'address' => 'Cebu City, Cebu', 'phone' => '+63 922 567 8901', 'email' => 'info@cebushipping.example', 'website' => 'https://cebushipping.example'],
            ['name' => 'Davao Agri Corp', 'industry' => 'Agriculture', 'address' => 'Davao City, Davao del Sur', 'phone' => '+63 923 678 9012', 'email' => 'info@davaoagri.example', 'website' => 'https://davaoagri.example'],
            ['name' => 'Island Resorts Group', 'industry' => 'Hospitality', 'address' => 'Boracay, Aklan', 'phone' => '+63 924 789 0123', 'email' => 'info@islandresorts.example', 'website' => 'https://islandresorts.example'],
            ['name' => 'National Construction Co.', 'industry' => 'Construction', 'address' => 'Quezon City, Metro Manila', 'phone' => '+63 925 890 1234', 'email' => 'info@nationalconstruction.example', 'website' => 'https://nationalconstruction.example'],
            ['name' => 'Philippine Retail Mart', 'industry' => 'Retail', 'address' => 'Mandaluyong City, Metro Manila', 'phone' => '+63 926 901 2345', 'email' => 'info@phretail.example', 'website' => 'https://phretail.example'],
            ['name' => 'Unity Bank Corporation', 'industry' => 'Finance', 'address' => 'Makati City, Metro Manila', 'phone' => '+63 927 012 3456', 'email' => 'info@unitybank.example', 'website' => 'https://unitybank.example'],
            ['name' => 'Wellness Pharma Inc.', 'industry' => 'Pharmaceuticals', 'address' => 'Taguig City, Metro Manila', 'phone' => '+63 928 123 4567', 'email' => 'info@wellnesspharma.example', 'website' => 'https://wellnesspharma.example'],
            ['name' => 'Eastern Telecom', 'industry' => 'Telecommunications', 'address' => 'Pasig City, Metro Manila', 'phone' => '+63 929 234 5678', 'email' => 'info@easterntelecom.example', 'website' => 'https://easterntelecom.example'],
            ['name' => 'Pacific Marine Services', 'industry' => 'Maritime', 'address' => 'Cebu City, Cebu', 'phone' => '+63 930 345 6789', 'email' => 'info@pacificmarine.example', 'website' => 'https://pacificmarine.example'],
            ['name' => 'Metro Security Agency', 'industry' => 'Security Services', 'address' => 'Manila, Metro Manila', 'phone' => '+63 931 456 7890', 'email' => 'info@metroseccurity.example', 'website' => 'https://metroseccurity.example'],
        ];

        $contacts = [
            ['first_name' => 'Robert', 'last_name' => 'Santos', 'title' => 'HR Manager'],
            ['first_name' => 'Michael', 'last_name' => 'Cruz', 'title' => 'HR Director'],
            ['first_name' => 'Daniel', 'last_name' => 'Torres', 'title' => 'HR Supervisor'],
            ['first_name' => 'Patricia', 'last_name' => 'Garcia', 'title' => 'Procurement Manager'],
            ['first_name' => 'Sophia', 'last_name' => 'Mendoza', 'title' => 'Administrative Manager'],
            ['first_name' => 'Olivia', 'last_name' => 'Park', 'title' => 'Finance Director'],
            ['first_name' => 'James', 'last_name' => 'Tan', 'title' => 'CTO'],
            ['first_name' => 'Elena', 'last_name' => 'Cruz', 'title' => 'Operations Director'],
            ['first_name' => 'Richard', 'last_name' => 'Lim', 'title' => 'Medical Director'],
            ['first_name' => 'Jennifer', 'last_name' => 'Ng', 'title' => 'VP Operations'],
            ['first_name' => 'Antonio', 'last_name' => 'Reyes', 'title' => 'Fleet Manager'],
            ['first_name' => 'Maria', 'last_name' => 'Clara', 'title' => 'Plant Manager'],
            ['first_name' => 'George', 'last_name' => 'Santos', 'title' => 'General Manager'],
            ['first_name' => 'Helen', 'last_name' => 'Chua', 'title' => 'Project Director'],
            ['first_name' => 'Peter', 'last_name' => 'Ong', 'title' => 'Operations Manager'],
            ['first_name' => 'Nancy', 'last_name' => 'Tan', 'title' => 'Branch Manager'],
            ['first_name' => 'Charles', 'last_name' => 'Uy', 'title' => 'Compliance Head'],
            ['first_name' => 'Grace', 'last_name' => 'Fernandez', 'title' => 'Network Admin'],
            ['first_name' => 'Rico', 'last_name' => 'Dela Rosa', 'title' => 'Port Captain'],
            ['first_name' => 'Linda', 'last_name' => 'Gonzales', 'title' => 'Security Chief'],
        ];

        $sources = ['Referral', 'Website Inquiry', 'Cold Outreach', 'Social Media', 'Trade Show', 'Email Campaign', 'Partner Referral', 'Advertisement'];

        $opportunityTitles = [
            'Production Line Staffing', 'Warehouse Associate Deployment', 'Packaging Line Support',
            'Seasonal Store Staffing', 'Construction Site Manpower', 'Corporate Office Staffing',
            'IT Support Deployment', 'Solar Installation Crew', 'Medical Staff Augmentation',
            'Financial Audit Support', 'Port Operations Crew', 'Farm Workers Deployment',
            'Hotel Housekeeping Staff', 'Building Maintenance', 'Retail Sales Associates',
            'Bank Tellers Deployment', 'Pharma Distribution', 'Network Operations Center',
            'Marine Crew Staffing', 'Security Personnel Deployment',
        ];

        $companyModels = [];
        $leadModels = [];
        $clientModels = [];

        for ($i = 0; $i < count($companies); $i++) {
            $company = Company::create($companies[$i]);

            $primaryContact = Contact::create(array_merge(
                $contacts[$i],
                ['company_id' => $company->id, 'is_primary' => true, 'email' => strtolower(str_replace(' ', '.', $contacts[$i]['first_name'] . '.' . $contacts[$i]['last_name'])) . '@' . explode('@', $companies[$i]['email'])[1], 'phone' => '+63 ' . rand(917, 999) . ' ' . rand(100, 999) . ' ' . rand(1000, 9999)]
            ));

            Contact::create([
                'company_id' => $company->id,
                'first_name' => 'Secondary',
                'last_name' => 'Contact',
                'title' => 'Assistant',
                'email' => 'secondary@' . explode('@', $companies[$i]['email'])[1],
                'phone' => '+63 ' . rand(917, 999) . ' ' . rand(100, 999) . ' ' . rand(1000, 9999),
                'is_primary' => false,
            ]);

            $assignedTo = $salesReps[array_rand($salesReps)];
            $source = $sources[array_rand($sources)];
            $monthsAgo = rand(0, 5);
            $leadDate = now()->subMonths($monthsAgo)->subDays(rand(0, 27));

            $opportunityStages = [
                OpportunityStage::INITIAL_CONTACT, OpportunityStage::DISCUSSION,
                OpportunityStage::PROPOSAL, OpportunityStage::NEGOTIATION,
                OpportunityStage::CONTRACT_PROCESSING, OpportunityStage::WON,
                OpportunityStage::LOST,
            ];
            $stage = $opportunityStages[array_rand($opportunityStages)];

            $status = match ($stage) {
                OpportunityStage::WON => LeadStatus::CONVERTED,
                OpportunityStage::LOST => LeadStatus::DISQUALIFIED,
                OpportunityStage::CONTRACT_PROCESSING, OpportunityStage::NEGOTIATION => LeadStatus::QUALIFIED,
                default => rand(0, 1) ? LeadStatus::NEW : LeadStatus::QUALIFIED,
            };

            $lead = Lead::create([
                'company_id' => $company->id,
                'assigned_to_id' => $assignedTo->id,
                'source' => $source,
                'status' => $status,
                'notes' => "Initial contact via {$source}. Assigned to {$assignedTo->name}.",
                'created_at' => $leadDate,
            ]);

            $companyModels[] = $company;
            $leadModels[] = $lead;

            $this->createStatusHistories($lead, $assignedTo->id, $status, $source);

            $client = null;
            if ($status === LeadStatus::CONVERTED) {
                $client = Client::create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'assigned_to_id' => $assignedTo->id,
                    'status' => rand(0, 1) ? ClientStatus::ACTIVE : ClientStatus::INACTIVE,
                    'client_since' => now()->subMonths(rand(1, 12))->toDateString(),
                    'notes' => 'Converted from lead after successful opportunity win.',
                ]);
                $clientModels[] = $client;

                $this->createSurveys($client);
            }

            $oppDate = now()->subMonths($monthsAgo)->subDays(rand(0, 27));

            $opportunity = Opportunity::create([
                'company_id' => $company->id,
                'lead_id' => $lead->id,
                'client_id' => $client?->id,
                'assigned_to_id' => $assignedTo->id,
                'title' => $opportunityTitles[$i],
                'description' => 'Manpower services for ' . strtolower($company->industry) . ' operations.',
                'stage' => $stage,
                'manpower_requirement' => rand(10, 100),
                'estimated_contract_value' => rand(30, 500) * 1000,
                'expected_close_date' => now()->subMonths(rand(-3, 6))->toDateString(),
                'lost_reason' => $stage === OpportunityStage::LOST ? 'Lost to competitor pricing.' : null,
                'created_at' => $oppDate,
                'updated_at' => $stage === OpportunityStage::WON ? $oppDate : now(),
            ]);

            $this->createStageHistoriesForOpportunity($opportunity, $assignedTo->id, $stage);
        }

        for ($i = 0; $i < count($companyModels); $i++) {
            $company = $companyModels[$i];
            $lead = $leadModels[$i];
            $assignedTo = $lead->assignedTo;
            $numComms = rand(2, 5);

            for ($j = 0; $j < $numComms; $j++) {
                $type = CommunicationType::cases()[array_rand(CommunicationType::cases())];
                $direction = CommunicationDirection::cases()[array_rand(CommunicationDirection::cases())];

                Communication::create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'user_id' => $assignedTo->id,
                    'contact_id' => $company->contacts->first()?->id,
                    'type' => $type,
                    'direction' => $direction,
                    'subject' => "Follow-up on {$opportunityTitles[$i]}",
                    'notes' => 'Discussion about requirements and next steps.',
                    'duration_minutes' => $type === CommunicationType::EMAIL || $type === CommunicationType::TEXT ? null : rand(15, 90),
                    'scheduled_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now()->subDays(rand(1, 60)),
                ]);
            }

            $numReminders = rand(1, 4);
            for ($j = 0; $j < $numReminders; $j++) {
                $isCompleted = rand(0, 1);
                Reminder::create([
                    'company_id' => $company->id,
                    'related_to_type' => 'lead',
                    'related_to_id' => $lead->id,
                    'title' => ['Follow up on proposal', 'Schedule meeting', 'Send updated quote', 'Confirm requirements'][$j % 4],
                    'description' => 'Action required for ' . $company->name,
                    'due_date' => now()->subDays(rand(-7, 14)),
                    'priority' => ReminderPriority::cases()[array_rand(ReminderPriority::cases())],
                    'is_completed' => $isCompleted,
                    'completed_at' => $isCompleted ? now()->subDays(rand(1, 10)) : null,
                    'assigned_to_name' => $assignedTo->name,
                    'created_at' => now()->subDays(rand(5, 20)),
                ]);
            }
        }
    }

    private function createStatusHistories(Lead $lead, int $userId, LeadStatus $finalStatus, string $source): void
    {
        $histories = [[LeadStatus::NEW, "Initial contact via {$source}.", now()->subDays(rand(30, 60))]];

        if (in_array($finalStatus, [LeadStatus::QUALIFIED, LeadStatus::CONVERTED])) {
            $histories[] = [LeadStatus::QUALIFIED, 'Requirements confirmed. Budget approved.', now()->subDays(rand(15, 29))];
        }

        if ($finalStatus === LeadStatus::CONVERTED) {
            $histories[] = [LeadStatus::CONVERTED, 'Contract signed. Converted to client.', now()->subDays(rand(1, 14))];
        }

        if ($finalStatus === LeadStatus::DISQUALIFIED) {
            $histories[] = [LeadStatus::DISQUALIFIED, 'No budget or timeline mismatch.', now()->subDays(rand(10, 20))];
        }

        $previousStatus = null;
        foreach ($histories as [$status, $reason, $createdAt]) {
            StatusHistory::create([
                'lead_id' => $lead->id,
                'user_id' => $userId,
                'from_status' => $previousStatus,
                'to_status' => $status->value,
                'reason' => $reason,
                'created_at' => $createdAt,
            ]);
            $previousStatus = $status->value;
        }
    }

    private function createStageHistoriesForOpportunity(Opportunity $opportunity, int $userId, OpportunityStage $finalStage): void
    {
        $allStages = [
            OpportunityStage::INITIAL_CONTACT,
            OpportunityStage::DISCUSSION,
            OpportunityStage::PROPOSAL,
            OpportunityStage::NEGOTIATION,
            OpportunityStage::CONTRACT_PROCESSING,
            OpportunityStage::WON,
        ];

        $stageIndex = array_search($finalStage, $allStages);
        if ($stageIndex === false) {
            $stageIndex = count($allStages) - 1;
        }

        $previousStage = null;
        $daysAgo = 60;

        for ($i = 0; $i <= $stageIndex; $i++) {
            $stage = $allStages[$i];
            $reason = match ($stage) {
                OpportunityStage::INITIAL_CONTACT => 'Initial outreach and discovery.',
                OpportunityStage::DISCUSSION => 'Requirements gathering completed.',
                OpportunityStage::PROPOSAL => 'Formal proposal submitted.',
                OpportunityStage::NEGOTIATION => 'Terms and pricing negotiated.',
                OpportunityStage::CONTRACT_PROCESSING => 'Contract under legal review.',
                OpportunityStage::WON => 'Contract signed. Opportunity won.',
                default => 'Stage updated.',
            };

            StageHistory::create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $userId,
                'from_stage' => $previousStage,
                'to_stage' => $stage->value,
                'reason' => $reason,
                'created_at' => now()->subDays($daysAgo),
            ]);

            $previousStage = $stage->value;
            $daysAgo = max(1, $daysAgo - rand(8, 12));
        }
    }

    private function createSurveys(Client $client): void
    {
        $numSurveys = rand(1, 3);
        $scores = [];

        for ($i = 0; $i < $numSurveys; $i++) {
            $isCompleted = rand(0, 1) || $i === 0;
            $surveyScores = [rand(3, 5), rand(3, 5), rand(2, 5), rand(3, 5), rand(4, 5)];
            $avgScore = $isCompleted ? round(array_sum($surveyScores) / count($surveyScores), 1) : null;

            ClientSurvey::create([
                'client_id' => $client->id,
                'token' => 'srv_' . str()->random(10),
                'status' => $isCompleted ? 'completed' : (rand(0, 1) ? 'pending' : 'expired'),
                'responses' => $isCompleted ? array_map(fn($score, $idx) => ['question_id' => 'q' . ($idx + 1), 'score' => $score], $surveyScores, array_keys($surveyScores)) : null,
                'average_score' => $avgScore,
                'completed_at' => $isCompleted ? now()->subDays(rand(5, 60)) : null,
            ]);

            if ($avgScore !== null) {
                $scores[] = $avgScore;
            }
        }
    }
}
