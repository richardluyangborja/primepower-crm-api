<?php

use App\Enums\ClientSurveyStatus;
use App\Enums\UserRole;
use App\Models\ClientSurvey;
use App\Models\Communication;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardCompany(string $suffix = ''): Company
{
    return Company::create([
        'name' => 'Dashboard Co '.$suffix.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0600',
        'email' => 'dashco.'.uniqid().'@test.com',
        'website' => null,
    ]);
}

it('serves the dashboard to every role without error', function () {
    $admin = User::factory()->admin()->create(['email' => 'dash-admin-'.uniqid().'@example.com']);
    $manager = User::factory()->manager()->create(['email' => 'dash-mgr-'.uniqid().'@example.com']);
    $rep = User::factory()->salesRep()->create(['email' => 'dash-rep-'.uniqid().'@example.com']);

    $company = dashboardCompany();

    Lead::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'source' => 'Test',
        'status' => 'new',
        'notes' => null,
    ]);

    foreach ([$admin, $manager, $rep] as $user) {
        $this->actingAs($user)->getJson('/api/dashboard')->assertOk();
    }
});

it('gives a manager the same dashboard totals as an admin', function () {
    $admin = User::factory()->admin()->create(['email' => 'dash-admin-b-'.uniqid().'@example.com']);
    $manager = User::factory()->manager()->create(['email' => 'dash-mgr-b-'.uniqid().'@example.com']);
    $repA = User::factory()->salesRep()->create(['email' => 'dash-rep-a-'.uniqid().'@example.com']);
    $repB = User::factory()->salesRep()->create(['email' => 'dash-rep-b-'.uniqid().'@example.com']);

    $companyA = dashboardCompany('a');
    $leadA = Lead::create([
        'company_id' => $companyA->id,
        'assigned_to_id' => $repA->id,
        'source' => 'Test',
        'status' => 'new',
        'notes' => null,
    ]);

    $clientA = $companyA->client()->create([
        'assigned_to_id' => $repA->id,
        'status' => 'active',
        'client_since' => now(),
        'notes' => null,
    ]);

    Opportunity::create([
        'company_id' => $companyA->id,
        'lead_id' => $leadA->id,
        'client_id' => $clientA->id,
        'assigned_to_id' => $repA->id,
        'title' => 'Deal A',
        'stage' => 'won',
        'estimated_contract_value' => 100000,
    ]);

    Reminder::create([
        'company_id' => $companyA->id,
        'related_to_type' => 'lead',
        'related_to_id' => $leadA->id,
        'title' => 'Follow up',
        'due_date' => now()->addDay()->toDateString(),
        'priority' => 'high',
        'status' => 'pending',
        'is_completed' => false,
        'user_id' => $repA->id,
    ]);

    Communication::create([
        'company_id' => $companyA->id,
        'lead_id' => $leadA->id,
        'user_id' => $repA->id,
        'type' => 'email',
        'direction' => 'outgoing',
        'subject' => 'Intro',
    ]);

    $companyB = dashboardCompany('b');
    Lead::create([
        'company_id' => $companyB->id,
        'assigned_to_id' => $repB->id,
        'source' => 'Test',
        'status' => 'qualified',
        'notes' => null,
    ]);

    $adminSummary = $this->actingAs($admin)->getJson('/api/dashboard')->assertOk()->json('data.summary');
    $managerSummary = $this->actingAs($manager)->getJson('/api/dashboard')->assertOk()->json('data.summary');

    expect($managerSummary['total_leads'])->toBe($adminSummary['total_leads'])
        ->and($managerSummary['total_clients'])->toBe($adminSummary['total_clients'])
        ->and($managerSummary['total_opportunities'])->toBe($adminSummary['total_opportunities'])
        ->and($managerSummary['won_opportunities'])->toBe($adminSummary['won_opportunities'])
        ->and($managerSummary['active_reminders'])->toBe($adminSummary['active_reminders']);
});

it('scopes the sales rep dashboard to their own records', function () {
    $rep = User::factory()->salesRep()->create(['email' => 'dash-rep-own-'.uniqid().'@example.com']);
    $other = User::factory()->salesRep()->create(['email' => 'dash-rep-other-'.uniqid().'@example.com']);

    Lead::create([
        'company_id' => dashboardCompany('own')->id,
        'assigned_to_id' => $rep->id,
        'source' => 'Test',
        'status' => 'new',
        'notes' => null,
    ]);

    Lead::create([
        'company_id' => dashboardCompany('other')->id,
        'assigned_to_id' => $other->id,
        'source' => 'Test',
        'status' => 'new',
        'notes' => null,
    ]);

    $response = $this->actingAs($rep)->getJson('/api/dashboard')->assertOk();

    expect($response->json('data.summary.total_leads'))->toBe(1)
        ->and($response->json('data.scope.role'))->toBe(UserRole::SALES_REP->value);
});

it('returns pipeline trend, win/loss, and satisfaction analytics series on the dashboard', function () {
    $admin = User::factory()->admin()->create(['email' => 'dash-series-admin-'.uniqid().'@example.com']);
    $rep = User::factory()->salesRep()->create(['email' => 'dash-series-rep-'.uniqid().'@example.com']);

    $company = dashboardCompany('series');
    $lead = Lead::create([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'source' => 'Test',
        'status' => 'new',
        'notes' => null,
    ]);
    $client = $company->client()->create([
        'assigned_to_id' => $rep->id,
        'status' => 'active',
        'client_since' => now(),
        'notes' => null,
    ]);

    $openDeal = Opportunity::create([
        'company_id' => $company->id,
        'lead_id' => $lead->id,
        'client_id' => $client->id,
        'assigned_to_id' => $rep->id,
        'title' => 'Open Deal',
        'stage' => 'proposal',
        'estimated_contract_value' => 50000,
    ]);
    $openDeal->created_at = now()->subMonth();
    $openDeal->save();

    Opportunity::create([
        'company_id' => $company->id,
        'lead_id' => $lead->id,
        'client_id' => $client->id,
        'assigned_to_id' => $rep->id,
        'title' => 'Won Deal',
        'stage' => 'won',
        'estimated_contract_value' => 150000,
    ]);

    ClientSurvey::create([
        'client_id' => $client->id,
        'token' => 'srv_'.uniqid(),
        'status' => ClientSurveyStatus::COMPLETED,
        'responses' => [
            ['question_id' => 'q1', 'score' => 5],
            ['question_id' => 'q2', 'score' => 4],
            ['question_id' => 'q3', 'score' => 3],
        ],
        'average_score' => 4.0,
        'completed_at' => now()->subWeek(),
    ]);

    $data = $this->actingAs($admin)->getJson('/api/dashboard')->assertOk()->json('data');

    // Opportunity pipeline analytics.
    $trendMonths = collect($data['opportunities']['trend'])->keyBy('month');
    $thisMonth = now()->format('Y-m');
    expect($trendMonths->has($thisMonth))->toBeTrue()
        ->and($trendMonths->get($thisMonth)['count'])->toBe(1)
        ->and($data['opportunities']['win_loss'])->toHaveCount(2);
    $won = collect($data['opportunities']['win_loss'])->firstWhere('stage', 'won');
    expect($won['count'])->toBe(1)
        ->and((float) $won['value'])->toBe(150000.0);

    // Satisfaction analytics.
    expect($data['satisfaction']['by_question'])->toHaveCount(3)
        ->and($data['satisfaction']['by_question'][0]['question'])->toBe('q1')
        ->and((float) $data['satisfaction']['by_question'][0]['average_score'])->toBe(5.0)
        ->and($data['satisfaction']['trend'])->not->toBeEmpty();

    // Per-rep performance is visible to admin/manager.
    $repRow = collect($data['performance'])->firstWhere('rep_id', $rep->id);
    expect($repRow['open_opportunities'])->toBe(1)
        ->and((float) $repRow['pipeline_value'])->toBe(50000.0)
        ->and($repRow['won_count'])->toBe(1)
        ->and((float) $repRow['win_rate'])->toBe(100.0)
        ->and((float) $repRow['average_score'])->toBe(4.0)
        ->and((float) $repRow['response_rate'])->toBe(100.0);

    // A sales rep sees only their own single performance row.
    $salesData = $this->actingAs($rep)->getJson('/api/dashboard')->assertOk()->json('data');
    expect($salesData['performance'])->toHaveCount(1)
        ->and($salesData['performance'][0]['rep_id'])->toBe($rep->id)
        ->and($salesData['performance'][0]['open_opportunities'])->toBe(1)
        ->and((float) $salesData['performance'][0]['pipeline_value'])->toBe(50000.0)
        ->and($salesData['performance'][0]['won_count'])->toBe(1)
        ->and((float) $salesData['performance'][0]['win_rate'])->toBe(100.0)
        ->and((float) $salesData['performance'][0]['average_score'])->toBe(4.0)
        ->and((float) $salesData['performance'][0]['response_rate'])->toBe(100.0);
});
