<?php

namespace App\Models;

use App\Enums\AiReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReport extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'date_range',
        'from_date',
        'to_date',
        'content',
    ];

    protected $casts = [
        'type' => AiReportType::class,
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
