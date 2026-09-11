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
use App\Notifications\ReminderDueNotification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CrmSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Days between consecutive stage transitions in a pinned timeline. */
    private const STAGE_SPAN_DAYS = 12;

    public function run(): void
    {
        // ── Users ────────────────────────────────────────────────
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

        $salesReps = collect([
            ['name' => 'Maria Santos', 'email' => 'maria@primepower.com'],
            ['name' => 'Juan Dela Cruz', 'email' => 'juan@primepower.com'],
            ['name' => 'Carlos Reyes', 'email' => 'carlos@primepower.com'],
            ['name' => 'Anna Lim', 'email' => 'anna@primepower.com'],
            ['name' => 'Miguel Garcia', 'email' => 'miguel@primepower.com'],
            ['name' => 'Sofia Mendoza', 'email' => 'sofia@primepower.com'],
        ])->map(fn (array $data) => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'role' => UserRole::SALES_REP,
            'is_active' => true,
            'email_verified_at' => now(),
        ]))->all();

        // ── Companies ────────────────────────────────────────────
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
            ['name' => 'Island Resorts Group', 'industry' => 'Hospitality', 'address' => 'Boracay, Aklan', 'phone' => '+63 924 789 0123', 'email' => 'info@islandresorts.example', 'website' => 'https://islandresorts.example'],
            ['name' => 'National Construction Co.', 'industry' => 'Construction', 'address' => 'Quezon City, Metro Manila', 'phone' => '+63 925 890 1234', 'email' => 'info@nationalconstruction.example', 'website' => 'https://nationalconstruction.example'],
            ['name' => 'Philippine Retail Mart', 'industry' => 'Retail', 'address' => 'Mandaluyong City, Metro Manila', 'phone' => '+63 926 901 2345', 'email' => 'info@phretail.example', 'website' => 'https://phretail.example'],
            ['name' => 'Unity Bank Corporation', 'industry' => 'Finance', 'address' => 'Makati City, Metro Manila', 'phone' => '+63 927 012 3456', 'email' => 'info@unitybank.example', 'website' => 'https://unitybank.example'],
            ['name' => 'Wellness Pharma Inc.', 'industry' => 'Pharmaceuticals', 'address' => 'Taguig City, Metro Manila', 'phone' => '+63 928 123 4567', 'email' => 'info@wellnesspharma.example', 'website' => 'https://wellnesspharma.example'],
            ['name' => 'Eastern Telecom', 'industry' => 'Telecommunications', 'address' => 'Pasig City, Metro Manila', 'phone' => '+63 929 234 5678', 'email' => 'info@easterntelecom.example', 'website' => 'https://easterntelecom.example'],
            ['name' => 'Pacific Marine Services', 'industry' => 'Maritime', 'address' => 'Cebu City, Cebu', 'phone' => '+63 930 345 6789', 'email' => 'info@pacificmarine.example', 'website' => 'https://pacificmarine.example'],
            ['name' => 'Metro Security Agency', 'industry' => 'Security Services', 'address' => 'Manila, Metro Manila', 'phone' => '+63 931 456 7890', 'email' => 'info@metrosecurity.example', 'website' => 'https://metrosecurity.example'],
            ['name' => 'GreenHarvest Farms', 'industry' => 'Agriculture', 'address' => 'Batangas City, Batangas', 'phone' => '+63 932 567 8901', 'email' => 'info@greenharvest.example', 'website' => 'https://greenharvest.example'],
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
            'Bank Tellers Deployment', 'Network Operations Center', 'Marine Crew Staffing',
            'Security Personnel Deployment', 'Field Harvest Workforce',
        ];

        // ── Lead data ────────────────────────────────────────────
        // [status, created_days_ago, qualified_days_ago, converted/disqualified_days_ago]
        // Every date spans Jan 2026 → now.
        // Indices 0-12: converted to clients (13)
        // Indices 13-15: disqualified after a lost deal (3)
        // Indices 16-17: qualified with active deals (2)
        // Index 18: new lead (1)
        // Index 19: qualified with a stalled deal without close date (1)
        $leadData = [
            [LeadStatus::CONVERTED, 240, 215, 185],    // 0  Jan
            [LeadStatus::CONVERTED, 225, 200, 170],    // 1  Jan
            [LeadStatus::CONVERTED, 205, 180, 150],    // 2  Feb
            [LeadStatus::CONVERTED, 190, 165, 135],    // 3  Feb
            [LeadStatus::CONVERTED, 170, 145, 115],    // 4  Mar
            [LeadStatus::CONVERTED, 150, 125, 95],     // 5  Apr
            [LeadStatus::CONVERTED, 135, 110, 80],     // 6  Apr
            [LeadStatus::CONVERTED, 115, 90, 60],      // 7  May
            [LeadStatus::CONVERTED, 95, 70, 40],       // 8  Jun
            [LeadStatus::CONVERTED, 80, 55, 25],       // 9  Jun
            [LeadStatus::CONVERTED, 150, 130, 120],    // 10 Cebu Shipping — client 120d
            [LeadStatus::CONVERTED, 130, 110, 100],    // 11 Island Resorts — client 100d
            [LeadStatus::CONVERTED, 110, 90, 80],      // 12 National Construction — client 80d
            [LeadStatus::DISQUALIFIED, 220, 190, 160], // 13 lost deal → disqualified
            [LeadStatus::DISQUALIFIED, 160, 130, 100], // 14 lost deal → disqualified
            [LeadStatus::DISQUALIFIED, 110, 80, 50],   // 15 lost deal → disqualified
            [LeadStatus::QUALIFIED, 60, 40, null],     // 16 negotiated deal, closing soon
            [LeadStatus::QUALIFIED, 40, 25, null],     // 17 proposal, closing soon
            [LeadStatus::NEW, 10, null, null],          // 18 fresh inquiry
            [LeadStatus::QUALIFIED, 30, 20, null],     // 19 stalled discussion, no close date
        ];

        // ── Opportunity stages (one per lead) ────────────────────
        // Converted leads 0-12 → WON (the deal that won them)
        // Disqualified 13-15 → LOST (deal fell through → lead disqualified)
        // Qualified/new 16-19 → open deals exercising stalled/closing/missing-date rules
        $oppStages = [
            OpportunityStage::WON,               // 0
            OpportunityStage::WON,               // 1
            OpportunityStage::WON,               // 2
            OpportunityStage::WON,               // 3
            OpportunityStage::WON,               // 4
            OpportunityStage::WON,               // 5
            OpportunityStage::WON,               // 6
            OpportunityStage::WON,               // 7
            OpportunityStage::WON,               // 8
            OpportunityStage::WON,               // 9
            OpportunityStage::WON,               // 10
            OpportunityStage::WON,               // 11
            OpportunityStage::WON,               // 12
            OpportunityStage::LOST,              // 13
            OpportunityStage::LOST,              // 14
            OpportunityStage::LOST,              // 15
            OpportunityStage::NEGOTIATION,       // 16 — closing soon
            OpportunityStage::PROPOSAL,          // 17 — closing soon
            OpportunityStage::INITIAL_CONTACT,   // 18 — fresh
            OpportunityStage::DISCUSSION,        // 19 — stalled, no close date
        ];

        // ── Survey score patterns per client (index = company index) ──
        // Every client has exactly 3 completed surveys; two also have a pending one.
        $surveyPatterns = [
            // Improving: 2.3 → 3.0 → 4.0
            0 => [
                ['scores' => [2, 2, 3, 2], 'days_ago' => 175, 'feedback' => 'Response time was slow initially.'],
                ['scores' => [3, 3, 3, 3], 'days_ago' => 120, 'feedback' => 'Better communication, still room for improvement.'],
                ['scores' => [4, 4, 4, 4], 'days_ago' => 45, 'feedback' => 'Much better service quality.'],
            ],
            // Consistently high: 4.5 → 4.8 → 4.8
            1 => [
                ['scores' => [4, 5, 4, 5], 'days_ago' => 160, 'feedback' => 'Excellent team, very responsive.'],
                ['scores' => [5, 5, 5, 4], 'days_ago' => 95, 'feedback' => 'Outstanding performance this quarter.'],
                ['scores' => [4, 5, 5, 5], 'days_ago' => 28, 'feedback' => 'Consistently excellent service.'],
            ],
            // Declining: 4.8 → 4.0 → 3.3  (at-risk)
            2 => [
                ['scores' => [5, 5, 4, 5], 'days_ago' => 145, 'feedback' => 'Great start, very professional.'],
                ['scores' => [4, 4, 4, 4], 'days_ago' => 85, 'feedback' => 'Service quality dropped slightly.'],
                ['scores' => [3, 3, 3, 4], 'days_ago' => 18, 'feedback' => 'Noticed decline in response times.'],
            ],
            // Low and flat: 2.3 → 2.3 → 1.8
            3 => [
                ['scores' => [2, 3, 2, 2], 'days_ago' => 128, 'feedback' => 'Issues with staffing consistency.'],
                ['scores' => [2, 2, 2, 3], 'days_ago' => 62, 'feedback' => 'Still experiencing problems.'],
                ['scores' => [2, 2, 1, 2], 'days_ago' => 8, 'feedback' => 'Very disappointed with recent performance.'],
            ],
            // Moderate improving: 3.0 → 3.3 → 3.8
            4 => [
                ['scores' => [3, 3, 3, 3], 'days_ago' => 105, 'feedback' => 'Average service, meets expectations.'],
                ['scores' => [3, 4, 3, 3], 'days_ago' => 45, 'feedback' => 'Some improvements noted.'],
                ['scores' => [4, 4, 4, 3], 'days_ago' => 4, 'feedback' => 'Good progress, keep it up.'],
            ],
            // High with a dip: 4.3 → 3.0 → 4.3
            5 => [
                ['scores' => [4, 4, 5, 4], 'days_ago' => 90, 'feedback' => 'Very satisfied with the team.'],
                ['scores' => [3, 3, 3, 3], 'days_ago' => 40, 'feedback' => 'Some issues this month.'],
                ['scores' => [4, 4, 4, 5], 'days_ago' => 2, 'feedback' => 'Issues resolved, back to normal.'],
            ],
            // Declining: 4.3 → 3.3 → 2.3  (at-risk)
            6 => [
                ['scores' => [4, 5, 4, 4], 'days_ago' => 75, 'feedback' => 'Happy with initial deployment.'],
                ['scores' => [3, 3, 4, 3], 'days_ago' => 30, 'feedback' => 'Quality has been inconsistent.'],
                ['scores' => [2, 2, 3, 2], 'days_ago' => 4, 'feedback' => 'Significant drop in service quality.'],
            ],
            // Stable high: 4.3 → 4.3 → 4.5
            7 => [
                ['scores' => [4, 4, 5, 4], 'days_ago' => 55, 'feedback' => 'Reliable service delivery.'],
                ['scores' => [4, 5, 4, 4], 'days_ago' => 22, 'feedback' => 'Good ongoing support.'],
                ['scores' => [5, 4, 4, 5], 'days_ago' => 1, 'feedback' => 'Excellent ongoing support.'],
            ],
            // Low improving: 2.3 → 3.0 → 3.3
            8 => [
                ['scores' => [2, 2, 2, 3], 'days_ago' => 38, 'feedback' => 'Initial teething problems.'],
                ['scores' => [3, 3, 3, 3], 'days_ago' => 12, 'feedback' => 'Things are getting better.'],
                ['scores' => [3, 4, 3, 3], 'days_ago' => 1, 'feedback' => 'Steady improvement.'],
            ],
            // Volatile: 5.0 → 2.3 → 3.8
            9 => [
                ['scores' => [5, 5, 5, 5], 'days_ago' => 25, 'feedback' => 'Perfect service initially.'],
                ['scores' => [2, 2, 3, 2], 'days_ago' => 9, 'feedback' => 'Major issues last month.'],
                ['scores' => [4, 4, 4, 3], 'days_ago' => 1, 'feedback' => 'Recovery after escalation.'],
            ],
            // Improving over a short tenure: 3.3 → 3.8 → 4.0
            10 => [
                ['scores' => [3, 3, 4, 3], 'days_ago' => 40, 'feedback' => 'Decent start.'],
                ['scores' => [3, 4, 4, 4], 'days_ago' => 18, 'feedback' => 'Improving communication.'],
                ['scores' => [4, 4, 4, 4], 'days_ago' => 2, 'feedback' => 'Stronger service now.'],
            ],
            // Stable good: 3.8 → 4.3 → 4.3
            11 => [
                ['scores' => [4, 4, 3, 4], 'days_ago' => 28, 'feedback' => 'Promising beginning.'],
                ['scores' => [4, 5, 4, 4], 'days_ago' => 12, 'feedback' => 'Consistency improving.'],
                ['scores' => [4, 4, 5, 4], 'days_ago' => 2, 'feedback' => 'Settled into a good rhythm.'],
            ],
            // Moderate improving: 2.8 → 3.3 → 3.8
            12 => [
                ['scores' => [2, 3, 3, 3], 'days_ago' => 50, 'feedback' => 'Early implementation issues.'],
                ['scores' => [3, 3, 4, 3], 'days_ago' => 24, 'feedback' => 'Working through kinks.'],
                ['scores' => [3, 4, 4, 4], 'days_ago' => 3, 'feedback' => 'Much smoother now.'],
            ],
        ];

        // Clients flagged at risk (declining satisfaction).
        $atRiskClients = [
            2 => 'Declining satisfaction across consecutive surveys.',
            6 => 'Satisfaction dropping — latest survey below 3.0.',
        ];

        // Clients with a survey sent but not yet completed.
        $pendingSurveyClients = [
            10 => ['days_ago' => 1, 'scores' => [null]],
            11 => ['days_ago' => 1, 'scores' => [null]],
        ];

        $companyModels = [];
        $leadModels = [];
        $clientModels = [];

        // ── Create companies, contacts, leads, clients, opportunities ──
        foreach ($companies as $i => $companyData) {
            $company = Company::create($companyData);

            $contactFirst = $contacts[$i];
            $emailDomain = explode('@', $companyData['email'])[1];
            $phoneSuffix = '917 '.(100 + $i).' '.(1000 + $i * 7);

            Contact::create(array_merge(
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
            [$status, $createdDaysAgo, $qualifiedDaysAgo, $resolvedDaysAgo] = $leadData[$i];

            $lead = Lead::create([
                'company_id' => $company->id,
                'assigned_to_id' => $assignedTo->id,
                'source' => $source,
                'status' => $status,
                'notes' => "Initial contact via {$source}. Assigned to {$assignedTo->name}.",
                'created_at' => now()->subDays($createdDaysAgo),
            ]);

            $companyModels[] = $company;
            $leadModels[] = $lead;

            $this->createStatusHistories(
                $lead,
                $assignedTo->id,
                $status,
                $source,
                $createdDaysAgo,
                $qualifiedDaysAgo,
                $resolvedDaysAgo
            );

            $client = null;
            if ($status === LeadStatus::CONVERTED) {
                $client = Client::create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'assigned_to_id' => $assignedTo->id,
                    'status' => ClientStatus::ACTIVE,
                    'client_since' => now()->subDays($resolvedDaysAgo)->toDateString(),
                    'notes' => 'Converted from lead after successful opportunity win.',
                    'at_risk' => array_key_exists($i, $atRiskClients),
                    'at_risk_reason' => $atRiskClients[$i] ?? null,
                ]);
                $clientModels[] = $client;

                foreach ($surveyPatterns[$i] as $pattern) {
                    $this->createSurvey($client, $pattern);
                }

                if (array_key_exists($i, $pendingSurveyClients)) {
                    $this->createPendingSurvey($client, $pendingSurveyClients[$i]['days_ago']);
                }
            }

            // ── Opportunity ──────────────────────────────────────
            $oppStage = $oppStages[$i];

            // Days since the deal entered its CURRENT stage. Only meaningful
            // for open deals — it drives the "stalled" rule (>= StalledDays).
            // Pinned so the data exercises every suggestion type.
            $currentStageDaysAgo = match (true) {
                $oppStage === OpportunityStage::WON || $oppStage === OpportunityStage::LOST => null,
                $i === 16 => 25,   // NEGOTIATION — stalled + closing soon
                $i === 17 => 20,   // PROPOSAL — stalled + closing soon
                $i === 18 => 1,    // INITIAL_CONTACT — fresh, no flags
                default => 18,     // DISCUSSION — stalled, no close date
            };

            // Opportunity created_at sits at the oldest stage transition.
            $oppCreatedDaysAgo = $currentStageDaysAgo !== null
                ? $currentStageDaysAgo + $this->stageIndex($oppStage) * self::STAGE_SPAN_DAYS
                : $createdDaysAgo - 10;

            $expectedCloseDate = match (true) {
                $oppStage === OpportunityStage::WON => now()->subDays($resolvedDaysAgo - 5),
                $oppStage === OpportunityStage::LOST => now()->subDays(45 + $i * 3),
                // Closing-soon deals: explicit dates inside the 30-day window
                $i === 16 => now()->addDays(12),
                $i === 17 => now()->addDays(18),
                // Fresh deal: over a month out
                $i === 18 => now()->addDays(45),
                // Stalled deal with no close date set
                default => null,
            };

            $opp = Opportunity::create([
                'company_id' => $company->id,
                'lead_id' => $lead->id,
                'client_id' => $client?->id,
                'assigned_to_id' => $assignedTo->id,
                'title' => $opportunityTitles[$i],
                'description' => 'Manpower services for '.strtolower($companyData['industry']).' operations.',
                'stage' => $oppStage,
                'manpower_requirement' => 10 + $i * 4,
                'estimated_contract_value' => (300 + $i * 50) * 1000,
                'expected_close_date' => $expectedCloseDate?->toDateString(),
                'lost_reason' => $oppStage === OpportunityStage::LOST ? 'Lost to competitor pricing.' : null,
                'created_at' => now()->subDays($oppCreatedDaysAgo),
                'updated_at' => in_array($oppStage, [OpportunityStage::WON, OpportunityStage::LOST])
                    ? now()->subDays($resolvedDaysAgo)
                    : now(),
            ]);

            $this->createStageHistoriesForOpportunity($opp, $assignedTo->id, $oppStage, $oppCreatedDaysAgo, $currentStageDaysAgo);
        }

        // ── Communications: 2-4 per company, spread across year ──
        foreach ($companyModels as $i => $company) {
            $lead = $leadModels[$i];
            $assignedTo = $lead->assignedTo;
            $contactId = $company->contacts->first()?->id;
            [$status, $createdDaysAgo] = $leadData[$i];

            $commData = [
                [
                    'type' => CommunicationType::EMAIL,
                    'direction' => CommunicationDirection::OUTGOING,
                    'subject' => 'Initial outreach',
                    'outcome' => CommunicationOutcome::INTERESTED,
                    'days_ago' => $createdDaysAgo - 5,
                    'duration' => null,
                ],
                [
                    'type' => CommunicationType::PHONE,
                    'direction' => CommunicationDirection::OUTGOING,
                    'subject' => 'Follow-up call',
                    'outcome' => CommunicationOutcome::MEETING_BOOKED,
                    'days_ago' => max(1, $createdDaysAgo - 25),
                    'duration' => 25,
                ],
                [
                    'type' => CommunicationType::MEETING,
                    'direction' => CommunicationDirection::OUTGOING,
                    'subject' => 'Requirements discussion',
                    'outcome' => CommunicationOutcome::INTERESTED,
                    'days_ago' => max(1, $createdDaysAgo - 40),
                    'duration' => 60,
                ],
                [
                    'type' => CommunicationType::EMAIL,
                    'direction' => CommunicationDirection::INCOMING,
                    'subject' => 'Client response',
                    'outcome' => CommunicationOutcome::NO_RESPONSE,
                    'days_ago' => max(1, $createdDaysAgo - 55),
                    'duration' => null,
                ],
            ];

            $commCount = 2 + ($i % 3);

            for ($j = 0; $j < $commCount; $j++) {
                $cd = $commData[$j];
                Communication::create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'user_id' => $assignedTo->id,
                    'contact_id' => $contactId,
                    'type' => $cd['type'],
                    'direction' => $cd['direction'],
                    'subject' => $cd['subject'],
                    'notes' => 'Discussion about requirements and next steps.',
                    'outcome' => $cd['outcome']->value,
                    'duration_minutes' => $cd['duration'],
                    'scheduled_at' => now()->subDays($cd['days_ago']),
                    'created_at' => now()->subDays($cd['days_ago'] + 2),
                ]);
            }
        }

        // ── Reminders: mix of completed, overdue, upcoming, recurring ──
        foreach ($companyModels as $i => $company) {
            $lead = $leadModels[$i];
            $assignedTo = $lead->assignedTo;
            [$status, $createdDaysAgo] = $leadData[$i];
            $relatedId = $status === LeadStatus::CONVERTED && $lead->client ? $lead->client->id : $lead->id;
            $relatedType = $status === LeadStatus::CONVERTED && $lead->client ? 'client' : 'lead';

            // Completed reminder from the past
            Reminder::create([
                'company_id' => $company->id,
                'related_to_type' => $relatedType,
                'related_to_id' => $relatedId,
                'title' => 'Initial consultation',
                'description' => 'First meeting with '.$company->name,
                'due_date' => now()->subDays(max(1, $createdDaysAgo - 25)),
                'priority' => ReminderPriority::MEDIUM,
                'status' => 'completed',
                'is_completed' => true,
                'completed_at' => now()->subDays(max(1, $createdDaysAgo - 30)),
                'assigned_to_name' => $assignedTo->name,
                'user_id' => $assignedTo->id,
                'recurrence_rule' => null,
                'recurrence_parent_id' => null,
                'created_at' => now()->subDays(max(1, $createdDaysAgo - 20)),
            ]);

            // Overdue for roughly a quarter of companies
            if ($i % 4 === 0) {
                Reminder::create([
                    'company_id' => $company->id,
                    'related_to_type' => $relatedType,
                    'related_to_id' => $relatedId,
                    'title' => 'Follow up on pending proposal',
                    'description' => 'Overdue follow-up for '.$company->name,
                    'due_date' => now()->subDays(2 + ($i % 5)),
                    'priority' => ReminderPriority::HIGH,
                    'status' => 'pending',
                    'is_completed' => false,
                    'completed_at' => null,
                    'assigned_to_name' => $assignedTo->name,
                    'user_id' => $assignedTo->id,
                    'recurrence_rule' => null,
                    'recurrence_parent_id' => null,
                    'created_at' => now()->subDays(18),
                ]);
            }

            // Upcoming for a third of companies
            if ($i % 3 === 0) {
                Reminder::create([
                    'company_id' => $company->id,
                    'related_to_type' => $relatedType,
                    'related_to_id' => $relatedId,
                    'title' => 'Quarterly review meeting',
                    'description' => 'Schedule quarterly review with '.$company->name,
                    'due_date' => now()->addDays(5 + $i * 2),
                    'priority' => ReminderPriority::MEDIUM,
                    'status' => 'pending',
                    'is_completed' => false,
                    'completed_at' => null,
                    'assigned_to_name' => $assignedTo->name,
                    'user_id' => $assignedTo->id,
                    'recurrence_rule' => null,
                    'recurrence_parent_id' => null,
                    'created_at' => now()->subDays(3),
                ]);
            }

            // Recurring weekly check-in for converted clients
            if ($status === LeadStatus::CONVERTED) {
                $dueReminder = Reminder::create([
                    'company_id' => $company->id,
                    'related_to_type' => 'client',
                    'related_to_id' => $lead->client->id,
                    'title' => 'Weekly status check',
                    'description' => 'Regular status check-in for '.$company->name,
                    'due_date' => now()->addDay(),
                    'priority' => ReminderPriority::LOW,
                    'status' => 'pending',
                    'is_completed' => false,
                    'completed_at' => null,
                    'assigned_to_name' => $assignedTo->name,
                    'user_id' => $assignedTo->id,
                    'recurrence_rule' => 'weekly',
                    'recurrence_parent_id' => null,
                    'created_at' => now()->subDays(12),
                ]);

                // Notify a few users so the due-refreshed flow has data
                if ($i < 3) {
                    $assignedTo->notify(new ReminderDueNotification($dueReminder));
                }
            }
        }
    }

    private function createStatusHistories(
        Lead $lead,
        int $userId,
        LeadStatus $finalStatus,
        string $source,
        int $createdDaysAgo,
        ?int $qualifiedDaysAgo,
        ?int $resolvedDaysAgo
    ): void {
        $histories = [[LeadStatus::NEW, "Initial contact via {$source}.", now()->subDays($createdDaysAgo)]];

        if ($qualifiedDaysAgo !== null) {
            $histories[] = [LeadStatus::QUALIFIED, 'Requirements confirmed. Budget approved.', now()->subDays($qualifiedDaysAgo)];
        }

        if ($finalStatus === LeadStatus::CONVERTED && $resolvedDaysAgo !== null) {
            $histories[] = [LeadStatus::CONVERTED, 'Contract signed. Converted to client.', now()->subDays($resolvedDaysAgo)];
        }

        if ($finalStatus === LeadStatus::DISQUALIFIED && $resolvedDaysAgo !== null) {
            $histories[] = [LeadStatus::DISQUALIFIED, 'No budget or timeline alignment after negotiation.', now()->subDays($resolvedDaysAgo)];
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

    private const STAGE_ORDER = [
        OpportunityStage::INITIAL_CONTACT,
        OpportunityStage::DISCUSSION,
        OpportunityStage::PROPOSAL,
        OpportunityStage::NEGOTIATION,
        OpportunityStage::CONTRACT_PROCESSING,
        OpportunityStage::WON,
    ];

    private function createStageHistoriesForOpportunity(Opportunity $opportunity, int $userId, OpportunityStage $finalStage, int $oppCreatedDaysAgo, ?int $currentStageDaysAgo): void
    {
        if ($currentStageDaysAgo !== null) {
            // Open deals: pin the current-stage entry so "stalled" rules fire.
            $this->createPinnedStageChain($opportunity, $userId, $finalStage, $currentStageDaysAgo);

            return;
        }

        if ($finalStage === OpportunityStage::LOST) {
            // Walk the deal to negotiation, then lose it.
            $this->createEvenStageChain($opportunity, $userId, OpportunityStage::NEGOTIATION, $oppCreatedDaysAgo);
            $this->createStageEntry($opportunity, $userId, OpportunityStage::NEGOTIATION->value, OpportunityStage::LOST, 'Lost to competitor pricing.', now()->subDays(max(1, $oppCreatedDaysAgo - 10)));

            return;
        }

        $this->createEvenStageChain($opportunity, $userId, $finalStage, $oppCreatedDaysAgo);
    }

    private function createPinnedStageChain(Opportunity $opportunity, int $userId, OpportunityStage $finalStage, int $currentStageDaysAgo): void
    {
        $stageIndex = $this->stageIndex($finalStage);
        $previousStage = null;

        for ($i = 0; $i <= $stageIndex; $i++) {
            $stage = self::STAGE_ORDER[$i];
            $daysAgo = $currentStageDaysAgo + ($stageIndex - $i) * self::STAGE_SPAN_DAYS;

            $this->createStageEntry(
                $opportunity,
                $userId,
                $previousStage,
                $stage,
                $this->stageReason($stage),
                now()->subDays($daysAgo)
            );

            $previousStage = $stage->value;
        }
    }

    private function createEvenStageChain(Opportunity $opportunity, int $userId, OpportunityStage $finalStage, int $oppCreatedDaysAgo): void
    {
        $stageIndex = $this->stageIndex($finalStage);
        $previousStage = null;
        $daysBetweenStages = (int) ceil($oppCreatedDaysAgo / max(1, $stageIndex + 1));

        for ($i = 0; $i <= $stageIndex; $i++) {
            $stage = self::STAGE_ORDER[$i];

            $this->createStageEntry(
                $opportunity,
                $userId,
                $previousStage,
                $stage,
                $this->stageReason($stage),
                now()->subDays($oppCreatedDaysAgo - ($i * $daysBetweenStages))
            );

            $previousStage = $stage->value;
        }
    }

    private function stageIndex(OpportunityStage $stage): int
    {
        $index = array_search($stage, self::STAGE_ORDER, true);

        return $index === false ? 0 : $index;
    }

    private function stageReason(OpportunityStage $stage): string
    {
        return match ($stage) {
            OpportunityStage::INITIAL_CONTACT => 'Initial outreach and discovery.',
            OpportunityStage::DISCUSSION => 'Requirements gathering completed.',
            OpportunityStage::PROPOSAL => 'Formal proposal submitted.',
            OpportunityStage::NEGOTIATION => 'Terms and pricing negotiated.',
            OpportunityStage::CONTRACT_PROCESSING => 'Contract under legal review.',
            OpportunityStage::WON => 'Contract signed. Opportunity won.',
            default => 'Stage updated.',
        };
    }

    private function createStageEntry(Opportunity $opportunity, int $userId, ?string $fromStage, OpportunityStage $toStage, string $reason, \DateTimeInterface $createdAt): void
    {
        StageHistory::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $userId,
            'from_stage' => $fromStage,
            'to_stage' => $toStage->value,
            'reason' => $reason,
            'created_at' => $createdAt,
        ]);
    }

    private function createSurvey(Client $client, array $pattern): void
    {
        $scores = $pattern['scores'];
        $avgScore = round(array_sum($scores) / count($scores), 1);

        ClientSurvey::create([
            'client_id' => $client->id,
            'token' => 'srv_'.Str::random(10),
            'status' => ClientSurveyStatus::COMPLETED,
            'responses' => array_map(
                fn ($score, $idx) => ['question_id' => 'q'.($idx + 1), 'score' => $score],
                $scores,
                array_keys($scores)
            ),
            'average_score' => $avgScore,
            'completed_at' => now()->subDays($pattern['days_ago']),
            'feedback' => $pattern['feedback'] ?? null,
        ]);
    }

    private function createPendingSurvey(Client $client, int $daysAgo): void
    {
        ClientSurvey::create([
            'client_id' => $client->id,
            'token' => 'srv_'.Str::random(10),
            'status' => ClientSurveyStatus::PENDING,
            'responses' => null,
            'average_score' => null,
            'completed_at' => null,
            'feedback' => null,
            'created_at' => now()->subDays($daysAgo),
        ]);
    }
}
