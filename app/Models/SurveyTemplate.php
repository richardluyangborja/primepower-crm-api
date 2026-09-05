<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SurveyTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(SurveyTemplateVersion::class)->orderBy('version', 'desc');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(SurveyTemplateVersion::class)->where('is_current', true);
    }
}
