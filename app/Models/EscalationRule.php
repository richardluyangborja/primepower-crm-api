<?php

namespace App\Models;

use App\Enums\EscalationAction;
use App\Enums\EscalationCondition;
use App\Enums\ReminderPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalationRule extends Model
{
    protected $fillable = [
        'name',
        'entity_type',
        'condition',
        'threshold_days',
        'action_type',
        'reminder_title',
        'reminder_priority',
        'reminder_due_in_days',
        'is_active',
    ];

    protected $casts = [
        'condition' => EscalationCondition::class,
        'action_type' => EscalationAction::class,
        'reminder_priority' => ReminderPriority::class,
        'threshold_days' => 'integer',
        'reminder_due_in_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function triggers(): HasMany
    {
        return $this->hasMany(EscalationTrigger::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
