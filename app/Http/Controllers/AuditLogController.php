<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->latest();

        if ($request->filled('module')) {
            $query->where('module', $request->string('module'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }

        if ($request->filled('actor')) {
            $actor = $request->string('actor');
            $query->where(function ($q) use ($actor) {
                $q->where('actor_name', 'like', "%{$actor}%")
                    ->orWhere('actor_email', 'like', "%{$actor}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to'));
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

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', AuditLog::class);

        $query = AuditLog::query()->latest();

        foreach (['module', 'action'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to'));
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Audit Log',
            'action' => 'Exported',
            'subject_type' => 'AuditLog',
            'subject_id' => null,
            'subject_name' => 'CSV export',
            'description' => 'Audit log CSV export requested.',
            'metadata' => $request->only(['module', 'action', 'date_from', 'date_to']),
        ]);

        $fileName = 'audit-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'created_at',
                'actor_name',
                'actor_email',
                'actor_role',
                'module',
                'action',
                'subject_type',
                'subject_id',
                'subject_name',
                'description',
            ]);

            $query->lazy()->each(function (AuditLog $log) use ($out) {
                fputcsv($out, [
                    $log->id,
                    $log->created_at?->toDateTimeString(),
                    $log->actor_name,
                    $log->actor_email,
                    $log->actor_role,
                    $log->module,
                    $log->action,
                    $log->subject_type,
                    $log->subject_id,
                    $log->subject_name,
                    $log->description,
                ]);
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
