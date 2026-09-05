<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentVersion = $this->currentVersion;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'version' => $currentVersion?->version,
            'questions' => $currentVersion?->questions ?? [],
            'question_count' => count($currentVersion?->questions ?? []),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
