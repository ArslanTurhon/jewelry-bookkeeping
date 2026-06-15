<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('api_token');
            $table->boolean('enabled')->default(true)->after('is_super_admin');
            $table->json('permissions')->nullable()->after('enabled');
            $table->timestamp('last_login_at')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table): void {
            $table->dropColumn(['is_super_admin', 'enabled', 'permissions', 'last_login_at']);
        });
    }
};
