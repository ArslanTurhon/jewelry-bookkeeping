<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('action');
            $table->string('reason', 500)->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['store_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->timestamp('voided_at')->nullable()->after('remark');
            $table->foreignId('voided_by_admin_id')->nullable()->after('voided_at')->constrained('admin_users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable()->after('voided_by_admin_id');
            $table->index(['store_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['store_id', 'voided_at']);
            $table->dropConstrainedForeignId('voided_by_admin_id');
            $table->dropColumn(['voided_at', 'void_reason']);
        });

        Schema::dropIfExists('audit_logs');
    }
};
