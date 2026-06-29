<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StoreContext
{
    public function readableStoreId(AdminUser $admin, Request $request): ?int
    {
        if (! $admin->is_super_admin) {
            return $admin->store_id ?: (int) Store::query()->where('is_default', true)->value('id');
        }

        $requested = $request->header('X-Store-Id');

        return filled($requested) ? (int) $requested : null;
    }

    public function writableStore(AdminUser $admin, Request $request): Store
    {
        if ($admin->is_super_admin) {
            $storeId = $request->header('X-Store-Id');
            if (! $storeId) {
                $enabled = Store::query()->where('enabled', true);
                if ($enabled->count() !== 1) {
                    abort(response()->json(['message' => '请先选择一家店铺再录入'], 422));
                }
                $storeId = $enabled->value('id');
            }
        } else {
            $storeId = $admin->store_id ?: Store::query()->where('is_default', true)->value('id');
        }

        return Store::query()->whereKey($storeId)->where('enabled', true)->firstOrFail();
    }

    public function scope(Builder $query, AdminUser $admin, Request $request): Builder
    {
        $storeId = $this->readableStoreId($admin, $request);

        return $query->when($storeId, fn (Builder $builder) => $builder->where('store_id', $storeId));
    }
}
