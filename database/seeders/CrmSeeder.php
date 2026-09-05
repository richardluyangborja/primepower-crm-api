<?php

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Enums\ClientSurveyStatus;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationOutcome;
use App\Enums\CommunicationType;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\ReminderPriority;
use App\Enums\UserRole;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CrmSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::create([
            'name' => 'Daniel Balisi',
            'email' => 'daniel@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $manager = User::create([
            'name' => 'Ana Reyes',
            'email' => 'ana@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::MANAGER,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $maria = User::create([
            'name' => 'Maria Santos',
            'email' => 'maria@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SALES_REP,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $juan = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SALES_REP,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $carlos = User::create([
            'name' => 'Carlos Reyes',
            'email' => 'carlos@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SALES_REP,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $anna = User::create([
            'name' => 'Anna Lim',
            'email' => 'anna@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SALES_REP,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $miguel = User::create([
            'name' => 'Miguel Garcia',
            'email' => 'miguel@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SALES_REP,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $sofia = User::create([
            'name' => 'Sofia Mendoza',
            'email' => 'sofia@primepower.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SALES_REP,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $salesReps = [$maria, $juan, $carlos, $anna, $miguel, $sofia];

        $manager->update(['manager_id' => null]);

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

        $stages = [
            OpportunityStage::INITIAL_CONTACT,
            OpportunityStage::DISCUSSION,
            OpportunityStage::PROPOSAL,
            OpportunityStage::NEGOTIATION,
            OpportunityStage::CONTRACT_PROCESSING,
            OpportunityStage::WON,
            OpportunityStage::LOST,
            OpportunityStage::INITIAL_CONTACT,
            OpportunityStage::DISCUSSION,
            OpportunityStage::PROPOSAL,
            OpportunityStage::NEGOTIATION,
            OpportunityStage::CONTRACT_PROCESSING,
            OpportunityStage::WON,
            OpportunityStage::LOST,
            OpportunityStage::INITIAL_CONTACT,
            OpportunityStage::DISCUSSION,
            OpportunityStage::PROPOSAL,
            OpportunityStage::NEGOTIATION,
            OpportunityStage::CONTRACT_PROCESSING,
            OpportunityStage::LOST,
        ];

        $leadStatuses = [
            LeadStatus::NEW,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::CONVERTED,
            LeadStatus::DISQUALIFIED,
            LeadStatus::NEW,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::CONVERTED,
            LeadStatus::DISQUALIFIED,
            LeadStatus::NEW,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::QUALIFIED,
            LeadStatus::DISQUALIFIED,
        ];

        $companyModels = [];
        $leadModels = [];
        $clientModels = [];

        foreach ($companies as $i => $companyData) {
            $company = Company::create($companyData);

            $contactFirst = $contacts[$i];
            $emailDomain = explode('@', $companyData['email'])[1];
            $phoneSuffix = '917 '.(100 + $i).' '.(1000 + $i * 7);

            $primaryContact = Contact::create(array_merge(
                $contactFirst,
                [
                    'company_id' => $company->id,
                    'is_primary' => true,
                    'email' => strtolower(str_replace(' ', '.', $contactFirst['first_name'].'.'.$contactFirst['last_name'])).'@'.$emailDomain,
                    'phone' => '+63 '.$phoneSuffix,
                ]
            ));

            Contact::create([
                'company_id' => $company->id,
                'first_name' => 'Secondary',
                'last_name' => 'Contact',
                'title' => 'Assistant',
                'email' => 'secondary@'.$emailDomain,
                'phone' => '+63 918 '.(100 + $i).' '.(1000 + $i * 13),
                'is_primary' => false,
            ]);

            $assignedTo = $salesReps[$i % count($salesReps)];
            $source = $sources[$i % count($sources)];
            $stage = $stages[$i];
            $status = $leadStatuses[$i];

            $leadDate = now()->subDays(60 - $i * 2);
            $oppDate = now()->subDays(50 - $i * 2);

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

            $this->createStatusHistories($lead, $assignedTo->id, $status, $source, $leadDate);

            $client = null;
            if ($status === LeadStatus::CONVERTED) {
                $client = Client::create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'assigned_to_id' => $assignedTo->id,
                    'status' => ClientStatus::ACTIVE,
                    'client_since' => now()->subMonths(1 + $i)->toDateString(),
                    'notes' => 'Converted from lead after successful opportunity win.',
                ]);
                $clientModels[] = $client;

                $this->createSurveys($client);
            }

            $opportunity = Opportunity::create([
                'company_id' => $company->id,
                'lead_id' => $lead->id,
                'client_id' => $client?->id,
                'assigned_to_id' => $assignedTo->id,
                'title' => $opportunityTitles[$i],
                'description' => 'Manpower services for '.strtolower($companyData['industry']).' operations.',
                'stage' => $stage,
                'manpower_requirement' => 10 + $i * 4,
                'estimated_contract_value' => (300 + $i * 50) * 1000,
                'expected_close_date' => now()->subMonths(-1 + $i)->toDateString(),
                'lost_reason' => $stage === OpportunityStage::LOST ? 'Lost to competitor pricing.' : null,
                'created_at' => $oppDate,
                'updated_at' => in_array($stage, [OpportunityStage::WON, OpportunityStage::LOST]) ? $oppDate : now(),
            ]);

            $this->createStageHistoriesForOpportunity($opportunity, $assignedTo->id, $stage, $oppDate);
        }

        foreach ($companyModels as $i => $company) {
            $lead = $leadModels[$i];
            $assignedTo = $lead->assignedTo;

            $commTypes = [CommunicationType::EMAIL, CommunicationType::PHONE, CommunicationType::MEETING];
            $commDirections = [CommunicationDirection::OUTGOING, CommunicationDirection::INCOMING, CommunicationDirection::OUTGOING];
            $commSubjects = ['Follow-up on proposal', 'Schedule meeting', 'Send updated quote'];
            $commDays = [30, 20, 10];
            $commOutcomes = [CommunicationOutcome::INTERESTED, CommunicationOutcome::MEETING_BOOKED, CommunicationOutcome::NO_RESPONSE];

            foreach ($commTypes as $j => $type) {
                Communication::create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'user_id' => $assignedTo->id,
                    'contact_id' => $company->contacts->first()?->id,
                    'type' => $type,
                    'direction' => $commDirections[$j],
                    'subject' => $commSubjects[$j],
                    'notes' => 'Discussion about requirements and next steps.',
                    'outcome' => $commOutcomes[$j]->value,
                    'duration_minutes' => $type === CommunicationType::EMAIL || $type === CommunicationType::TEXT ? null : 30 + $j * 20,
                    'scheduled_at' => now()->subDays($commDays[$j]),
                    'created_at' => now()->subDays($commDays[$j] + 5),
                ]);
            }

            $reminderTitles = ['Follow up on proposal', 'Schedule meeting'];
            $reminderPriorities = [ReminderPriority::HIGH, ReminderPriority::MEDIUM];
            $reminderDays = [14, 7];
            $recurrenceRules = ['weekly', null];

            foreach ($reminderTitles as $j => $title) {
                Reminder::create([
                    'company_id' => $company->id,
                    'related_to_type' => 'lead',
                    'related_to_id' => $lead->id,
                    'title' => $title,
                    'description' => 'Action required for '.$company->name,
                    'due_date' => now()->addDays($reminderDays[$j]),
                    'priority' => $reminderPriorities[$j],
                    'status' => 'pending',
                    'is_completed' => false,
                    'completed_at' => null,
                    'assigned_to_name' => $assignedTo->name,
                    'user_id' => $assignedTo->id,
                    'recurrence_rule' => $recurrenceRules[$j],
                    'recurrence_parent_id' => null,
                    'created_at' => now()->subDays(20 + $j * 5),
                ]);
            }

            $dueReminder = Reminder::create([
                'company_id' => $company->id,
                'related_to_type' => 'lead',
                'related_to_id' => $lead->id,
                'title' => 'Urgent follow-up',
                'description' => 'Overdue action for '.$company->name,
                'due_date' => now()->subDay(),
                'priority' => ReminderPriority::HIGH,
                'status' => 'pending',
                'is_completed' => false,
                'completed_at' => null,
                'assigned_to_name' => $assignedTo->name,
                'user_id' => $assignedTo->id,
                'recurrence_rule' => null,
                'recurrence_parent_id' => null,
                'created_at' => now()->subDays(5),
            ]);

            $assignedTo->notify(new \App\Notifications\ReminderDueNotification($dueReminder));
        }
    }

    private function createStatusHistories(Lead $lead, int $userId, LeadStatus $finalStatus, string $source, \DateTimeInterface $leadDate): void
    {
        $histories = [[LeadStatus::NEW, "Initial contact via {$source}.", $leadDate]];

        if (in_array($finalStatus, [LeadStatus::QUALIFIED, LeadStatus::CONVERTED])) {
            $histories[] = [LeadStatus::QUALIFIED, 'Requirements confirmed. Budget approved.', now()->subDays(15)];
        }

        if ($finalStatus === LeadStatus::CONVERTED) {
            $histories[] = [LeadStatus::CONVERTED, 'Contract signed. Converted to client.', now()->subDays(7)];
        }

        if ($finalStatus === LeadStatus::DISQUALIFIED) {
            $histories[] = [LeadStatus::DISQUALIFIED, 'No budget or timeline mismatch.', now()->subDays(10)];
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

    private function createStageHistoriesForOpportunity(Opportunity $opportunity, int $userId, OpportunityStage $finalStage, \DateTimeInterface $oppDate): void
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
                'created_at' => $oppDate->copy()->subDays($daysAgo),
            ]);

            $previousStage = $stage->value;
            $daysAgo = max(1, $daysAgo - 10);
        }
    }

    private function createSurveys(Client $client): void
    {
        $surveyScores = [4, 5, 3, 4, 5];
        $avgScore = round(array_sum($surveyScores) / count($surveyScores), 1);

        ClientSurvey::create([
            'client_id' => $client->id,
            'token' => 'srv_'.Str::random(10),
            'status' => ClientSurveyStatus::COMPLETED,
            'responses' => array_map(fn ($score, $idx) => ['question_id' => 'q'.($idx + 1), 'score' => $score], $surveyScores, array_keys($surveyScores)),
            'average_score' => $avgScore,
            'completed_at' => now()->subDays(10),
        ]);
    }
}
