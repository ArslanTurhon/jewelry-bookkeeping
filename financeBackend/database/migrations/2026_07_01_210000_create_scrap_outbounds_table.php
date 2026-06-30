<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrap_outbounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_admin_id')->constrained('admin_users')->restrictOnDelete();
            $table->string('product_type');
            $table->string('wrap_material')->nullable();
            $table->decimal('pure_gold_weight', 12, 3)->default(0);
            $table->decimal('wrapped_gold_weight', 12, 3)->default(0);
            $table->decimal('material_weight', 12, 3)->default(0);
            $table->unsignedInteger('material_pieces')->default(0);
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('received_amount', 14, 2);
            $table->string('payment_account');
            $table->string('online_method')->nullable();
            $table->json('fees')->nullable();
            $table->decimal('cost_amount', 14, 2);
            $table->decimal('profit_amount', 14, 2);
            $table->date('outbound_date');
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_outbounds');
    }
};
