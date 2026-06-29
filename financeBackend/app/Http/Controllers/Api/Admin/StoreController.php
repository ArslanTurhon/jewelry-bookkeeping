<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Store;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $owner = $this->owner($request);
        if (! $owner instanceof AdminUser) {
            return $owner;
        }

        return response()->json(Store::query()->orderByDesc('is_default')->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $owner = $this->owner($request);
        if (! $owner instanceof AdminUser) {
            return $owner;
        }

        return response()->json(Store::query()->create($this->validated($request)), 201);
    }

    public function update(Request $request, Store $store)
    {
        $owner = $this->owner($request);
        if (! $owner instanceof AdminUser) {
            return $owner;
        }

        $store->update($this->validated($request, $store));

        return response()->json($store->fresh());
    }

    public function destroy(Request $request, Store $store)
    {
        $owner = $this->owner($request);
        if (! $owner instanceof AdminUser) {
            return $owner;
        }

        $store->update(['enabled' => false]);

        return response()->json(['message' => 'disabled']);
    }

    private function owner(Request $request)
    {
        $admin = AdminAccess::require($request);

        return $admin instanceof AdminUser && ! $admin->is_super_admin
            ? response()->json(['message' => 'Forbidden'], 403)
            : $admin;
    }

    private function validated(Request $request, ?Store $store = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('stores')->ignore($store)],
            'enabled' => ['sometimes', 'boolean'],
        ]);
    }
}
