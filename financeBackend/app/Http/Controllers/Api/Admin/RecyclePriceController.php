<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\RecyclePrice;
use App\Support\AdminAccess;
use App\Support\StoreContext;
use Illuminate\Http\Request;

class RecyclePriceController extends Controller
{
    public function show(Request $request, StoreContext $stores)
    {
        $admin = AdminAccess::require($request, 'recycle_price');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $date = $request->query('date', now()->toDateString());
        $store = $stores->writableStore($admin, $request);
        $price = RecyclePrice::query()->firstOrCreate(
            ['store_id' => $store->id, 'price_date' => $date],
            ['reference_gold_price' => 0, 'reference_silver_price' => 0],
        );

        return response()->json($price);
    }

    public function store(Request $request, StoreContext $stores)
    {
        $admin = AdminAccess::require($request, 'recycle_price');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $data = $request->validate([
            'price_date' => ['required', 'date'],
            'reference_gold_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'reference_silver_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        $store = $stores->writableStore($admin, $request);

        return response()->json(RecyclePrice::query()->updateOrCreate(
            ['store_id' => $store->id, 'price_date' => $data['price_date']],
            [
                'reference_gold_price' => $data['reference_gold_price'] ?? 0,
                'reference_silver_price' => $data['reference_silver_price'] ?? 0,
            ],
        ));
    }
}
