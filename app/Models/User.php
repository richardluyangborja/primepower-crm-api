<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'manager_id',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
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
     * Admin -> all; Manager -> self + every report (transitive); SalesRep -> self.
     */
    public function visibleUserIds(): Collection
    {
        if ($this->isAdmin()) {
            return User::query()->pluck('id');
        }

        if ($this->isManager()) {
            return collect([$this->id])->merge($this->collectReportIds());
        }

        return collect([$this->id]);
    }

    private function collectReportIds(): Collection
    {
        $ids = collect();
        $stack = [$this->id];

        while ($managerId = array_shift($stack)) {
            $reports = User::query()
                ->where('manager_id', $managerId)
                ->pluck('id')
                ->all();

            foreach ($reports as $reportId) {
                if (! $ids->contains($reportId) && $reportId !== $this->id) {
                    $ids->push($reportId);
                    $stack[] = $reportId;
                }
            }
        }

        return $ids;
    }
}
