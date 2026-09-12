<?php

namespace App\Http\Controllers;

use App\Actions\Ai\ComposeAiReportPrompt;
use App\Actions\Ai\GroqClient;
use App\Enums\AiReportType;
use App\Http\Resources\AiReportResource;
use App\Models\AiReport;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AiReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AiReport::class);

        $reports = AiReport::query()
            ->with('user')
            ->latest()
            ->paginate(15);

        return AiReportResource::collection($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AiReport::class);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_column(AiReportType::cases(), 'value'))],
            'date_range' => ['required', 'string', 'in:this_week,last_month,custom'],
            'from_date' => ['required_if:date_range,custom', 'nullable', 'date'],
            'to_date' => ['required_if:date_range,custom', 'nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $type = AiReportType::from($validated['type']);
        [$from, $to] = $this->resolveRange($validated['date_range'], $validated['from_date'] ?? null, $validated['to_date'] ?? null);

        $composer = app(ComposeAiReportPrompt::class);
        $prompt = $composer->compose($type, $request->user(), $from, $to);

        $content = app(GroqClient::class)->generate($prompt['prompt'], $prompt['system']);

        $report = AiReport::create([
            'user_id' => $request->user()->id,
            'type' => $type,
            'date_range' => $validated['date_range'],
            'from_date' => $from?->toDateString(),
            'to_date' => $to?->toDateString(),
            'content' => $content,
        ]);

        $report->load('user');

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'AI Reports',
            'action' => 'Generated',
            'subject_type' => 'AiReport',
            'subject_id' => (string) $report->id,
            'subject_name' => $type->label(),
            'description' => "{$type->label()} ({$validated['date_range']}) was generated.",
            'metadata' => [
                'type' => $type->value,
                'date_range' => $validated['date_range'],
                'from_date' => $from?->toDateString(),
                'to_date' => $to?->toDateString(),
            ],
        ]);

        return (new AiReportResource($report))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(AiReport $ai_report)
    {
        $this->authorize('delete', $ai_report);

        $label = $ai_report->type->label();
        $id = $ai_report->id;
        $ai_report->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'AI Reports',
            'action' => 'Deleted',
            'subject_type' => 'AiReport',
            'subject_id' => (string) $id,
            'subject_name' => $label,
            'description' => "{$label} was deleted.",
        ]);

        return response()->noContent();
    }

    private function resolveRange(string $range, ?string $fromDate, ?string $toDate): array
    {
        return match ($range) {
            'this_week' => [Carbon::now()->startOfWeek(), Carbon::now()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'custom' => [
                $fromDate ? Carbon::parse($fromDate) : null,
                $toDate ? Carbon::parse($toDate) : null,
            ],
        };
    }
}
