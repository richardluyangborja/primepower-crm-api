<?php

use App\Enums\ClientStatus;
use App\Enums\ClientSurveyStatus;
use App\Models\Client;
use App\Models\ClientSurvey;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\StageHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email' => 'analytics-admin@example.com']);

    Http::preventStrayRequests();
});

it('only allows admins to view the AI status endpoint', function () {
    $rep = User::factory()->salesRep()->create();

    $this->actingAs($rep)
        ->getJson('/api/analytics/status')
        ->assertForbidden();
});

it('reports unconfigured when no API key is set', function () {
    config(['ai.api_key' => null]);

    $this->actingAs($this->admin)
        ->getJson('/api/analytics/status')
        ->assertStatus(422)
        ->assertJson(['configured' => false]);
});

it('reports reachable when Gemini responds', function () {
    config(['ai.api_key' => 'test-key']);

    Http::fake([
        config('ai.api_url').'chat/completions' => Http::response([
            'id' => 'chatcmpl-1',
            'model' => config('ai.model'),
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'OK'],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/analytics/status')
        ->assertOk()
        ->assertJson(['configured' => true, 'reachable' => true])
        ->assertJsonPath('reply', 'OK');

    Http::assertSent(fn ($request) => $request->url() === config('ai.api_url').'chat/completions');
});

it('reports unreachable when Gemini errors', function () {
    config(['ai.api_key' => 'test-key']);

    Http::fake([
        config('ai.api_url').'chat/completions' => Http::response([
            'error' => ['message' => 'invalid api key'],
        ], 401),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/analytics/status')
        ->assertStatus(502)
        ->assertJson(['configured' => true, 'reachable' => false]);
});

// ---------------------------------------------------------------------------
// Opportunity pipeline analytics
// ---------------------------------------------------------------------------

function makeOpportunityForAnalytics(array $attributes = [], array $repAttributes = []): Opportunity
{
    $company = Company::create([
        'name' => 'AI Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0400',
        'email' => 'ai.co.'.uniqid().'@test.com',
    ]);

    $rep = User::factory()->salesRep()->create(array_merge(['email' => 'ai.rep.'.uniqid().'@test.com'], $repAttributes));

    return Opportunity::create(array_merge([
        'company_id' => $company->id,
        'assigned_to_id' => $rep->id,
        'title' => 'Deal '.uniqid(),
        'stage' => 'discussion',
        'estimated_contract_value' => 100000,
        'expected_close_date' => now()->addDays(10)->toDateString(),
    ], $attributes));
}

it('gives managers access to every opportunity in the analytics', function () {
    $manager = User::factory()->manager()->create(['email' => 'ai.manager@example.com']);

    makeOpportunityForAnalytics();
    makeOpportunityForAnalytics(['stage' => 'proposal', 'estimated_contract_value' => 250000]);

    $this->actingAs($manager)
        ->getJson('/api/analytics/opportunities')
        ->assertOk()
        ->assertJsonPath('data.metrics.total_open', 2)
        ->assertJsonPath('data.metrics.total_value', 350000);
});

it('scopes opportunity analytics to the requesting sales rep', function () {
    $myDeal = makeOpportunityForAnalytics();
    makeOpportunityForAnalytics(['stage' => 'proposal', 'estimated_contract_value' => 250000]);

    $this->actingAs(User::find($myDeal->assigned_to_id))
        ->getJson('/api/analytics/opportunities')
        ->assertOk()
        ->assertJsonPath('data.metrics.total_open', 1)
        ->assertJsonPath('data.metrics.total_value', 100000);
});

it('returns an empty analysis without any HTTP calls when the pipeline is empty', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/analytics/opportunities')
        ->assertOk()
        ->assertJsonPath('data.empty', true)
        ->assertJsonPath('data.metrics.total_open', 0)
        ->assertJsonPath('data.suggested_actions', []);

    Http::assertNothingSent();
});

it('reports quantitative metrics and data-driven suggested actions without any AI call', function () {
    makeOpportunityForAnalytics();
    makeOpportunityForAnalytics(['stage' => 'proposal', 'estimated_contract_value' => 250000]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/analytics/opportunities')
        ->assertOk()
        ->assertJsonPath('data.empty', false)
        ->assertJsonPath('data.metrics.total_open', 2)
        ->assertJsonPath('data.metrics.total_value', 350000)
        ->assertJsonPath('data.metrics.weighted_value', 150000)
        ->assertJsonPath('data.metrics.per_stage.0.label', 'Discussion')
        ->assertJsonPath('data.suggested_actions.0.type', 'closing');

    $this->assertStringStartsWith('Reach out to close', $response->json('data.suggested_actions.0.suggest'));

    Http::assertNothingSent();
});

it('suggests a follow-up action for a stalled deal', function () {
    $deal = makeOpportunityForAnalytics(['stage' => 'proposal']);
    $history = StageHistory::create([
        'opportunity_id' => $deal->id,
        'user_id' => $deal->assigned_to_id,
        'from_stage' => 'discussion',
        'to_stage' => 'proposal',
        'reason' => 'Moved to proposal',
    ]);
    $history->created_at = now()->subDays(20);
    $history->save();

    $this->actingAs($this->admin)
        ->getJson('/api/analytics/opportunities')
        ->assertOk()
        ->assertJsonPath('data.metrics.stalled.0.days', 20)
        ->assertJsonPath('data.suggested_actions.0.type', 'stalled');
});

// ---------------------------------------------------------------------------
// Satisfaction analytics
// ---------------------------------------------------------------------------

function makeClientForAnalytics(User $owner, array $overrides = []): Client
{
    $company = Company::create([
        'name' => 'Sat Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0600',
        'email' => 'sat.co.'.uniqid().'@test.com',
    ]);

    return $company->client()->create(array_merge([
        'assigned_to_id' => $owner->id,
        'status' => ClientStatus::ACTIVE->value,
        'client_since' => now(),
    ], $overrides));
}

function makeSurveyForAnalytics(Client $client, array $overrides = []): ClientSurvey
{
    $survey = new ClientSurvey;
    $survey->forceFill(array_merge([
        'client_id' => $client->id,
        'token' => 'srv_'.bin2hex(random_bytes(16)),
        'status' => ClientSurveyStatus::COMPLETED->value,
        'average_score' => 4.5,
        'responses' => [['question_id' => 'q1', 'score' => 5]],
        'completed_at' => now(),
    ], $overrides));
    $survey->save();

    return $survey;
}

it('scopes satisfaction analytics to the requesting sales rep', function () {
    $rep = User::factory()->salesRep()->create(['email' => 'sat.rep.'.uniqid().'@test.com']);
    $other = User::factory()->salesRep()->create(['email' => 'sat.other.'.uniqid().'@test.com']);

    makeSurveyForAnalytics(makeClientForAnalytics($rep), ['average_score' => 2.5]);
    makeSurveyForAnalytics(makeClientForAnalytics($other), ['average_score' => 5.0]);

    $this->actingAs($rep)
        ->getJson('/api/analytics/satisfaction')
        ->assertOk()
        ->assertJsonPath('data.metrics.clients', 1)
        ->assertJsonPath('data.metrics.surveys_sent', 1)
        ->assertJsonPath('data.metrics.average_score', 2.5);

    Http::assertNothingSent();
});

it('reports satisfaction metrics with score bands and response rate', function () {
    $rep = User::factory()->salesRep()->create(['email' => 'sat.rep.'.uniqid().'@test.com']);

    $client = makeClientForAnalytics($rep);
    makeSurveyForAnalytics($client, ['average_score' => 4.5]);
    makeSurveyForAnalytics($client, [
        'status' => ClientSurveyStatus::PENDING->value,
        'average_score' => null,
        'responses' => null,
        'completed_at' => null,
    ]);

    $low = makeClientForAnalytics($rep);
    makeSurveyForAnalytics($low, ['average_score' => 2.0]);

    $this->actingAs($this->admin)
        ->getJson('/api/analytics/satisfaction')
        ->assertOk()
        ->assertJsonPath('data.metrics.clients', 2)
        ->assertJsonPath('data.metrics.surveys_sent', 3)
        ->assertJsonPath('data.metrics.surveys_completed', 2)
        ->assertJsonPath('data.metrics.pending_count', 1)
        ->assertJsonPath('data.metrics.response_rate', 66.7)
        ->assertJsonPath('data.metrics.average_score', 3.3)
        ->assertJsonPath('data.metrics.score_bands.0.band', 'High')
        ->assertJsonPath('data.metrics.score_bands.0.count', 1)
        ->assertJsonPath('data.metrics.score_bands.1.band', 'Medium')
        ->assertJsonPath('data.metrics.score_bands.1.count', 0)
        ->assertJsonPath('data.metrics.score_bands.2.band', 'Low')
        ->assertJsonPath('data.metrics.score_bands.2.count', 1)
        ->assertJsonPath('data.metrics.by_owner.0.owner', $rep->name)
        ->assertJsonPath('data.metrics.by_owner.0.count', 2);
});

it('flags at-risk and low-score clients and returns data-driven suggested actions', function () {
    $rep = User::factory()->salesRep()->create(['email' => 'sat.rep.'.uniqid().'@test.com']);

    $client = makeClientForAnalytics($rep, [
        'at_risk' => true,
        'at_risk_reason' => 'Two consecutive low surveys',
    ]);
    makeSurveyForAnalytics($client, ['average_score' => 2.5]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/analytics/satisfaction')
        ->assertOk()
        ->assertJsonPath('data.metrics.at_risk_count', 1)
        ->assertJsonPath('data.metrics.at_risk.0.company', $client->company->name)
        ->assertJsonPath('data.metrics.at_risk.0.score', 2.5)
        ->assertJsonPath('data.metrics.low_score.0.company', $client->company->name)
        ->assertJsonPath('data.suggested_actions.0.type', 'low_score')
        ->assertJsonPath('data.suggested_actions.1.type', 'at_risk');

    $this->assertStringStartsWith('Follow up on', $response->json('data.suggested_actions.0.suggest'));
    $this->assertStringStartsWith('Check in with', $response->json('data.suggested_actions.1.suggest'));

    Http::assertNothingSent();
});

it('returns an empty satisfaction analysis without any HTTP calls when there is no data', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/analytics/satisfaction')
        ->assertOk()
        ->assertJsonPath('data.empty', true)
        ->assertJsonPath('data.metrics.surveys_sent', 0)
        ->assertJsonPath('data.suggested_actions', []);

    Http::assertNothingSent();
});
