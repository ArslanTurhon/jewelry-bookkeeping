<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminUser extends Authenticatable
{
    public const PERMISSIONS = [
        'dashboard' => '首页',
        'transactions' => '流水',
        'recycle_pure_gold' => '纯金回收',
        'recycle_gold_wrapped' => '金包银回收',
        'opening' => '期初',
        'recycle_price' => '参考价',
        'users' => '用户管理',
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'api_token',
        'is_super_admin',
        'enabled',
        'permissions',
        'last_login_at',
        'store_id',
    ];

    protected $hidden = ['password', 'api_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'enabled' => 'boolean',
            'permissions' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_super_admin || in_array($permission, $this->permissions ?? [], true);
    }

    public function visiblePermissions(): array
    {
        return $this->is_super_admin ? array_keys(self::PERMISSIONS) : ($this->permissions ?? []);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
