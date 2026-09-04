<?php

namespace App\Models;

use App\Enums\ReminderPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    protected $fillable = [
        'company_id',
        'related_to_type',
        'related_to_id',
        'title',
        'description',
        'due_date',
        'priority',
        'status',
        'is_completed',
        'completed_at',
        'assigned_to_name',
        'user_id',
        'recurrence_rule',
        'recurrence_parent_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'priority' => ReminderPriority::class,
        'status' => 'string',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function relatedTo(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }
}
