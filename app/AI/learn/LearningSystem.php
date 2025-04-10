<?php

namespace App\AI\Learn;

use App\Models\AIData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LearningSystem
{
    private $isTraining = false;
    private $learningRate = 0.1;
    private $progress = 0;
    private $lastTrainingTime;
    private $knowledgeBase = [
        'patterns' => [],
        'rules' => [],
        'concepts' => []
    ];
    
    public function __construct()
    {
        $this->lastTrainingTime = now();
        $this->loadKnowledgeBase();
    }
    
    /**
     * Öğrenme oranını ayarla
     */
    public function setLearningRate($rate)
    {
        $this->learningRate = max(0.01, min(1, (float)$rate));
    }
    
    /**
     * Bilgi tabanını yükle
     */
    private function loadKnowledgeBase()
    {
        $this->knowledgeBase = Cache::get('ai_knowledge_base', [
            'patterns' => [],
            'rules' => [],
            'concepts' => []
        ]);
    }
    
    /**
     * Bilgi tabanını kaydet
     */
    private function saveKnowledgeBase()
    {
        Cache::put('ai_knowledge_base', $this->knowledgeBase, now()->addWeek());
    }
    
    /**
     * Eğitim verilerini kullanarak sistemi eğit
     */
    public function train($trainingData = [])
    {
        $this->isTraining = true;
        $this->progress = 0;
        $totalItems = count($trainingData);
        
        if (empty($trainingData)) {
            // Varsayılan eğitim verisi yoksa veritabanından al
            $trainingData = $this->getDefaultTrainingData();
            $totalItems = count($trainingData);
        }
        
        try {
            foreach ($trainingData as $index => $data) {
                // Her bir veri için öğrenme işlemi
                $this->learnFromData($data);
                
                // İlerlemeyi güncelle
                $this->progress = ($index + 1) / $totalItems;
            }
            
            // Eğitim tamamlandı
            $this->isTraining = false;
            $this->lastTrainingTime = now();
            $this->saveKnowledgeBase();
            
            return true;
            
        } catch (\Exception $e) {
            $this->isTraining = false;
            Log::error('Eğitim hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tek bir veri öğesinden öğren
     */
    private function learnFromData($data)
    {
        if (!isset($data['input']) || empty($data['input'])) {
            return;
        }
        
        // Girdiden kalıplar çıkar
        $patterns = $this->extractPatterns($data['input']);
        
        foreach ($patterns as $pattern) {
            if (!isset($this->knowledgeBase['patterns'][$pattern])) {
                $this->knowledgeBase['patterns'][$pattern] = [
                    'frequency' => 0,
                    'contexts' => [],
                    'outputs' => []
                ];
            }
            
            // Frekansı artır
            $this->knowledgeBase['patterns'][$pattern]['frequency']++;
            
            // Bağlamları kaydet
            if (isset($data['context'])) {
                $this->knowledgeBase['patterns'][$pattern]['contexts'][] = $data['context'];
                // Maksimum 10 bağlam tut
                $this->knowledgeBase['patterns'][$pattern]['contexts'] = array_slice(
                    $this->knowledgeBase['patterns'][$pattern]['contexts'], 
                    -10
                );
            }
            
            // Çıktıları kaydet
            if (isset($data['output'])) {
                $this->knowledgeBase['patterns'][$pattern]['outputs'][$data['output']] = 
                    ($this->knowledgeBase['patterns'][$pattern]['outputs'][$data['output']] ?? 0) + 1;
            }
        }
        
        // Veritabanına kaydet
        $this->storeToDatabase($data);
    }
    
    /**
     * Metinden kalıplar çıkar
     */
    public function extractPatterns($text)
    {
        $patterns = [];
        
        // Metni kelimelere ayır
        $words = preg_split('/\s+/', strtolower($text));
        
        // Tek kelimeler
        foreach ($words as $word) {
            if (strlen($word) > 2) { // 2 harften uzun kelimeleri al
                $patterns[] = $word;
            }
        }
        
        // İkili kelime grupları
        for ($i = 0; $i < count($words) - 1; $i++) {
            $pattern = $words[$i] . ' ' . $words[$i + 1];
            $patterns[] = $pattern;
        }
        
        return $patterns;
    }
    
    /**
     * Öğrenilen bilgileri veritabanına kaydet
     */
    private function storeToDatabase($data)
    {
        // Girdiden kelimeleri çıkar
        $words = explode(' ', strtolower($data['input']));
        
        foreach ($words as $word) {
            if (strlen($word) < 2) continue; // Çok kısa kelimeleri atla
            
            // Kelimeyi temizle
            $word = trim($word);
            
            try {
                // Kelimeyi veritabanına ekle veya güncelle
                AIData::updateOrCreate(
                    ['word' => $word],
                    [
                        'category' => $data['context']['category'] ?? 'general',
                        'context' => $data['context']['context'] ?? null,
                        'language' => 'tr'
                    ]
                );
                
                // Frekansı artır
                DB::table('ai_data')
                    ->where('word', $word)
                    ->increment('frequency');
                    
            } catch (\Exception $e) {
                Log::error('Kelime kaydetme hatası: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Varsayılan eğitim verilerini al
     */
    private function getDefaultTrainingData()
    {
        try {
            $words = AIData::select('word', 'sentence', 'category', 'context')
                ->where('language', 'tr')
                ->where('frequency', '>', 0)
                ->limit(200)
                ->get();
                
            $trainingData = [];
            
            foreach ($words as $word) {
                $trainingData[] = [
                    'input' => $word->word,
                    'output' => $word->sentence ?? $word->word,
                    'context' => [
                        'category' => $word->category,
                        'context' => $word->context
                    ]
                ];
            }
            
            return $trainingData;
            
        } catch (\Exception $e) {
            Log::error('Eğitim verisi alma hatası: ' . $e->getMessage());
            
            // Hata durumunda basit veri seti döndür
            return [
                [
                    'input' => 'öğrenme',
                    'output' => 'Öğrenme sürecindeyim.',
                    'context' => ['category' => 'learning']
                ]
            ];
        }
    }
    
    /**
     * Benzer kalıpları bul
     */
    public function findSimilarPatterns($input)
    {
        $input = strtolower($input);
        $patterns = $this->extractPatterns($input);
        $foundPatterns = [];
        
        foreach ($patterns as $pattern) {
            if (isset($this->knowledgeBase['patterns'][$pattern])) {
                $foundPatterns[$pattern] = $this->knowledgeBase['patterns'][$pattern];
            }
        }
        
        // Cümleler oluşturmak için en sık kullanılan kalıpları al
        if (!empty($foundPatterns)) {
            $outputs = [];
            
            foreach ($foundPatterns as $pattern => $data) {
                foreach ($data['outputs'] as $output => $frequency) {
                    if (!isset($outputs[$output])) {
                        $outputs[$output] = 0;
                    }
                    $outputs[$output] += $frequency;
                }
            }
            
            // Frekansa göre sırala
            arsort($outputs);
            
            // En yüksek frekanslı çıktıları al
            $topOutputs = array_slice($outputs, 0, 5, true);
            
            $results = [];
            foreach ($topOutputs as $output => $frequency) {
                $results[] = [
                    'output' => $output,
                    'frequency' => $frequency,
                    'confidence' => min(1, $frequency / 10),
                    'emotion' => 'neutral'
                ];
            }
            
            return $results;
        }
        
        // Eğer hiç kalıp bulunamazsa veritabanından ara
        return $this->searchInDatabase($input);
    }
    
    /**
     * Veritabanında ara
     */
    private function searchInDatabase($input)
    {
        $words = explode(' ', strtolower($input));
        $results = [];
        
        foreach ($words as $word) {
            if (strlen($word) < 3) continue;
            
            try {
                $aiData = AIData::where('word', 'LIKE', "%{$word}%")
                    ->whereNotNull('sentence')
                    ->orderBy('frequency', 'desc')
                    ->limit(3)
                    ->get();
                    
                foreach ($aiData as $data) {
                    if (!empty($data->sentence)) {
                        $results[] = [
                            'output' => $data->sentence,
                            'frequency' => $data->frequency,
                            'confidence' => min(1, $data->frequency / 20),
                            'emotion' => 'neutral'
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Veritabanı arama hatası: ' . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Öğrenme sistemini güncelle
     */
    public function update($input, $metadata = [])
    {
        // Girdiden öğren
        $this->learnFromData([
            'input' => $input,
            'context' => $metadata['emotional_context'] ?? null,
            'output' => $metadata['output'] ?? null
        ]);
        
        return true;
    }
    
    /**
     * Eğitimi başlat
     */
    public function startTraining()
    {
        if ($this->isTraining) {
            return false;
        }
        
        // Otomatik eğitimi başlat
        return $this->train();
    }
    
    /**
     * Durumu kontrol et
     */
    public function getStatus()
    {
        return [
            'is_training' => $this->isTraining,
            'progress' => $this->progress * 100, // Yüzde olarak
            'last_training' => $this->lastTrainingTime ? $this->lastTrainingTime->diffForHumans() : 'Hiç',
            'learning_rate' => $this->learningRate,
            'knowledge_base_size' => [
                'patterns' => count($this->knowledgeBase['patterns']),
                'rules' => count($this->knowledgeBase['rules']),
                'concepts' => count($this->knowledgeBase['concepts'])
            ]
        ];
    }
    
    /**
     * İlerleme durumunu al
     */
    public function getProgress()
    {
        return $this->progress * 100; // Yüzde olarak
    }
    
    /**
     * Sürekli öğrenme işlemi
     */
    public function continuousLearning($options = [])
    {
        // Son eğitimden bu yana 5 dakika geçmediyse atlayalım
        $lastTraining = $this->lastTrainingTime;
        $limit = $options['limit'] ?? 100;
        $force = $options['force'] ?? false;
        
        if (!$force && $lastTraining->diffInMinutes(now()) < 5) {
            return [
                'success' => false,
                'message' => 'Son eğitimden bu yana yeterli süre geçmedi, atlıyoruz.'
            ];
        }
        
        try {
            // Öğrenilecek yeni veriler var mı kontrol edelim
            $newData = AIData::where('updated_at', '>', $lastTraining)
                ->limit($limit)
                ->get();
                
            // Yeni veri yoksa rasgele veri seçelim
            if ($newData->count() == 0) {
                return $this->learnFromRandomData($limit);
            }
            
            $learnedItems = 0;
            $learnedPatterns = [];
            $relations = [
                'synonyms' => 0,
                'antonyms' => 0,
                'associations' => 0
            ];
            
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            foreach ($newData as $data) {
                // Her veriyi öğrenme sistemine ekle
                $this->learnFromData([
                    'input' => $data->word,
                    'output' => $data->sentence,
                    'context' => [
                        'category' => $data->category,
                        'context' => $data->context
                    ]
                ]);
                
                // İlişkileri bul ve kur
                if (!empty($data->sentence)) {
                    // Kelime tanımlarını öğren
                    $wordRelations->learnFromContextualData($data->word, ['category' => $data->category], $data->sentence);
                    
                    // Cümledeki diğer kelimeleri bul ve ilişkileri kur
                    $words = $this->extractPatterns($data->sentence);
                    foreach ($words as $relatedWord) {
                        if ($relatedWord != $data->word) {
                            $relationResult = $wordRelations->learnAssociation($data->word, $relatedWord, 'related', 0.4);
                            if ($relationResult) {
                                $relations['associations']++;
                            }
                        }
                    }
                }
                
                // Eş anlamlı ve zıt anlamlı kelimeleri bulmak için analiz yap
                $this->analyzeForSynonymsAndAntonyms($data->word, $data->sentence, $wordRelations, $relations);
                
                $learnedItems++;
                
                // Öğrenilen kalıpları kaydet
                $learnedPatterns[$data->word] = [
                    'frequency' => $data->frequency ?? 1,
                    'confidence' => min(1, ($data->frequency ?? 1) / 20)
                ];
            }
            
            // Bilgi tabanını güncelle
            $this->lastTrainingTime = now();
            $this->saveKnowledgeBase();
            
            return [
                'success' => true,
                'learned_items' => $learnedItems,
                'patterns' => $learnedPatterns,
                'relations' => $relations
            ];
            
        } catch (\Exception $e) {
            Log::error('Sürekli öğrenme hatası: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Öğrenme sırasında hata: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Rasgele verilerden öğren
     */
    private function learnFromRandomData($limit = 50)
    {
        try {
            // Rasgele veri seç
            $randomData = AIData::inRandomOrder()
                ->limit($limit)
                ->get();
                
            if ($randomData->count() == 0) {
                return [
                    'success' => false,
                    'message' => 'Öğrenilecek veri bulunamadı.'
                ];
            }
            
            $learnedItems = 0;
            $learnedPatterns = [];
            $relations = [
                'synonyms' => 0,
                'antonyms' => 0,
                'associations' => 0
            ];
            
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            foreach ($randomData as $data) {
                // Her veriyi öğrenme sistemine ekle
                $this->learnFromData([
                    'input' => $data->word,
                    'output' => $data->sentence,
                    'context' => [
                        'category' => $data->category,
                        'context' => $data->context
                    ]
                ]);
                
                // İlişkileri bul ve kur
                if (!empty($data->sentence)) {
                    // Kelime tanımlarını öğren
                    $wordRelations->learnFromContextualData($data->word, ['category' => $data->category], $data->sentence);
                    
                    // Cümledeki diğer kelimeleri bul ve ilişkileri kur
                    $words = $this->extractPatterns($data->sentence);
                    foreach ($words as $relatedWord) {
                        if ($relatedWord != $data->word) {
                            $relationResult = $wordRelations->learnAssociation($data->word, $relatedWord, 'related', 0.3);
                            if ($relationResult) {
                                $relations['associations']++;
                            }
                        }
                    }
                }
                
                // Eş anlamlı ve zıt anlamlı kelimeleri bulmak için analiz yap
                $this->analyzeForSynonymsAndAntonyms($data->word, $data->sentence, $wordRelations, $relations);
                
                $learnedItems++;
                
                // Öğrenilen kalıpları kaydet
                $learnedPatterns[$data->word] = [
                    'frequency' => $data->frequency ?? 1,
                    'confidence' => min(1, ($data->frequency ?? 1) / 20)
                ];
            }
            
            // Bilgi tabanını güncelle
            $this->lastTrainingTime = now();
            $this->saveKnowledgeBase();
            
            return [
                'success' => true,
                'learned_items' => $learnedItems,
                'patterns' => $learnedPatterns,
                'relations' => $relations,
                'type' => 'random'
            ];
            
        } catch (\Exception $e) {
            Log::error('Rasgele öğrenme hatası: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Rasgele öğrenme sırasında hata: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cümleyi analiz ederek eş anlamlı ve zıt anlamlı kelimeleri bulmaya çalışır
     */
    private function analyzeForSynonymsAndAntonyms($word, $sentence, $wordRelations, &$relations)
    {
        if (empty($sentence)) return;
        
        // Eş anlamlı kelime belirteçleri
        $synonymPatterns = [
            '/\byani\b/',
            '/\beş anlamlı\b/',
            '/\bgibi\b/',
            '/\baynı\b/',
            '/\bbenzer\b/',
            '/\beşdeğer\b/'
        ];
        
        // Zıt anlamlı kelime belirteçleri
        $antonymPatterns = [
            '/\bzıt\b/',
            '/\bkarşıt\b/',
            '/\baksi\b/',
            '/\btersine\b/',
            '/\bdeğil\b/'
        ];
        
        // Kelime adaylarını bul
        $candidates = preg_split('/[\s,;\.]+/', $sentence);
        
        // Eş anlamlı kontrolleri
        foreach ($synonymPatterns as $pattern) {
            if (preg_match($pattern, $sentence)) {
                // Muhtemel eş anlamlı bulundu
                foreach ($candidates as $candidate) {
                    if (strlen($candidate) > 2 && $candidate != $word) {
                        $result = $wordRelations->learnSynonym($word, $candidate, 0.6);
                        if ($result) {
                            $relations['synonyms']++;
                        }
                    }
                }
            }
        }
        
        // Zıt anlamlı kontrolleri
        foreach ($antonymPatterns as $pattern) {
            if (preg_match($pattern, $sentence)) {
                // Muhtemel zıt anlamlı bulundu
                foreach ($candidates as $candidate) {
                    if (strlen($candidate) > 2 && $candidate != $word) {
                        $result = $wordRelations->learnAntonym($word, $candidate, 0.6);
                        if ($result) {
                            $relations['antonyms']++;
                        }
                    }
                }
            }
        }
    }
} 