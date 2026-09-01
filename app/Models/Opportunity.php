<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [
        'company_id',
        'lead_id',
        'client_id',
        'assigned_to_id',
        'title',
        'description',
        'stage',
        'manpower_requirement',
        'estimated_contract_value',
        'expected_close_date',
        'lost_reason',
    ];

    protected $casts = [
        'stage' => OpportunityStage::class,
        'estimated_contract_value' => 'decimal:2',
        'expected_close_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function stageHistories()
    {
        return $this->hasMany(StageHistory::class)->orderBy('created_at', 'desc');
    }

    public function reminders()
    {
        return $this->morphMany(Reminder::class, 'related_to');
    }
}
