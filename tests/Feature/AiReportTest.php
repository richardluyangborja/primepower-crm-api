<?php

use App\Enums\UserRole;
use App\Models\AiReport;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('groq.service_url', 'http://ai-service.test');
    Config::set('groq.timeout', 30);

    Http::fake([
        'ai-service.test/generate' => Http::response(['text' => "Executive Summary\nTest report content.\n\nKey Findings\nNothing notable."]),
    ]);

    $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin.ai@example.com']);
    $this->manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'manager.ai@example.com']);
    $this->salesRep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'sales.ai@example.com']);
});

describe('AI Reports - Authorization', function () {
    it('allows admin to list reports', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/ai-reports');

        $response->assertOk();
    });

    it('allows manager to list reports', function () {
        $response = $this->actingAs($this->manager)->getJson('/api/ai-reports');

        $response->assertOk();
    });

    it('rejects sales rep from listing reports', function () {
        $response = $this->actingAs($this->salesRep)->getJson('/api/ai-reports');

        $response->assertForbidden();
    });

    it('allows admin to generate a report', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'opportunity',
            'date_range' => 'this_week',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'opportunity');
        $response->assertJsonPath('data.type_label', 'Opportunity Report');
    });

    it('allows manager to generate a report', function () {
        $response = $this->actingAs($this->manager)->postJson('/api/ai-reports', [
            'type' => 'satisfaction',
            'date_range' => 'last_month',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'satisfaction');
    });

    it('rejects sales rep from generating a report', function () {
        $response = $this->actingAs($this->salesRep)->postJson('/api/ai-reports', [
            'type' => 'opportunity',
            'date_range' => 'this_week',
        ]);

        $response->assertForbidden();
    });

    it('allows admin to delete a report', function () {
        $report = AiReport::create([
            'user_id' => $this->admin->id,
            'type' => 'opportunity',
            'date_range' => 'this_week',
            'content' => 'Test content.',
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/ai-reports/{$report->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('ai_reports', ['id' => $report->id]);
    });

    it('rejects manager from deleting a report', function () {
        $report = AiReport::create([
            'user_id' => $this->admin->id,
            'type' => 'opportunity',
            'date_range' => 'this_week',
            'content' => 'Test content.',
        ]);

        $response = $this->actingAs($this->manager)->deleteJson("/api/ai-reports/{$report->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('ai_reports', ['id' => $report->id]);
    });

    it('rejects sales rep from deleting a report', function () {
        $report = AiReport::create([
            'user_id' => $this->admin->id,
            'type' => 'opportunity',
            'date_range' => 'this_week',
            'content' => 'Test content.',
        ]);

        $response = $this->actingAs($this->salesRep)->deleteJson("/api/ai-reports/{$report->id}");

        $response->assertForbidden();
    });
});

describe('AI Reports - Validation', function () {
    it('requires type field', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'date_range' => 'this_week',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('type');
    });

    it('requires valid type value', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'invalid_type',
            'date_range' => 'this_week',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('type');
    });

    it('requires date_range field', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'opportunity',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('date_range');
    });

    it('requires from_date and to_date when date_range is custom', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'opportunity',
            'date_range' => 'custom',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['from_date', 'to_date']);
    });

    it('accepts custom range with valid dates', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'opportunity',
            'date_range' => 'custom',
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-11',
        ]);

        $response->assertCreated();
    });
});

describe('AI Reports - Behavior', function () {
    it('persists the report with correct fields', function () {
        $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'rep_performance',
            'date_range' => 'last_month',
        ]);

        $report = AiReport::latest()->first();
        expect($report)->not->toBeNull();
        expect($report->type->value)->toBe('rep_performance');
        expect($report->date_range)->toBe('last_month');
        expect($report->user_id)->toBe($this->admin->id);
        expect($report->content)->toContain('Executive Summary');
    });

    it('logs an audit entry on generate', function () {
        $before = AuditLog::count();

        $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'opportunity',
            'date_range' => 'this_week',
        ]);

        expect(AuditLog::count())->toBeGreaterThan($before);

        $log = AuditLog::latest()->first();
        expect($log->module)->toBe('AI Reports');
        expect($log->action)->toBe('Generated');
    });

    it('logs an audit entry on delete', function () {
        $report = AiReport::create([
            'user_id' => $this->admin->id,
            'type' => 'opportunity',
            'date_range' => 'this_week',
            'content' => 'Test content.',
        ]);

        $before = AuditLog::count();

        $this->actingAs($this->admin)->deleteJson("/api/ai-reports/{$report->id}");

        expect(AuditLog::count())->toBeGreaterThan($before);

        $log = AuditLog::latest()->first();
        expect($log->module)->toBe('AI Reports');
        expect($log->action)->toBe('Deleted');
    });

    it('sends the correct prompt to the AI service', function () {
        $this->actingAs($this->admin)->postJson('/api/ai-reports', [
            'type' => 'opportunity',
            'date_range' => 'this_week',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'REPORT TOPIC: Opportunity pipeline performance')
                && str_contains($request->body(), 'system');
        });
    });

    it('lists reports in descending created_at order', function () {
        // created_at isn't fillable, so set it directly to guarantee distinct
        // timestamps and a real ordering check in SQLite.
        $old = new AiReport([
            'user_id' => $this->admin->id,
            'type' => 'opportunity',
            'date_range' => 'this_week',
            'content' => 'Old report.',
        ]);
        $old->created_at = '2026-09-01 08:00:00';
        $old->save();

        $new = new AiReport([
            'user_id' => $this->admin->id,
            'type' => 'satisfaction',
            'date_range' => 'last_month',
            'content' => 'New report.',
        ]);
        $new->created_at = '2026-09-11 12:00:00';
        $new->save();

        $response = $this->actingAs($this->admin)->getJson('/api/ai-reports');

        $data = $response->json('data');
        expect($data)->toHaveCount(2);
        expect($data[0]['type'])->toBe('satisfaction');
        expect($data[1]['type'])->toBe('opportunity');
    });
});
