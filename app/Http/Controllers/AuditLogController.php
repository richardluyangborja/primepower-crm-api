<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit log entries with optional filtering.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $role = $user?->role instanceof UserRole
            ? $user->role->value
            : (string) $user?->role;

        if (! in_array($role, ['admin', 'manager'], true)) {
            abort(403, 'You are not authorized to view the audit log.');
        }

        $query = AuditLog::query()->latest();

        if ($request->filled('module')) {
            $query->where('module', $request->string('module'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where(function ($q) use ($search) {
                $q->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('actor_email', 'like', "%{$search}%")
                    ->orWhere('subject_name', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return AuditLogResource::collection($query->paginate(20));
    }
}
