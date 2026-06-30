<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->boolean('affects_finance')->default(true)->after('reconciliation_section_id');
            $table->boolean('affects_stock')->default(true)->after('affects_finance');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['affects_finance', 'affects_stock']);
        });
    }
};
