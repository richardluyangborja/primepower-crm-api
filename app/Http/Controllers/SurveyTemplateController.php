<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSurveyTemplateRequest;
use App\Http\Requests\UpdateSurveyTemplateRequest;
use App\Http\Resources\SurveyTemplateResource;
use App\Models\AuditLog;
use App\Models\SurveyTemplate;
use Illuminate\Http\Request;

class SurveyTemplateController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', SurveyTemplate::class);

        $templates = SurveyTemplate::query()
            ->with('currentVersion')
            ->latest()
            ->get();

        return SurveyTemplateResource::collection($templates);
    }

    public function store(StoreSurveyTemplateRequest $request)
    {
        $this->authorize('create', SurveyTemplate::class);

        $template = SurveyTemplate::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $template->versions()->create([
            'version' => 1,
            'questions' => $request->input('questions'),
            'is_current' => true,
        ]);

        $this->logAudit('Created', $template, [
            'version' => 1,
            'is_active' => $template->is_active,
        ]);

        return new SurveyTemplateResource($template->load('currentVersion'));
    }

    public function update(UpdateSurveyTemplateRequest $request, SurveyTemplate $surveyTemplate)
    {
        $this->authorize('update', $surveyTemplate);

        $template = $surveyTemplate;

        $template->update([
            'name' => $request->input('name', $template->name),
            'description' => $request->has('description')
                ? $request->input('description')
                : $template->description,
            'is_active' => $request->has('is_active')
                ? $request->boolean('is_active')
                : $template->is_active,
        ]);

        $questionsChanged = false;
        if ($request->has('questions')) {
            $currentVersion = $template->currentVersion;
            $currentQuestions = $currentVersion?->questions ?? [];
            $incoming = $request->input('questions');

            if (! $this->questionsEqual($currentQuestions, $incoming)) {
                $nextVersion = ($currentVersion?->version ?? 0) + 1;

                if ($currentVersion) {
                    $currentVersion->update(['is_current' => false]);
                }

                $template->versions()->create([
                    'version' => $nextVersion,
                    'questions' => $incoming,
                    'is_current' => true,
                ]);

                $questionsChanged = true;
            }
        }

        $this->logAudit('Updated', $template, [
            'version' => $template->currentVersion?->version,
            'is_active' => $template->is_active,
            'questions_changed' => $questionsChanged,
        ]);

        return new SurveyTemplateResource($template->load('currentVersion'));
    }

    public function destroy(SurveyTemplate $surveyTemplate)
    {
        $this->authorize('delete', $surveyTemplate);

        $name = $surveyTemplate->name;
        $surveyTemplate->delete();

        $this->logAudit('Deleted', $surveyTemplate, []);

        return response()->noContent();
    }

    private function questionsEqual(array $current, array $incoming): bool
    {
        // Normalise to a comparable shape so field order in the JSON does not
        // force a spurious version bump.
        $normalise = fn (array $questions) => collect($questions)
            ->map(fn ($q) => [
                'id' => $q['id'] ?? '',
                'text' => $q['text'] ?? '',
                'category' => $q['category'] ?? '',
            ])
            ->values()
            ->toArray();

        return $normalise($current) === $normalise($incoming);
    }

    private function logAudit(string $action, SurveyTemplate $template, array $metadata): void
    {
        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'SurveyTemplate',
            'action' => $action,
            'subject_type' => 'SurveyTemplate',
            'subject_id' => (string) $template->id,
            'subject_name' => $template->name,
            'description' => "Survey template '{$template->name}' was {$this->pastTense($action)}.",
            'metadata' => $metadata,
        ]);
    }

    private function pastTense(string $action): string
    {
        return match ($action) {
            'Created' => 'created',
            'Updated' => 'updated',
            'Deleted' => 'deleted',
            default => strtolower($action),
        };
    }
}
