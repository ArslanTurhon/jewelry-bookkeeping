<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Support\AdminAccess;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $admin = AdminAccess::require($request, 'users');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        return response()->json(AdminUser::query()
            ->orderByDesc('is_super_admin')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50))
            ->through(fn (AdminUser $user) => AdminAccess::present($user)));
    }

    public function permissions(Request $request)
    {
        $admin = AdminAccess::require($request, 'users');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        return response()->json(AdminUser::PERMISSIONS);
    }

    public function store(Request $request)
    {
        $admin = AdminAccess::require($request, 'users');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $data = $this->validatedData($request, true);
        $data['is_super_admin'] = false;

        return response()->json(AdminAccess::present(AdminUser::query()->create($data)), 201);
    }

    public function update(Request $request, AdminUser $adminUser, AuditLogger $audit)
    {
        $admin = AdminAccess::require($request, 'users');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        if ($adminUser->is_super_admin) {
            return response()->json(['message' => '超级管理员不能被修改权限或停用'], 422);
        }

        DB::transaction(function () use ($request, $adminUser, $admin, $audit): void {
            $before = $adminUser->toArray();
            $wasEnabled = $adminUser->enabled;
            $adminUser->update($this->validatedData($request));
            $action = $wasEnabled === $adminUser->enabled
                ? 'user.updated'
                : ($adminUser->enabled ? 'user.enabled' : 'user.disabled');
            $audit->record($admin, $adminUser, $action, null, $before, $adminUser->fresh()->toArray());
        });

        return response()->json(AdminAccess::present($adminUser->fresh()));
    }

    public function destroy(Request $request, AdminUser $adminUser, AuditLogger $audit)
    {
        $admin = AdminAccess::require($request, 'users');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        if ($adminUser->is_super_admin) {
            return response()->json(['message' => '超级管理员不能删除'], 422);
        }

        DB::transaction(function () use ($adminUser, $admin, $audit): void {
            $before = $adminUser->toArray();
            $adminUser->forceFill(['enabled' => false, 'api_token' => null])->save();
            $audit->record($admin, $adminUser, 'user.disabled', null, $before, $adminUser->fresh()->toArray());
        });

        return response()->json(['message' => 'disabled']);
    }

    public function resetPassword(Request $request, AdminUser $adminUser)
    {
        $admin = AdminAccess::require($request, 'users');
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        if ($adminUser->is_super_admin) {
            return response()->json(['message' => '超级管理员密码不能在这里重置'], 422);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ]);
        $adminUser->forceFill(['password' => $data['password'], 'api_token' => null])->save();

        return response()->json(['message' => 'password reset']);
    }

    private function validatedData(Request $request, bool $creating = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('admin_users', 'username')->ignore($request->route('adminUser')),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admin_users', 'email')->ignore($request->route('adminUser')),
            ],
            'enabled' => ['boolean'],
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where('enabled', true)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(array_keys(AdminUser::PERMISSIONS))],
        ];
        if ($creating) {
            $rules['password'] = ['required', 'string', 'min:6', 'max:100'];
        }

        $data = $request->validate($rules);
        $data['enabled'] = $data['enabled'] ?? true;
        $data['permissions'] = array_values(array_diff($data['permissions'] ?? [], ['users']));

        return $data;
    }
}
