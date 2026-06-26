<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('transactions', 'recycle_price_rate')) {
                $table->decimal('recycle_price_rate', 5, 2)->default(100)->after('online_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('transactions', 'recycle_price_rate')) {
                $table->dropColumn('recycle_price_rate');
            }
        });
    }
};
