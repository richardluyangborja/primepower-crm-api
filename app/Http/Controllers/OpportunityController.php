<?php

namespace App\Http\Controllers;

use App\Actions\Opportunities\CreateOpportunity;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Http\Requests\UpdateOpportunityStageRequest;
use App\Http\Resources\OpportunityDetailsResource;
use App\Http\Resources\OpportunityResource;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\StageHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        $user = $request->user();
        $opportunities = $this->scopeVisibleTo($user, Opportunity::query())
            ->with(['company', 'assignedTo'])
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')))
            ->when($request->filled('assigned_to_id'), fn ($q) => $q->where('assigned_to_id', $request->integer('assigned_to_id')))
            ->when($request->filled('value_min'), fn ($q) => $q->where('estimated_contract_value', '>=', $request->integer('value_min')))
            ->when($request->filled('value_max'), fn ($q) => $q->where('estimated_contract_value', '<=', $request->integer('value_max')))
            ->when($request->filled('expected_close_from'), fn ($q) => $q->whereDate('expected_close_date', '>=', $request->date('expected_close_from')))
            ->when($request->filled('expected_close_to'), fn ($q) => $q->whereDate('expected_close_date', '<=', $request->date('expected_close_to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return OpportunityResource::collection($opportunities);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $opportunities = Opportunity::query()
            ->where('assigned_to_id', $userId)
            ->with([
                'company',
                'assignedTo',
            ])
            ->latest()
            ->paginate(15);

        return OpportunityResource::collection($opportunities);
    }

    public function store(
        StoreOpportunityRequest $request,
        CreateOpportunity $createOpportunity
    ) {
        $this->authorize('create', Opportunity::class);

        $opportunity = $createOpportunity->handle(
            $request->validated()
        );

        $opportunity->load([
            'company.contacts',
            'lead.company',
            'assignedTo',
            'stageHistories.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Opportunity',
            'action' => 'Created',
            'subject_type' => 'Opportunity',
            'subject_id' => (string) $opportunity->id,
            'subject_name' => $opportunity->title,
            'description' => "Opportunity '{$opportunity->title}' was created"
                .($opportunity->company?->name ? " for company '{$opportunity->company->name}'" : '')
                .'.',
            'metadata' => [
                'company_name' => $opportunity->company?->name,
                'assigned_to' => $opportunity->assignedTo?->name,
                'stage' => $opportunity->stage?->value,
                'estimated_contract_value' => $opportunity->estimated_contract_value,
                'expected_close_date' => $opportunity->expected_close_date?->toDateString(),
            ],
        ]);

        return new OpportunityDetailsResource($opportunity);
    }

    public function show(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);

        $opportunity->load([
            'company.contacts',
            'lead.company',
            'assignedTo',

            'stageHistories.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        return new OpportunityDetailsResource($opportunity);
    }

    public function update(
        UpdateOpportunityRequest $request,
        Opportunity $opportunity
    ) {
        $this->authorize('update', $opportunity);

        $original = $opportunity->only(array_keys($request->validated()));
        $opportunity->update($request->validated());

        $changes = [];
        foreach ($original as $key => $oldValue) {
            $changes[$key] = [
                'from' => $oldValue,
                'to' => $opportunity->{$key},
            ];
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Opportunity',
            'action' => 'Updated',
            'subject_type' => 'Opportunity',
            'subject_id' => (string) $opportunity->id,
            'subject_name' => $opportunity->title,
            'description' => "Opportunity '{$opportunity->title}' was updated.",
            'metadata' => ['changes' => $changes],
        ]);

        $opportunity->load([
            'company.contacts',
            'lead.company',
            'assignedTo',

            'stageHistories.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        return new OpportunityDetailsResource($opportunity);
    }

    public function updateStage(
        UpdateOpportunityStageRequest $request,
        Opportunity $opportunity
    ) {
        $this->authorize('updateStage', $opportunity);

        $validated = $request->validated();

        $fromStage = $opportunity->stage->value;

        $opportunity->update([
            'stage' => $validated['stage'],
        ]);

        StageHistory::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => Auth::id(),
            'from_stage' => $fromStage,
            'to_stage' => $validated['stage'],
            'reason' => $validated['reason'] ?? null,
        ]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Opportunity',
            'action' => 'Stage Changed',
            'subject_type' => 'Opportunity',
            'subject_id' => (string) $opportunity->id,
            'subject_name' => $opportunity->title,
            'description' => "Opportunity '{$opportunity->title}' stage changed from '{$fromStage}' to '{$validated['stage']}'."
                .($validated['reason'] ?? null ? " Reason: {$validated['reason']}." : ''),
            'metadata' => [
                'from_stage' => $fromStage,
                'to_stage' => $validated['stage'],
                'reason' => $validated['reason'] ?? null,
            ],
        ]);

        $opportunity->load([
            'company.contacts',
            'lead.company',
            'assignedTo',

            'stageHistories.user',
            'reminders.company',
            'reminders.relatedTo',
        ]);

        return new OpportunityDetailsResource($opportunity);
    }

    private function scopeVisibleTo(User $user, $query)
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $ids = $user->visibleUserIds();

        return $query->whereIn('assigned_to_id', $ids);
    }
}
