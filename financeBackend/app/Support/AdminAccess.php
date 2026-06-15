<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Http\Request;

class AdminAccess
{
    public static function user(Request $request): ?AdminUser
    {
        $token = $request->bearerToken();

        return $token ? AdminUser::query()->where('api_token', $token)->first() : null;
    }

    public static function require(Request $request, ?string $permission = null)
    {
        $admin = self::user($request);

        if (! $admin || ! $admin->enabled) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($permission && ! $admin->hasPermission($permission)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $admin;
    }

    public static function present(AdminUser $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'is_super_admin' => $admin->is_super_admin,
            'enabled' => $admin->enabled,
            'permissions' => $admin->visiblePermissions(),
            'last_login_at' => $admin->last_login_at,
        ];
    }
}
