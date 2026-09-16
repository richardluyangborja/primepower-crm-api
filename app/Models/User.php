<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'assigned_to_id');
    }

    public function assignedClients()
    {
        return $this->hasMany(Client::class, 'assigned_to_id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'assigned_to_id');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::MANAGER;
    }

    public function isSalesRep(): bool
    {
        return $this->role === UserRole::SALES_REP;
    }

    /**
     * Collect every user id that this user can see records for.
     *
     * Admin and Manager see every record in the system (managers are
     * read-only — writes are gated by policies, not this). A sales rep
     * sees only their own records.
     */
    public function visibleUserIds(): Collection
    {
        if ($this->isAdmin() || $this->isManager()) {
            return User::query()->pluck('id');
        }

        return collect([$this->id]);
    }
}
