<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 401);

        return response()->json([
            'data' => new DashboardResource($request),
        ]);
    }
}
