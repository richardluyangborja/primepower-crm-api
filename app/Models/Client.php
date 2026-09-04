<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'company_id',
        'lead_id',
        'assigned_to_id',
        'status',
        'client_since',
        'notes',
    ];

    protected $casts = [
        'status' => ClientStatus::class,
        'client_since' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
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

    public function statusHistories()
    {
        return $this->hasMany(ClientStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function surveys()
    {
        return $this->hasMany(ClientSurvey::class);
    }

    public function getRecentActivityAttribute(): ?string
    {
        $latestCommunication = $this->company?->communications()->latest('created_at')->first();
        $latestReminder = $this->reminders()->latest('updated_at')->first();
        $latestSurvey = $this->surveys()->latest('created_at')->first();

        $candidates = array_filter([
            $latestCommunication?->created_at,
            $latestReminder?->updated_at,
            $latestSurvey?->created_at,
            $this->updated_at,
        ]);

        if (empty($candidates)) {
            return null;
        }

        $latest = collect($candidates)->max();

        return $latest instanceof \DateTimeInterface ? $latest->format(\DateTimeInterface::ATOM) : (string) $latest;
    }
}
