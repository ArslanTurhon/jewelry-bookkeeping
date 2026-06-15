<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Support\AdminAccess;
use App\Support\FinanceStats;
use Illuminate\Http\Request;

class OpeningBalanceController extends Controller
{
    public function show(Request $request, FinanceStats $stats)
    {
        $admin = AdminAccess::require($request, 'opening');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        return response()->json($stats->openingBalances());
    }

    public function store(Request $request, FinanceStats $stats)
    {
        $admin = AdminAccess::require($request, 'opening');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        return response()->json($stats->saveOpeningBalances($request->validate([
            '*' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
        ])));
    }

}
