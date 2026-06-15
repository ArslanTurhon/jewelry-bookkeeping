<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('business_type', ['sale', 'recycle', 'income', 'operating_expense']);
            $table->enum('payment_account', ['cash', 'online', 'pure_gold_fund']);
            $table->enum('online_method', ['bank', 'wechat', 'alipay'])->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('stock_bucket', ['sale_stock', 'scrap_stock'])->nullable();
            $table->enum('product_type', ['pure_gold', 'pure_silver', 'gold_wrapped'])->nullable();
            $table->enum('wrap_material', ['silver', 'copper'])->nullable();
            $table->decimal('pure_gold_weight', 12, 3)->default(0);
            $table->decimal('wrapped_gold_weight', 12, 3)->default(0);
            $table->decimal('material_weight', 12, 3)->default(0);
            $table->unsignedInteger('material_pieces')->default(0);
            $table->enum('expense_category', ['rent', 'electricity', 'water', 'salary', 'supplies', 'other'])->nullable();
            $table->date('transaction_date');
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->index(['business_type', 'transaction_date']);
            $table->index(['stock_bucket', 'product_type']);
            $table->index(['payment_account', 'online_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
