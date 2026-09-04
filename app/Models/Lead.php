<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    protected $fillable = [
        'company_id',
        'assigned_to_id',
        'source',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => LeadStatus::class,
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class, 'lead_id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(StatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function communications()
    {
        return $this->hasMany(Communication::class, 'company_id', 'company_id')
            ->latest();
    }

    public function reminders()
    {
        return $this->morphMany(Reminder::class, 'related_to');
    }

    public function getRecentActivityAttribute(): ?string
    {
        $latestCommunication = $this->company?->communications()->latest('created_at')->first();
        $latestReminder = $this->reminders()->latest('updated_at')->first();

        $candidates = array_filter([
            $latestCommunication?->created_at,
            $latestReminder?->updated_at,
            $this->updated_at,
        ]);

        if (empty($candidates)) {
            return null;
        }

        $latest = collect($candidates)->max();

        return $latest instanceof \DateTimeInterface ? $latest->format(\DateTimeInterface::ATOM) : (string) $latest;
    }
}
