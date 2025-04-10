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
        Schema::create('word_relations', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->index();
            $table->string('related_word', 100)->index();
            $table->enum('relation_type', ['synonym', 'antonym', 'association', 'definition']);
            $table->float('strength')->default(1.0);
            $table->string('context')->nullable();
            $table->string('language', 5)->default('tr');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->unique(['word', 'related_word', 'relation_type', 'language']);
        });

        Schema::create('word_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->index();
            $table->text('definition');
            $table->string('language', 5)->default('tr');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->unique(['word', 'definition', 'language']);
        });

        Schema::create('word_usages', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->index();
            $table->text('usage_example');
            $table->string('language', 5)->default('tr');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_usages');
        Schema::dropIfExists('word_definitions');
        Schema::dropIfExists('word_relations');
    }
};
