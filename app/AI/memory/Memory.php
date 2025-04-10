<?php

namespace App\AI\Memory;

class Memory
{
    private $shortTermMemory = [];
    private $longTermMemory = [];
    private $proceduralMemory = [];
    private $semanticMemory = [];
    private $maxCapacity = 100000;
    
    public function __construct()
    {
        $this->initializeMemorySystems();
    }
    
    private function initializeMemorySystems()
    {
        // Hafıza sistemlerini başlat
    }
    
    public function store($data, $type = 'short_term')
    {
        switch($type) {
            case 'short_term':
                $this->shortTermMemory[] = $data;
                break;
            case 'long_term':
                $this->longTermMemory[] = $data;
                break;
            case 'procedural':
                $this->proceduralMemory[] = $data;
                break;
            case 'semantic':
                $this->semanticMemory[] = $data;
                break;
        }
    }
    
    public function search($query)
    {
        $results = [];
        
        // Kısa süreli hafızada ara
        $results['short_term'] = $this->searchInMemory($this->shortTermMemory, $query);
        
        // Uzun süreli hafızada ara
        $results['long_term'] = $this->searchInMemory($this->longTermMemory, $query);
        
        // Anlamsal hafızada ara
        $results['semantic'] = $this->searchInMemory($this->semanticMemory, $query);
        
        return $results;
    }
    
    private function searchInMemory($memory, $query)
    {
        // Basit arama algoritması
        $results = [];
        foreach($memory as $item) {
            if(strpos($item, $query) !== false) {
                $results[] = $item;
            }
        }
        return $results;
    }
    
    public function consolidate()
    {
        // Kısa süreli hafızadan uzun süreli hafızaya aktarım
        foreach($this->shortTermMemory as $memory) {
            if($this->shouldConsolidate($memory)) {
                $this->longTermMemory[] = $memory;
            }
        }
        
        // Kısa süreli hafızayı temizle
        $this->shortTermMemory = [];
    }
    
    private function shouldConsolidate($memory)
    {
        // Konsolidasyon kriterleri
        return true; // Şimdilik hepsini aktar
    }
    
    /**
     * Uzun süreli hafızayı getir
     */
    public function getLongTermMemory()
    {
        try {
            // null kontrolü
            if ($this->longTermMemory === null) {
                return [];
            }
            
            // array değilse kontrol et
            if (!is_array($this->longTermMemory)) {
                // Log hatayı
                \Log::error('Uzun süreli hafıza bir dizi değil: ' . gettype($this->longTermMemory));
                return [];
            }
            
            return $this->longTermMemory;
        } catch (\Exception $e) {
            \Log::error('Uzun süreli hafıza alma hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Kısa süreli hafızayı getir
     */
    public function getShortTermMemory()
    {
        try {
            // null kontrolü
            if ($this->shortTermMemory === null) {
                return [];
            }
            
            // array değilse kontrol et
            if (!is_array($this->shortTermMemory)) {
                // Log hatayı
                \Log::error('Kısa süreli hafıza bir dizi değil: ' . gettype($this->shortTermMemory));
                return [];
            }
            
            return $this->shortTermMemory;
        } catch (\Exception $e) {
            \Log::error('Kısa süreli hafıza alma hatası: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Hafıza kullanım yüzdesini hesapla
     */
    public function getUsagePercentage()
    {
        try {
            $totalItems = count($this->shortTermMemory ?? []) + 
                         count($this->longTermMemory ?? []) + 
                         count($this->proceduralMemory ?? []) + 
                         count($this->semanticMemory ?? []);
            
            if ($this->maxCapacity <= 0) {
                $this->maxCapacity = 100000; // Varsayılan değer
            }
            
            return round(($totalItems / $this->maxCapacity) * 100);
        } catch (\Exception $e) {
            \Log::error('Hafıza kullanımı hesaplama hatası: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Son öğrenme sorusunu getir
     */
    public function getLastLearningQuestion()
    {
        try {
            $shortTermMemory = $this->getShortTermMemory();
            
            if (empty($shortTermMemory)) {
                return null;
            }
            
            foreach ($shortTermMemory as $memory) {
                if (is_array($memory) && isset($memory['type']) && $memory['type'] === 'learning_question') {
                    return $memory;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::error('Son öğrenme sorusu getirme hatası: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Öğrenme sorularını temizle
     */
    public function clearLearningQuestions()
    {
        try {
            $shortTermMemory = $this->getShortTermMemory();
            $updatedMemory = [];
            
            foreach ($shortTermMemory as $memory) {
                if (!isset($memory['type']) || $memory['type'] !== 'learning_question') {
                    $updatedMemory[] = $memory;
                }
            }
            
            $this->shortTermMemory = $updatedMemory;
            
            try {
                $this->saveMemory();
            } catch (\Exception $e) {
                \Log::error('Hafıza kaydetme hatası: ' . $e->getMessage());
                // Kaydetme hatası olsa bile temizleme işlemi yapıldı
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Öğrenme soruları temizleme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hafızayı yedekle/kaydet
     */
    private function saveMemory()
    {
        try {
            // Burada Cache veya dosya sistemine kaydetme işlemleri yapılabilir
            // Şimdilik boş bırakıldı, çünkü hafıza şu an sadece bellekte tutuluyor
            return true;
        } catch (\Exception $e) {
            \Log::error('Hafıza kaydetme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    public function setMaxCapacity($capacity)
    {
        $this->maxCapacity = $capacity;
    }
} 