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
        Schema::create('word_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->references('id')->on('word_categories')->onDelete('set null');
            $table->integer('level')->default(0);
            $table->integer('usage_count')->default(0);
            $table->json('emotional_context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('name');
            $table->index('parent_id');
            $table->index('level');
        });

        Schema::create('word_category_items', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100);
            $table->foreignId('category_id')->references('id')->on('word_categories')->onDelete('cascade');
            $table->float('strength')->default(1.0);
            $table->integer('usage_count')->default(0);
            $table->string('context')->nullable();
            $table->json('examples')->nullable();
            $table->string('language', 5)->default('tr');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index('word');
            $table->index('category_id');
            $table->index('language');
            $table->unique(['word', 'category_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_category_items');
        Schema::dropIfExists('word_categories');
    }
}; 