<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\AdminUser;
use App\Http\Controllers\Controller;
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

        if (! $admin->api_token) {
            $admin->forceFill(['api_token' => Str::random(60)])->save();
        }

        return response()->json([
            'token' => $admin->api_token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $admin = $this->admin($request);

        if (! $admin) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ]);
    }

    public function logout(Request $request)
    {
        $admin = $this->admin($request);

        if ($admin) {
            $admin->forceFill(['api_token' => null])->save();
        }

        return response()->json(['message' => 'logged out']);
    }

    private function admin(Request $request): ?AdminUser
    {
        $token = $request->bearerToken();

        return $token ? AdminUser::query()->where('api_token', $token)->first() : null;
    }
}
