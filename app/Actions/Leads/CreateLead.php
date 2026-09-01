<?php

namespace App\Actions\Leads;

use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class CreateLead
{
    public function handle(array $data): Lead
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create($data['company']);

            $company->contacts()->create([
                ...$data['primary_contact'],
                'is_primary' => true,
            ]);

            return $company->leads()->create([
                'assigned_to_id' => $data['assigned_to_id'],
                'source' => $data['source'],
                'notes' => $data['notes'] ?? null,
                'status' => LeadStatus::NEW,
            ]);
        });
    }
}
