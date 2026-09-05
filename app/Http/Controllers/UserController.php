<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['manager'])->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return UserResource::collection($query->paginate(20));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $plainPassword = $data['password'];
        $data['password'] = Hash::make($plainPassword);
        $data['is_active'] = $data['is_active'] ?? true;

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->load(['manager']);

            AuditLog::log([
                ...AuditLog::actor(),
                'module' => 'User',
                'action' => 'Created',
                'subject_type' => 'User',
                'subject_id' => (string) $user->id,
                'subject_name' => $user->name,
                'description' => "User '{$user->name}' was created with role '{$user->role->value}'.",
                'metadata' => [
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'manager_id' => $user->manager_id,
                ],
            ]);

            return $user;
        });

        return response()->json([
            'data' => (new UserResource($user))->resolve(),
            'initial_password' => $plainPassword,
        ], 201);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load(['manager']);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $original = $user->only(array_keys($data));

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $previousRole = $user->role;
        $user->update($data);
        $user->load(['manager']);

        $changes = [];
        foreach ($original as $key => $oldValue) {
            if ($key === 'password') {
                continue;
            }
            $changes[$key] = [
                'from' => $oldValue,
                'to' => $user->{$key},
            ];
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'User',
            'action' => 'Updated',
            'subject_type' => 'User',
            'subject_id' => (string) $user->id,
            'subject_name' => $user->name,
            'description' => "User '{$user->name}' was updated.",
            'metadata' => ['changes' => $changes],
        ]);

        if ($previousRole !== $user->role) {
            $user->tokens()->delete();

            AuditLog::log([
                ...AuditLog::actor(),
                'module' => 'User',
                'action' => 'Tokens Revoked',
                'subject_type' => 'User',
                'subject_id' => (string) $user->id,
                'subject_name' => $user->name,
                'description' => "User '{$user->name}' role changed from '{$previousRole->value}' to '{$user->role->value}'; all tokens revoked.",
                'metadata' => [
                    'previous_role' => $previousRole->value,
                    'new_role' => $user->role->value,
                ],
            ]);
        }

        return new UserResource($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $name = $user->name;
        $email = $user->email;
        $user->tokens()->delete();
        $user->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'User',
            'action' => 'Soft Deleted',
            'subject_type' => 'User',
            'subject_id' => (string) $user->id,
            'subject_name' => $name,
            'description' => "User '{$name}' ({$email}) was soft-deleted.",
            'metadata' => ['email' => $email],
        ]);

        return response()->json(null, 204);
    }

    public function deactivate(User $user): JsonResponse
    {
        $this->authorize('deactivate', $user);

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'User',
            'action' => 'Deactivated',
            'subject_type' => 'User',
            'subject_id' => (string) $user->id,
            'subject_name' => $user->name,
            'description' => "User '{$user->name}' was deactivated; all tokens revoked.",
            'metadata' => ['email' => $user->email],
        ]);

        return response()->json(['data' => (new UserResource($user->fresh(['manager'])))->resolve()]);
    }

    public function activate(User $user): JsonResponse
    {
        $this->authorize('activate', $user);

        $user->update(['is_active' => true]);

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'User',
            'action' => 'Activated',
            'subject_type' => 'User',
            'subject_id' => (string) $user->id,
            'subject_name' => $user->name,
            'description' => "User '{$user->name}' was re-activated.",
            'metadata' => ['email' => $user->email],
        ]);

        return response()->json(['data' => (new UserResource($user->fresh(['manager'])))->resolve()]);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('resetPassword', $user);

        $plainPassword = Str::random(16);
        $user->update(['password' => Hash::make($plainPassword)]);
        $user->tokens()->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'User',
            'action' => 'Password Reset',
            'subject_type' => 'User',
            'subject_id' => (string) $user->id,
            'subject_name' => $user->name,
            'description' => "Password for '{$user->name}' was reset by an administrator.",
            'metadata' => [
                'email' => $user->email,
                'send_email' => (bool) $request->input('send_email', false),
            ],
        ]);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'temporary_password' => $plainPassword,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', User::class);

        $query = User::query()->with(['manager'])->latest();

        foreach (['role'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'User',
            'action' => 'Exported',
            'subject_type' => 'User',
            'subject_id' => null,
            'subject_name' => 'CSV export',
            'description' => 'User CSV export requested.',
            'metadata' => $request->only(['role', 'is_active']),
        ]);

        $fileName = 'users-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'name', 'email', 'role', 'manager', 'is_active', 'created_at',
            ]);

            $query->lazy()->each(function (User $user) use ($out) {
                fputcsv($out, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
                    $user->manager?->name,
                    $user->is_active ? 'yes' : 'no',
                    $user->created_at?->toDateTimeString(),
                ]);
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
