<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\Log;

class AIDataCollectorService
{
    /**
     * Kelime geçerli mi kontrol et
     */
    public function isValidWord($word)
    {
        // Null kontrolü
        if ($word === null) {
            return false;
        }
        
        // Boş string kontrolü
        if (empty(trim($word))) {
            return false;
        }
        
        // Minimum uzunluk kontrolü 
        if (strlen($word) < 2) {
            return false;
        }
        
        // Sadece sayı içeriyor mu
        if (is_numeric($word)) {
            return false;
        }
        
        // Özel karakterler içeriyor mu
        if (preg_match('/[^\p{L}\p{N}\s\-]/u', $word)) {
            return false;
        }
        
        // Çok uzun kelimeler geçersiz sayılsın (veritabanı sınırlamaları için)
        if (strlen($word) > 100) {
            return false;
        }
        
        return true;
    }

    private function processAndStoreWord($word, $wordData)
    {
        // Kelime geçerliliğini kontrol et
        if (!$this->isValidWord($word)) {
            Log::warning("Geçersiz kelime: " . $word);
            return false;
        }
        
        // ... existing code ...
    }
} 