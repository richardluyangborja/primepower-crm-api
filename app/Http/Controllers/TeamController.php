<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\TeamResource;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $teams = Team::query()
            ->with(['manager', 'members'])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return TeamResource::collection($teams);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $team = Team::create($data);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Team',
            'action' => 'Created',
            'subject_type' => 'Team',
            'subject_id' => (string) $team->id,
            'subject_name' => $team->name,
            'description' => "Team '{$team->name}' was created.",
            'metadata' => $data,
        ]);

        $team->load(['manager'])->loadCount('members');

        return response()->json([
            'data' => (new TeamResource($team))->resolve(),
        ], 201);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', "unique:teams,name,{$team->id}"],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $team->update($data);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Team',
            'action' => 'Updated',
            'subject_type' => 'Team',
            'subject_id' => (string) $team->id,
            'subject_name' => $team->name,
            'description' => "Team '{$team->name}' was updated.",
            'metadata' => $data,
        ]);

        $team->load(['manager'])->loadCount('members');

        return new TeamResource($team);
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('create', User::class);

        $name = $team->name;
        $team->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Team',
            'action' => 'Deleted',
            'subject_type' => 'Team',
            'subject_id' => (string) $team->id,
            'subject_name' => $name,
            'description' => "Team '{$name}' was deleted.",
            'metadata' => [],
        ]);

        return response()->json(null, 204);
    }

    public function members(Team $team)
    {
        $members = $team->members()->with('manager')->orderBy('name')->get();

        return response()->json([
            'data' => $members->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role instanceof UserRole ? $u->role->value : (string) $u->role,
                'manager_id' => $u->manager_id,
                'is_active' => (bool) $u->is_active,
            ])->all(),
        ]);
    }
}
