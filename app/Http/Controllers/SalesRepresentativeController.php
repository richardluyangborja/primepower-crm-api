<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\SalesRepresentativeResource;
use App\Models\User;
use Illuminate\Http\Request;

class SalesRepresentativeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $includeManagers = $request->boolean('include_managers')
            || $user->isAdmin()
            || $user->isManager();

        $query = User::query()
            ->where('role', UserRole::SALES_REP)
            ->where('is_active', true)
            ->when($includeManagers, fn ($q) => $q->orWhere(function ($q) {
                $q->where('role', UserRole::MANAGER)
                    ->where('is_active', true);
            }))
            ->orderBy('name')
            ->get();

        return SalesRepresentativeResource::collection($query);
    }
}
