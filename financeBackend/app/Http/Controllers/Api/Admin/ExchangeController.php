<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Exchange;
use App\Support\AdminAccess;
use App\Support\AuditLogger;
use App\Support\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExchangeController extends Controller
{
    public function index(Request $request, StoreContext $stores)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->hasPermission('transactions')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Exchange::query()->with('recorder')->latest('exchange_date')->latest('id');
        $stores->scope($query, $admin, $request);
        $request->validate([
            'date' => ['nullable', 'date'],
            'admin_user_id' => ['nullable', 'integer', 'exists:admin_users,id'],
        ]);
        $query
            ->when($request->filled('date'), fn ($query) => $query->whereDate('exchange_date', $request->string('date')))
            ->when(
                $admin->is_super_admin && $request->filled('admin_user_id'),
                fn ($query) => $query->where('recorded_by_admin_id', $request->integer('admin_user_id')),
            );

        return response()->json($query->paginate($request->integer('per_page', 50)));
    }

    public function store(Request $request, StoreContext $stores, AuditLogger $audit)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }
        if (! $admin->hasPermission('transactions')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'direction' => ['required', Rule::in(['cash_in', 'online_in'])],
            'online_method' => ['required', Rule::in(['bank', 'wechat', 'alipay'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'exchange_date' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $store = $stores->writableStore($admin, $request);
        $exchange = Exchange::query()->create($data + [
            'store_id' => $store->id,
            'recorded_by_admin_id' => $admin->id,
            'fee' => $data['fee'] ?? 0,
        ]);
        $audit->record($admin, $exchange, 'exchange.created', null, null, $exchange->toArray());

        return response()->json($exchange->load('recorder'), 201);
    }
}
