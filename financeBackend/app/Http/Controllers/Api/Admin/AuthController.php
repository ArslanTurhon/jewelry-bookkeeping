<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\AdminUser;
use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'account' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'email', 'required_without:account'],
            'password' => ['required', 'string'],
        ]);

        $account = $data['account'] ?? $data['email'];
        $admin = AdminUser::query()
            ->where('username', $account)
            ->orWhere('email', $account)
            ->first();

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

    public function updateProfile(Request $request)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('admin_users', 'username')->ignore($admin->id),
            ],
        ]);
        $admin->update($data);

        return response()->json(AdminAccess::present($admin->fresh()));
    }

    public function updatePassword(Request $request)
    {
        $admin = AdminAccess::require($request);
        if (! $admin instanceof AdminUser) {
            return $admin;
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);
        if (! Hash::check($data['current_password'], $admin->password)) {
            return response()->json([
                'message' => '原密码不正确',
                'errors' => ['current_password' => ['原密码不正确']],
            ], 422);
        }

        $admin->forceFill([
            'password' => $data['password'],
            'api_token' => null,
        ])->save();

        return response()->json(['message' => 'password updated']);
    }
}
