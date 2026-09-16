<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyTemplateVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'survey_template_id',
        'version',
        'questions',
        'is_current',
    ];

    protected $casts = [
        'questions' => 'array',
        'is_current' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }
}
