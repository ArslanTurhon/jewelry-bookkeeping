<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('reconciliation_date');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['store_id', 'reconciliation_date']);
        });

        Schema::create('reconciliation_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->string('section_type');
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('no_business')->default(false);
            $table->json('business_summary')->nullable();
            $table->json('actual_snapshot')->nullable();
            $table->json('book_snapshot')->nullable();
            $table->json('differences')->nullable();
            $table->text('difference_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestamps();
            $table->unique(['daily_reconciliation_id', 'section_type']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('reconciliation_section_id')->nullable()
                ->constrained('reconciliation_sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reconciliation_section_id');
        });
        Schema::dropIfExists('reconciliation_sections');
        Schema::dropIfExists('daily_reconciliations');
    }
};
