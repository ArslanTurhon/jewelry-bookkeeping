<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Support\FinanceStats;
use Illuminate\Http\Request;

class OpeningBalanceController extends Controller
{
    public function show(Request $request, FinanceStats $stats)
    {
        if (! $this->admin($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($stats->openingBalances());
    }

    public function store(Request $request, FinanceStats $stats)
    {
        if (! $this->admin($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($stats->saveOpeningBalances($request->validate([
            '*' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
        ])));
    }

    private function admin(Request $request): ?AdminUser
    {
        $token = $request->bearerToken();

        return $token ? AdminUser::query()->where('api_token', $token)->first() : null;
    }
}
