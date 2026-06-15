<?php

namespace App\Http\Controllers\Api\App;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'string', 'max:255'],
        ]);

        $appid = config('services.wechat.appid');
        $secret = config('services.wechat.secret');

        if (! $appid || ! $secret) {
            return response()->json(['message' => 'WECHAT_APPID 和 WECHAT_SECRET 尚未配置'], 422);
        }

        $response = Http::get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $appid,
            'secret' => $secret,
            'js_code' => $data['code'],
            'grant_type' => 'authorization_code',
        ])->json();

        if (! isset($response['openid'])) {
            return response()->json(['message' => $response['errmsg'] ?? '微信登录失败'], 422);
        }

        $user = User::query()->updateOrCreate(
            ['openid' => $response['openid']],
            [
                'name' => $data['nickname'] ?? '微信用户',
                'avatar_url' => $data['avatar_url'] ?? null,
                'api_token' => Str::random(60),
            ],
        );

        return response()->json([
            'token' => $user->api_token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }
}
