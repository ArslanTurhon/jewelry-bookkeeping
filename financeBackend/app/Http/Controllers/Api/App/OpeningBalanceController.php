<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FinanceStats;
use Illuminate\Http\Request;

class OpeningBalanceController extends Controller
{
    public function show(Request $request, FinanceStats $stats)
    {
        if (! $this->user($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($stats->openingBalances());
    }

    public function store(Request $request, FinanceStats $stats)
    {
        if (! $this->user($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($stats->saveOpeningBalances($request->validate([
            '*' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
        ])));
    }

    private function user(Request $request): ?User
    {
        $token = $request->bearerToken();

        return $token ? User::query()->where('api_token', $token)->first() : null;
    }
}
