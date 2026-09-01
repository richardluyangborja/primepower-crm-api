<?php

namespace App\Models;

use App\Enums\ClientSurveyStatus;
use Illuminate\Database\Eloquent\Model;

class ClientSurvey extends Model
{
    protected $fillable = [
        'client_id',
        'token',
        'status',
        'responses',
        'average_score',
        'completed_at',
        'respondent_name',
        'respondent_position',
        'feedback',
    ];

    protected $casts = [
        'status' => ClientSurveyStatus::class,
        'responses' => 'array',
        'average_score' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function calculateAverageScore(): ?float
    {
        if (empty($this->responses)) {
            return null;
        }

        $scores = array_column($this->responses, 'score');
        if (empty($scores)) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }
}
