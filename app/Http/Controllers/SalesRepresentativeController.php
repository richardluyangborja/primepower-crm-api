<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\SalesRepresentativeResource;
use App\Models\User;

class SalesRepresentativeController extends Controller
{
    public function index()
    {
        $salesRepresentatives = User::query()
            ->where('role', UserRole::SALES_REP)
            ->orderBy('name')
            ->get();

        return SalesRepresentativeResource::collection(
            $salesRepresentatives
        );
    }
}
