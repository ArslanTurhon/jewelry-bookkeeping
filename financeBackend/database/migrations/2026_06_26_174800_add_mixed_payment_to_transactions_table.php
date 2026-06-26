<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY payment_account ENUM('cash', 'online', 'pure_gold_fund', 'mixed') NOT NULL");

        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('cash_amount', 12, 2)->default(0)->after('amount');
            $table->decimal('online_amount', 12, 2)->default(0)->after('cash_amount');
        });

        DB::table('transactions')
            ->where('payment_account', 'cash')
            ->update(['cash_amount' => DB::raw('amount')]);

        DB::table('transactions')
            ->where('payment_account', 'online')
            ->update(['online_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        DB::table('transactions')
            ->where('payment_account', 'mixed')
            ->update([
                'payment_account' => 'online',
                'online_amount' => DB::raw('amount'),
                'cash_amount' => 0,
            ]);

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['cash_amount', 'online_amount']);
        });

        DB::statement("ALTER TABLE transactions MODIFY payment_account ENUM('cash', 'online', 'pure_gold_fund') NOT NULL");
    }
};
