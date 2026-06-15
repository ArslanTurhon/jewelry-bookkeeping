<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\AdminUser;
use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = AdminUser::query()->where('email', $data['email'])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            return response()->json(['message' => '账号或密码错误'], 422);
        }
        if (! $admin->enabled) {
            return response()->json(['message' => '账号已停用'], 422);
        }

        if (! $admin->api_token) {
            $admin->forceFill(['api_token' => Str::random(60)])->save();
        }
        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $admin->api_token,
            'admin' => AdminAccess::present($admin),
        ]);
    }

    public function me(Request $request)
    {
        $admin = AdminAccess::require($request);

        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        return response()->json(AdminAccess::present($admin));
    }

    public function logout(Request $request)
    {
        $admin = AdminAccess::user($request);

        if ($admin) {
            $admin->forceFill(['api_token' => null])->save();
        }

        return response()->json(['message' => 'logged out']);
    }
}
