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
        Schema::create('training_patterns', function (Blueprint $table) {
            $table->id();
            $table->text('input')->comment('Giriş metni');
            $table->text('output')->comment('Çıkış metni');
            $table->string('category', 100)->nullable()->comment('Kategori bilgisi');
            $table->text('context')->nullable()->comment('Bağlam bilgisi');
            $table->tinyInteger('priority')->default(5)->comment('Öncelik (1-10)');
            $table->boolean('is_active')->default(true)->comment('Aktif mi?');
            $table->timestamps();
            
            // İndeksler
            $table->index('category');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_patterns');
    }
};
