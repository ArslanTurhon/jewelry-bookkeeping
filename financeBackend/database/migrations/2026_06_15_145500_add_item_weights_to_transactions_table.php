<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('transactions', 'item_weights')) {
                $table->json('item_weights')->nullable()->after('material_pieces');
            }
            if (! Schema::hasColumn('transactions', 'gold_unit_price')) {
                $table->decimal('gold_unit_price', 12, 2)->nullable()->after('item_weights');
            }
            if (! Schema::hasColumn('transactions', 'silver_unit_price')) {
                $table->decimal('silver_unit_price', 12, 2)->nullable()->after('gold_unit_price');
            }
            if (! Schema::hasColumn('transactions', 'reference_gold_price')) {
                $table->decimal('reference_gold_price', 12, 2)->nullable()->after('silver_unit_price');
            }
            if (! Schema::hasColumn('transactions', 'reference_silver_price')) {
                $table->decimal('reference_silver_price', 12, 2)->nullable()->after('reference_gold_price');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY business_type ENUM('sale', 'recycle', 'income', 'operating_expense') NOT NULL");
            DB::statement("ALTER TABLE transactions MODIFY payment_account ENUM('cash', 'online', 'pure_gold_fund') NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn([
                'item_weights',
                'gold_unit_price',
                'silver_unit_price',
                'reference_gold_price',
                'reference_silver_price',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY business_type ENUM('sale', 'recycle', 'operating_expense') NOT NULL");
            DB::statement("ALTER TABLE transactions MODIFY payment_account ENUM('cash', 'online') NOT NULL");
        }
    }
};
