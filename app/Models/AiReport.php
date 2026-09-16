<?php

namespace App\Models;

use App\Enums\AiReportType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'date_range',
        'from_date',
        'to_date',
        'content',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => AiReportType::coerce((string) $value),
            set: fn (AiReportType|string $value) => ['type' => $value instanceof AiReportType ? $value->value : $value],
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
