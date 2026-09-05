<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\SurveyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function templateUser(string $role, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'email' => $role.'-'.uniqid().'@example.com',
    ], $overrides));
}

function sampleQuestions(): array
{
    return [
        ['id' => 'q1', 'text' => 'How satisfied are you with our communication?', 'category' => 'Communication'],
        ['id' => 'q2', 'text' => 'How would you rate the quality of our deliverables?', 'category' => 'Quality'],
    ];
}

function createTemplate(array $overrides = []): SurveyTemplate
{
    $template = SurveyTemplate::create(array_merge([
        'name' => 'Test Template '.uniqid(),
        'description' => 'A test survey template',
        'is_active' => true,
    ], $overrides));

    $template->versions()->create([
        'version' => 1,
        'questions' => sampleQuestions(),
        'is_current' => true,
    ]);

    return $template;
}

// ── Index (read) ──

it('lets every role read survey templates', function () {
    createTemplate();

    foreach ([UserRole::ADMIN->value, UserRole::MANAGER->value, UserRole::SALES_REP->value] as $role) {
        $user = templateUser($role);

        $this->actingAs($user)->getJson('/api/survey-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
});

// ── Store ──

it('only lets an admin create survey templates', function () {
    $payload = [
        'name' => 'New Template',
        'description' => 'Brand new template',
        'questions' => sampleQuestions(),
    ];

    $admin = templateUser(UserRole::ADMIN->value);
    $this->actingAs($admin)->postJson('/api/survey-templates', $payload)
        ->assertCreated();

    expect(SurveyTemplate::where('name', 'New Template')->exists())->toBeTrue();
    expect(SurveyTemplate::where('name', 'New Template')->first()->versions()->count())->toBe(1);

    foreach ([UserRole::MANAGER->value, UserRole::SALES_REP->value] as $role) {
        $user = templateUser($role);
        $this->actingAs($user)->postJson('/api/survey-templates', $payload)
            ->assertForbidden();
    }
});

it('validates required fields on store', function () {
    $admin = templateUser(UserRole::ADMIN->value);

    $this->actingAs($admin)->postJson('/api/survey-templates', [])
        ->assertUnprocessable();

    $this->actingAs($admin)->postJson('/api/survey-templates', ['name' => 'Missing questions'])
        ->assertUnprocessable();

    $this->actingAs($admin)->postJson('/api/survey-templates', [
        'name' => 'Empty',
        'questions' => [],
    ])->assertUnprocessable();
});

// ── Update ──

it('only lets an admin update survey templates', function () {
    $template = createTemplate(['name' => 'Original']);
    $admin = templateUser(UserRole::ADMIN->value);

    $this->actingAs($admin)->putJson("/api/survey-templates/{$template->id}", [
        'name' => 'Renamed',
        'questions' => sampleQuestions(),
    ])->assertOk();

    expect($template->fresh()->name)->toBe('Renamed');

    foreach ([UserRole::MANAGER->value, UserRole::SALES_REP->value] as $role) {
        $user = templateUser($role);
        $this->actingAs($user)->putJson("/api/survey-templates/{$template->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();
    }
});

it('bumps version when questions change', function () {
    $template = createTemplate();
    $admin = templateUser(UserRole::ADMIN->value);

    $newQuestions = [
        ['id' => 'q1', 'text' => 'Updated question 1?', 'category' => 'Communication'],
        ['id' => 'q2', 'text' => 'Updated question 2?', 'category' => 'Quality'],
    ];

    $this->actingAs($admin)->putJson("/api/survey-templates/{$template->id}", [
        'name' => $template->name,
        'questions' => $newQuestions,
    ])->assertOk();

    expect($template->versions()->count())->toBe(2);
    expect($template->currentVersion->version)->toBe(2);
    expect($template->currentVersion->questions)->toEqual($newQuestions);
});

it('does not bump version when only metadata changes', function () {
    $template = createTemplate();
    $admin = templateUser(UserRole::ADMIN->value);

    $this->actingAs($admin)->putJson("/api/survey-templates/{$template->id}", [
        'name' => 'Renamed Template',
        'description' => 'Updated description',
        'questions' => sampleQuestions(),
    ])->assertOk();

    expect($template->versions()->count())->toBe(1);
    expect($template->currentVersion->version)->toBe(1);
});

it('does not bump version when question field order differs but content is the same', function () {
    $template = createTemplate();
    $admin = templateUser(UserRole::ADMIN->value);

    // Reversed field order within each question — should be considered equal
    $reordered = collect(sampleQuestions())->map(fn ($q) => array_reverse($q))->toArray();

    $this->actingAs($admin)->putJson("/api/survey-templates/{$template->id}", [
        'questions' => $reordered,
    ])->assertOk();

    expect($template->versions()->count())->toBe(1);
});

// ── Destroy ──

it('only lets an admin delete survey templates', function () {
    $template = createTemplate();
    $admin = templateUser(UserRole::ADMIN->value);

    $this->actingAs($admin)->deleteJson("/api/survey-templates/{$template->id}")
        ->assertNoContent();

    expect(SurveyTemplate::find($template->id))->toBeNull();

    $template2 = createTemplate(['name' => 'To Delete']);
    foreach ([UserRole::MANAGER->value, UserRole::SALES_REP->value] as $role) {
        $user = templateUser($role);
        $this->actingAs($user)->deleteJson("/api/survey-templates/{$template2->id}")
            ->assertForbidden();
    }
});

// ── Store survey with template_id snapshots version ──

it('snapshots the current template version when creating a survey with template_id', function () {
    $template = createTemplate();
    $admin = templateUser(UserRole::ADMIN->value);

    $company = Company::create([
        'name' => 'Template Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0600',
        'email' => 'co.'.uniqid().'@test.com',
    ]);
    $client = $company->client()->create([
        'assigned_to_id' => $admin->id,
        'status' => 'active',
        'client_since' => now(),
    ]);

    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys", [
        'template_id' => $template->id,
    ])->assertOk();

    $survey = $client->surveys()->first();
    expect($survey->template_version_id)->toBe($template->currentVersion->id);

    // Bump the template version
    $this->actingAs($admin)->putJson("/api/survey-templates/{$template->id}", [
        'questions' => [
            ['id' => 'q1', 'text' => 'Brand new question', 'category' => 'New'],
        ],
    ]);

    // New survey should get the new version
    $this->actingAs($admin)->postJson("/api/satisfaction/{$client->id}/surveys", [
        'template_id' => $template->id,
    ])->assertOk();

    $surveys = $client->surveys()->orderBy('id')->get();
    expect($surveys->first()->template_version_id)->toBe(1); // old version
    expect($surveys->last()->template_version_id)->toBe(2); // new version
});
