<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_data', function (Blueprint $table) {
            $table->id();
            $table->string('word');                    // Kelime
            $table->text('sentence')->nullable();      // Cümle
            $table->string('category');               // Kategori
            $table->text('context')->nullable();      // Bağlam
            $table->string('language')->default('tr'); // Dil
            $table->integer('frequency')->default(0);  // Kullanım sıklığı
            $table->float('confidence')->default(0);   // Güven skoru
            $table->json('related_words')->nullable(); // İlişkili kelimeler
            $table->json('usage_examples')->nullable();// Kullanım örnekleri
            $table->json('emotional_context')->nullable(); // Duygusal bağlam
            $table->json('metadata')->nullable();      // Ek bilgiler
            $table->timestamps();
            
            // İndeksler
            $table->index('word');
            $table->index('category');
            $table->index('language');
            $table->index('frequency');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_data');
    }
}; 