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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['income', 'expense']);
            $table->string('color', 20)->default('#2563eb');
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->default('store');
            $table->string('key');
            $table->decimal('value', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['scope', 'key']);
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 20);
            $table->string('translation_key');
            $table->text('translation_value');
            $table->timestamps();
            $table->unique(['language_code', 'translation_key']);
            $table->foreign('language_code')->references('code')->on('languages')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('opening_balances');
        Schema::dropIfExists('categories');
    }
};
