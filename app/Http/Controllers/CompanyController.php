<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()
            ->with([
                'client',
                'client.assignedTo',
                'contacts',
                'leads',
                'leads.assignedTo',
                'leads.company',
            ])
            ->orderBy('name')
            ->get();

        return CompanyResource::collection($companies);
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        $companies = Company::query()
            ->whereHas('leads', fn ($q) => $q->where('assigned_to_id', $userId))
            ->orWhereHas('client', fn ($q) => $q->where('assigned_to_id', $userId))
            ->with([
                'client',
                'client.assignedTo',
                'contacts',
                'leads',
                'leads.assignedTo',
                'leads.company',
            ])
            ->orderBy('name')
            ->get();

        return CompanyResource::collection($companies);
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $original = $company->only(array_keys($request->validated()));
        $company->update($request->validated());

        $changes = [];
        foreach ($original as $key => $oldValue) {
            $changes[$key] = [
                'from' => $oldValue,
                'to' => $company->{$key},
            ];
        }

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Company',
            'action' => 'Updated',
            'subject_type' => 'Company',
            'subject_id' => (string) $company->id,
            'subject_name' => $company->name,
            'description' => "Company '{$company->name}' was updated.",
            'metadata' => ['changes' => $changes],
        ]);

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'industry' => $company->industry,
                'address' => $company->address,
                'phone' => $company->phone,
                'email' => $company->email,
                'website' => $company->website,
            ],
        ]);
    }
}
