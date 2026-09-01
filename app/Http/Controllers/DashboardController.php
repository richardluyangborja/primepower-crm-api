<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => new DashboardResource([]),
        ]);
    }
}
