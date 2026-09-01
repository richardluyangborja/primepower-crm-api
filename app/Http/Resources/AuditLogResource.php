<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_name' => $this->actor_name,
            'actor_email' => $this->actor_email,
            'actor_role' => $this->actor_role,
            'module' => $this->module,
            'action' => $this->action,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->subject_name,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
