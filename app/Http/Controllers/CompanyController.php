<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Models\Company;
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
}
