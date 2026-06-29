<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('admin_users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('name');
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_admin_id')->nullable()->after('user_id')->constrained('admin_users')->nullOnDelete();
            $table->index(['store_id', 'transaction_date']);
        });

        Schema::table('opening_balances', function (Blueprint $table): void {
            $table->dropUnique('opening_balances_scope_key_unique');
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unique(['store_id', 'scope', 'key']);
        });

        Schema::table('recycle_prices', function (Blueprint $table): void {
            $table->dropUnique('recycle_prices_price_date_unique');
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unique(['store_id', 'price_date']);
        });

        $now = now();
        $storeId = DB::table('stores')->insertGetId([
            'name' => '总店',
            'enabled' => true,
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('transactions')->whereNull('store_id')->update(['store_id' => $storeId]);
        DB::table('opening_balances')->whereNull('store_id')->update(['store_id' => $storeId]);
        DB::table('recycle_prices')->whereNull('store_id')->update(['store_id' => $storeId]);

        DB::table('admin_users')->orderBy('id')->get()->each(function (object $user): void {
            $base = Str::of((string) $user->email)->before('@')->slug()->value() ?: 'user-'.$user->id;
            $username = $base;
            $suffix = 1;
            while (DB::table('admin_users')->where('username', $username)->exists()) {
                $username = $base.'-'.$suffix++;
            }
            DB::table('admin_users')->where('id', $user->id)->update(['username' => $username]);
        });
    }

    public function down(): void
    {
        Schema::table('recycle_prices', function (Blueprint $table): void {
            $table->dropUnique(['store_id', 'price_date']);
            $table->dropConstrainedForeignId('store_id');
            $table->unique('price_date');
        });

        Schema::table('opening_balances', function (Blueprint $table): void {
            $table->dropUnique(['store_id', 'scope', 'key']);
            $table->dropConstrainedForeignId('store_id');
            $table->unique(['scope', 'key']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['store_id', 'transaction_date']);
            $table->dropConstrainedForeignId('recorded_by_admin_id');
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::table('admin_users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });

        Schema::dropIfExists('stores');
    }
};
