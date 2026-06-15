<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recycle_prices', function (Blueprint $table): void {
            $table->id();
            $table->date('price_date')->unique();
            $table->decimal('reference_gold_price', 12, 2)->default(0);
            $table->decimal('reference_silver_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycle_prices');
    }
};
