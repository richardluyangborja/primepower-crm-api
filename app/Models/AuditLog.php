<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property string|null $actor_role
 * @property string $module
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_name
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'actor_name',
    'actor_email',
    'actor_role',
    'module',
    'action',
    'subject_type',
    'subject_id',
    'subject_name',
    'description',
    'metadata',
])]
class AuditLog extends Model
{
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Build the actor attributes from the currently authenticated user.
     *
     * @return array{actor_name: string|null, actor_email: string|null, actor_role: string|null}
     */
    public static function actor(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [
                'actor_name' => 'System',
                'actor_email' => null,
                'actor_role' => null,
            ];
        }

        $role = $user->role instanceof UserRole
            ? $user->role->value
            : (string) $user->role;

        return [
            'actor_name' => $user->name,
            'actor_email' => $user->email,
            'actor_role' => $role,
        ];
    }

    /**
     * Persist an audit log entry without ever breaking the caller's operation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function log(array $data): void
    {
        try {
            self::create($data);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
