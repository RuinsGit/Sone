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
        Schema::table('word_category_items', function (Blueprint $table) {
            // Nov/tür alanı ekleniyor (öğrenilen kelimenin türünü belirtir)
            $table->string('nov', 50)->nullable()->after('word')->comment('Kelimenin türü (isim, fiil, sıfat vb.)');
            $table->index('nov');
            
            // Tekil kısıt kaldırılıp yerine kelimenin türünü de içeren kısıt ekleniyor
            $table->dropUnique(['word', 'category_id', 'language']);
            $table->unique(['word', 'nov', 'category_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('word_category_items', function (Blueprint $table) {
            // Değişiklikleri geri al
            $table->dropUnique(['word', 'nov', 'category_id', 'language']);
            $table->unique(['word', 'category_id', 'language']);
            $table->dropIndex(['nov']);
            $table->dropColumn('nov');
        });
    }
};
