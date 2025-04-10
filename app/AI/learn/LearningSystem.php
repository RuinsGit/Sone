<?php

namespace App\AI\Learn;

use App\AI\Core\CategoryManager;
use App\AI\Core\WordRelations;
use App\Models\AIData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LearningSystem
{
    private $categoryManager;
    private $wordRelations;
    private $apiKeys = [];
    private $requestLimits = [];
    private $errorLog = [];
    private $isLearning = false;
    private $wordLimit = 0;
    private $wordsLearned = 0;
    private $startTime = null;
    private $language = 'tr';
    
    /**
     * LearningSystem constructor.
     * 
     * @param CategoryManager $categoryManager
     * @param WordRelations $wordRelations
     */
    public function __construct(CategoryManager $categoryManager, WordRelations $wordRelations)
    {
        $this->categoryManager = $categoryManager;
        $this->wordRelations = $wordRelations;
        $this->loadApiKeys();
    }
    
    /**
     * API anahtarlarını yükle
     */
    private function loadApiKeys()
    {
        // Örnek API anahtarları (gerçek projede env dosyasından alınmalı)
        $this->apiKeys = [
            'tdk' => env('TDK_API_KEY', ''),
            'google' => env('GOOGLE_API_KEY', ''),
            'oxford' => env('OXFORD_API_KEY', ''),
            'wiktionary' => env('WIKTIONARY_API_KEY', '')
        ];
        
        // İstek limitleri
        $this->requestLimits = [
            'tdk' => [
                'daily' => 1000,
                'used' => 0,
                'reset' => strtotime('tomorrow')
            ],
            'google' => [
                'daily' => 100,
                'used' => 0,
                'reset' => strtotime('tomorrow')
            ],
            'oxford' => [
                'daily' => 50,
                'used' => 0,
                'reset' => strtotime('tomorrow')
            ],
            'wiktionary' => [
                'daily' => 500,
                'used' => 0,
                'reset' => strtotime('tomorrow')
            ]
        ];
    }
    
    /**
     * Öğrenme işlemini başlat
     * 
     * @param int $wordLimit Öğrenilecek maksimum kelime sayısı
     * @return array İşlem sonucu
     */
    public function startLearning($wordLimit = 100)
    {
        if ($this->isLearning) {
            return [
                'success' => false,
                'message' => 'Öğrenme işlemi zaten devam ediyor.'
            ];
        }
        
        try {
            $this->isLearning = true;
            $this->wordLimit = $wordLimit;
            $this->wordsLearned = 0;
            $this->startTime = time();
            $this->errorLog = [];
            
            // Öğrenilecek kelimeleri al
            $words = $this->getWordsToLearn($wordLimit);
            $totalWords = count($words);
            
            if ($totalWords == 0) {
                $this->isLearning = false;
                return [
                    'success' => false,
                    'message' => 'Öğrenilecek kelime bulunamadı.'
                ];
            }
            
            // Kelimeleri öğren
            foreach ($words as $word) {
                $result = $this->learnWord($word);
                
                if ($result['success']) {
                    $this->wordsLearned++;
                } else {
                    $this->errorLog[] = [
                        'word' => $word,
                        'error' => $result['message']
                    ];
                }
                
                // Kelime limiti kontrolü
                if ($this->wordsLearned >= $this->wordLimit) {
                    break;
                }
            }
            
            $this->isLearning = false;
            
            return [
                'success' => true,
                'message' => $this->wordsLearned . ' kelime öğrenildi',
                'learned' => $this->wordsLearned,
                'total' => $totalWords,
                'duration' => time() - $this->startTime,
                'errors' => count($this->errorLog)
            ];
            
        } catch (\Exception $e) {
            $this->isLearning = false;
            Log::error('Öğrenme başlatma hatası: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Öğrenme hatası: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Belirli bir kelimeyi öğren
     * 
     * @param string $word Öğrenilecek kelime
     * @return array İşlem sonucu
     */
    public function learnWord($word)
    {
        if (empty($word)) {
            return [
                'success' => false,
                'message' => 'Kelime boş olamaz'
            ];
        }
        
        try {
            // TDK'dan verileri topla
            $tdkData = $this->collectFromTDK($word);
            
            // Wikipedia'dan verileri topla
            $wikipediaData = $this->collectFromWikipedia($word);
            
            // Google'dan verileri topla
            $googleData = $this->collectFromGoogle($word);
            
            // Toplanan verileri birleştir
            $data = [
                'definitions' => array_merge(
                    $tdkData['definitions'] ?? [], 
                    $wikipediaData['definitions'] ?? []
                ),
                'examples' => array_merge(
                    $tdkData['examples'] ?? [], 
                    $wikipediaData['examples'] ?? [], 
                    $googleData['examples'] ?? []
                ),
                'synonyms' => array_merge(
                    $tdkData['synonyms'] ?? [], 
                    $wikipediaData['synonyms'] ?? [], 
                    $googleData['synonyms'] ?? []
                ),
                'antonyms' => array_merge(
                    $tdkData['antonyms'] ?? [], 
                    $wikipediaData['antonyms'] ?? []
                ),
                'word_types' => array_merge(
                    $tdkData['word_types'] ?? [], 
                    $wikipediaData['word_types'] ?? []
                ),
                'related_words' => array_merge(
                    $tdkData['related_words'] ?? [], 
                    $wikipediaData['related_words'] ?? [],
                    $googleData['related_words'] ?? []
                ),
                'search_results' => $googleData['search_results'] ?? []
            ];
            
            // Kelimenin kategorilerini belirle
            $categories = $this->determineCategories($word, $data);
            
            // Kelimenin ilişkilerini belirle
            $relations = $this->determineRelations($word, $data);
            
            // Kelime frekansını ve önemini hesapla
            $frequency = $this->calculateFrequency($word, $data);
            $importance = $this->calculateImportance($word, $data, $categories);
            
            // Metadata oluştur
            $metadata = [
                'sources' => [
                    'tdk' => !empty($tdkData),
                    'wikipedia' => !empty($wikipediaData),
                    'google' => !empty($googleData)
                ],
                'categories' => $categories,
                'frequency' => $frequency,
                'importance' => $importance,
                'learned_at' => now()->toDateTimeString()
            ];
            
            // Veritabanına kaydet
            $this->saveWordData($word, $data, $metadata);
            
            // İlişkileri kaydet
            foreach ($relations as $relation) {
                switch ($relation['type']) {
                    case 'synonym':
                        $this->wordRelations->learnSynonym(
                            $word, 
                            $relation['word'], 
                            $relation['strength']
                        );
                        break;
                    case 'antonym':
                        $this->wordRelations->learnAntonym(
                            $word, 
                            $relation['word'], 
                            $relation['strength']
                        );
                        break;
                    default:
                        $this->wordRelations->learnAssociation(
                            $word, 
                            $relation['word'], 
                            $relation['type'],
                            $relation['strength']
                        );
                        break;
                }
            }
            
            return [
                'success' => true,
                'message' => 'Kelime başarıyla öğrenildi',
                'data' => $data,
                'metadata' => $metadata
            ];
            
        } catch (\Exception $e) {
            // Hatayı logla
            Log::error('Kelime öğrenme hatası: ' . $e->getMessage());
            
            // Hatayı önbelleğe al
            $this->cacheError($word, $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Kelime öğrenme hatası: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * TDK'dan verileri topla
     * 
     * @param string $word Kelime
     * @return array Toplanan veriler
     */
    private function collectFromTDK($word)
    {
        try {
            // İstek limitini kontrol et
            if (!$this->checkRequestLimit('tdk')) {
                return [];
            }
            
            $url = "https://sozluk.gov.tr/gts?ara=" . urlencode($word);
            $response = Http::get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // TDK cevabını ayrıştır
                $result = [
                    'definitions' => [],
                    'examples' => [],
                    'synonyms' => [],
                    'antonyms' => [],
                    'word_types' => [],
                    'related_words' => []
                ];
                
                if (isset($data[0]['anlamlarListe'])) {
                    foreach ($data[0]['anlamlarListe'] as $meaning) {
                        $result['definitions'][] = $meaning['anlam'];
                        
                        // Örnek cümleleri ekle
                        if (isset($meaning['orneklerListe'])) {
                            foreach ($meaning['orneklerListe'] as $example) {
                                $result['examples'][] = $example['ornek'];
                            }
                        }
                    }
                }
                
                // Kelime türü
                if (isset($data[0]['lisan'])) {
                    $result['word_types'][] = $data[0]['lisan'];
                }
                
                // Eş/Zıt anlamlılar varsa ekle
                if (isset($data[0]['atasozu'])) {
                    foreach ($data[0]['atasozu'] as $proverb) {
                        $result['related_words'][] = $proverb['madde'];
                    }
                }
                
                return $result;
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('TDK veri toplama hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Wikipedia'dan verileri topla
     * 
     * @param string $word Kelime
     * @return array Toplanan veriler
     */
    private function collectFromWikipedia($word)
    {
        try {
            // İstek limitini kontrol et
            if (!$this->checkRequestLimit('wiktionary', 'wikipedia')) {
                return [];
            }
            
            // Wikipedia API URL'i
            $url = "https://tr.wikipedia.org/api/rest_v1/page/summary/" . urlencode($word);
            $response = Http::get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $result = [
                    'definitions' => [],
                    'examples' => [],
                    'synonyms' => [],
                    'antonyms' => [],
                    'word_types' => [],
                    'related_words' => []
                ];
                
                // Tanımı ekle
                if (isset($data['extract'])) {
                    $result['definitions'][] = $data['extract'];
                }
                
                // İlgili başlıkları al
                if (isset($data['title'])) {
                    $relatedUrl = "https://tr.wikipedia.org/api/rest_v1/page/related/" . urlencode($data['title']);
                    $relatedResponse = Http::get($relatedUrl);
                    
                    if ($relatedResponse->successful()) {
                        $relatedData = $relatedResponse->json();
                        
                        if (isset($relatedData['pages'])) {
                            foreach ($relatedData['pages'] as $page) {
                                if (isset($page['title']) && $page['title'] != $word) {
                                    $result['related_words'][] = $page['title'];
                                }
                            }
                        }
                    }
                }
                
                return $result;
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Wikipedia veri toplama hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Google'dan verileri topla
     * 
     * @param string $word Kelime
     * @return array Toplanan veriler
     */
    private function collectFromGoogle($word)
    {
        try {
            // İstek limitini kontrol et
            if (!$this->checkRequestLimit('google')) {
                return [];
            }
            
            // API anahtarı kontrolü
            $apiKey = $this->apiKeys['google'];
            if (empty($apiKey)) {
                return [];
            }
            
            // Custom Search API URL'i
            $cx = env('GOOGLE_SEARCH_CX', ''); // Özel arama motoru ID'si
            $url = "https://www.googleapis.com/customsearch/v1?key=" . $apiKey . "&cx=" . $cx . "&q=" . urlencode($word);
            
            $response = Http::get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $result = [
                    'examples' => [],
                    'synonyms' => [],
                    'related_words' => [],
                    'search_results' => []
                ];
                
                // Arama sonuçlarını ekle
                if (isset($data['items'])) {
                    foreach ($data['items'] as $item) {
                        $result['search_results'][] = [
                            'title' => $item['title'] ?? '',
                            'snippet' => $item['snippet'] ?? '',
                            'link' => $item['link'] ?? ''
                        ];
                        
                        // Snippetları örnek olarak kullan
                        if (isset($item['snippet']) && str_contains(strtolower($item['snippet']), strtolower($word))) {
                            $result['examples'][] = $item['snippet'];
                        }
                    }
                }
                
                return $result;
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Google veri toplama hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Öğrenilecek kelimeleri getir
     * 
     * @param int $limit Kelime limiti
     * @return array Kelime listesi
     */
    private function getWordsToLearn($limit = 100)
    {
        try {
            // Öncelikle manuel eklenen kelimeleri kontrol et
            $manualWords = Cache::get('manual_words_to_learn', []);
            
            // Eğer yeterli manuel kelime varsa, onları kullan
            if (count($manualWords) >= $limit) {
                return array_slice($manualWords, 0, $limit);
            }
            
            // Manuel kelimeler + otomatik kelimeler
            $remainingLimit = $limit - count($manualWords);
            
            // Otomatik kelimeleri topla
            $automaticWords = $this->getAutomaticWordsToLearn($remainingLimit);
            
            return array_merge($manualWords, $automaticWords);
        } catch (\Exception $e) {
            Log::error('Öğrenilecek kelime getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Otomatik öğrenilecek kelimeleri getir
     * 
     * @param int $limit Kelime limiti
     * @return array Kelime listesi
     */
    private function getAutomaticWordsToLearn($limit = 100)
    {
        // Öğrenilen ve öğrenilecek kelimeleri kontrol et
        $learnedWords = AIData::pluck('word')->toArray();
        $commonWords = $this->getCommonTurkishWords($limit * 2); // 2 kat fazla al, filtreleme sonrası yeterli olsun
        
        // Öğrenilmemiş kelimeleri filtrele
        $wordsToLearn = array_filter($commonWords, function($word) use ($learnedWords) {
            return !in_array($word, $learnedWords);
        });
        
        return array_slice($wordsToLearn, 0, $limit);
    }
    
    /**
     * Yaygın Türkçe kelimeleri getir
     * 
     * @param int $limit Kelime limiti
     * @return array Kelime listesi
     */
    private function getCommonTurkishWords($limit = 100)
    {
        // Önbellekten kontrol et
        $cachedWords = Cache::get('common_turkish_words', []);
        
        if (!empty($cachedWords)) {
            return array_slice($cachedWords, 0, $limit);
        }
        
        // Temel kelimeler (gerçek uygulamada daha geniş bir liste olmalı)
        $commonWords = [
            'zaman', 'insan', 'yıl', 'yol', 'gün', 'hayat', 'el', 'göz', 'kadın', 'iş',
            'su', 'çocuk', 'yer', 'baş', 'ev', 'dünya', 'yüz', 'anne', 'ülke', 'kitap',
            'söz', 'baba', 'bilgi', 'kapı', 'ses', 'arkadaş', 'aile', 'güzel', 'sevgi', 'para',
            'konu', 'durum', 'oda', 'sokak', 'okul', 'düşünce', 'iyi', 'gece', 'ağaç', 'masa',
            'çalışma', 'pencere', 'bilgisayar', 'telefon', 'araba', 'kalem', 'hava', 'deniz', 'oyun', 'sanat',
            'müzik', 'yemek', 'köy', 'şehir', 'dil', 'tarih', 'yaşam', 'doğa', 'kültür', 'sistem',
            'hak', 'kişi', 'gelenek', 'öğrenci', 'öğretmen', 'duygu', 'anlam', 'sağlık', 'hastalık', 'hediye',
            'sevmek', 'gitmek', 'gelmek', 'bakmak', 'anlamak', 'görmek', 'duymak', 'bilmek', 'istemek', 'yapmak',
            'etmek', 'olmak', 'vermek', 'almak', 'konuşmak', 'okumak', 'yazmak', 'çalışmak', 'düşünmek', 'öğrenmek',
            'büyük', 'küçük', 'uzun', 'kısa', 'genç', 'yaşlı', 'zengin', 'yoksul', 'güçlü', 'zayıf'
        ];
        
        // Önbelleğe kaydet
        Cache::put('common_turkish_words', $commonWords, now()->addDays(1));
        
        return array_slice($commonWords, 0, $limit);
    }
    
    /**
     * Kelimenin kategorilerini belirle
     * 
     * @param string $word Kelime
     * @param array $data Toplanan veriler
     * @return array Kategoriler
     */
    private function determineCategories($word, $data)
    {
        $categories = [];
        
        // Tanımlardan kategorileri çıkar
        if (!empty($data['definitions'])) {
            foreach ($data['definitions'] as $definition) {
                $analysisResults = $this->categoryManager->analyzeText($definition);
                
                foreach ($analysisResults as $categoryId => $info) {
                    if ($info['score'] > 0.3) {
                        $categories[$categoryId] = $info['name'];
                    }
                }
            }
        }
        
        // Kelime türlerini kategori olarak değerlendir
        if (!empty($data['word_types'])) {
            foreach ($data['word_types'] as $type) {
                // Kategori ID'sini bul veya oluştur
                $categoryId = $this->categoryManager->getCategoryIdByName($type);
                if ($categoryId) {
                    $categories[$categoryId] = $type;
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * Kelimenin ilişkilerini belirle
     * 
     * @param string $word Kelime
     * @param array $data Toplanan veriler
     * @return array İlişkiler
     */
    private function determineRelations($word, $data)
    {
        $relations = [];
        
        // Eş anlamlıları ekle
        if (!empty($data['synonyms'])) {
            foreach ($data['synonyms'] as $synonym) {
                $relations[] = [
                    'word' => $synonym,
                    'type' => 'synonym',
                    'strength' => 0.9
                ];
            }
        }
        
        // Zıt anlamlıları ekle
        if (!empty($data['antonyms'])) {
            foreach ($data['antonyms'] as $antonym) {
                $relations[] = [
                    'word' => $antonym,
                    'type' => 'antonym',
                    'strength' => 0.9
                ];
            }
        }
        
        // İlişkili kelimeleri ekle
        if (!empty($data['related_words'])) {
            foreach ($data['related_words'] as $relatedWord) {
                $relations[] = [
                    'word' => $relatedWord,
                    'type' => 'association',
                    'strength' => 0.7
                ];
            }
        }
        
        return $relations;
    }
    
    /**
     * Kelime frekansını hesapla
     * 
     * @param string $word Kelime
     * @param array $data Toplanan veriler
     * @return int Frekans
     */
    private function calculateFrequency($word, $data)
    {
        $frequency = 0;
        
        // Tanımların sayısına göre
        $frequency += count($data['definitions']) * 5;
        
        // Örneklerin sayısına göre
        $frequency += count($data['examples']) * 3;
        
        // Eş/zıt anlamlıların sayısına göre
        $frequency += count($data['synonyms']) * 2;
        $frequency += count($data['antonyms']) * 2;
        
        // İlişkili kelimelerin sayısına göre
        $frequency += count($data['related_words']);
        
        // Arama sonuçlarının sayısına göre
        $frequency += count($data['search_results']) * 2;
        
        return max(1, $frequency);
    }
    
    /**
     * Kelimenin önemini hesapla
     * 
     * @param string $word Kelime
     * @param array $data Toplanan veriler
     * @param array $categories Kategoriler
     * @return float Önem
     */
    private function calculateImportance($word, $data, $categories)
    {
        $importance = 0.5; // Başlangıç değeri
        
        // Tanım sayısı
        $definitionCount = count($data['definitions']);
        if ($definitionCount > 0) {
            $importance += min(0.2, $definitionCount * 0.05);
        }
        
        // Kategori sayısı
        $categoryCount = count($categories);
        if ($categoryCount > 0) {
            $importance += min(0.2, $categoryCount * 0.05);
        }
        
        // İlişki sayısı
        $relationCount = count($data['synonyms']) + count($data['antonyms']) + count($data['related_words']);
        if ($relationCount > 0) {
            $importance += min(0.1, $relationCount * 0.01);
        }
        
        return min(1.0, $importance);
    }
    
    /**
     * Kelime verilerini veritabanına kaydet
     * 
     * @param string $word Kelime
     * @param array $data Toplanan veriler
     * @param array $metadata Metadata
     * @return bool Başarı durumu
     */
    private function saveWordData($word, $data, $metadata)
    {
        try {
            // AIData tablosuna kaydet
            $aiData = AIData::updateOrCreate(
                ['word' => $word],
                [
                    'sentence' => $data['definitions'][0] ?? null,
                    'category' => array_values($metadata['categories'])[0] ?? 'genel',
                    'context' => implode(', ', array_values($metadata['categories'])),
                    'language' => $this->language,
                    'frequency' => $metadata['frequency'],
                    'confidence' => $metadata['importance'],
                    'related_words' => json_encode(array_map(function($relation) {
                        return $relation['word'];
                    }, $this->determineRelations($word, $data))),
                    'usage_examples' => json_encode($data['examples']),
                    'emotional_context' => null,
                    'metadata' => json_encode($metadata)
                ]
            );
            
            // Kelimeyi kategorilere ekle
            foreach ($metadata['categories'] as $categoryId => $categoryName) {
                $wordType = $data['word_types'][0] ?? null;
                $this->categoryManager->addWordToCategory($word, $categoryId, $metadata['importance'], null, $wordType);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Kelime verisi kaydetme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * API istek limitini kontrol et
     * 
     * @param string $service Servis adı
     * @return bool Limit aşılmadıysa true
     */
    private function checkRequestLimit($service, $fallbackService = null)
    {
        // Servis kontrolü
        if (!isset($this->requestLimits[$service])) {
            if ($fallbackService && isset($this->requestLimits[$fallbackService])) {
                $service = $fallbackService;
            } else {
                return true; // Limit yoksa izin ver
            }
        }
        
        // Limiti kontrol et
        $limit = $this->requestLimits[$service];
        
        // Reset time kontrolü
        if (time() > $limit['reset']) {
            // Limiti sıfırla
            $this->requestLimits[$service]['used'] = 0;
            $this->requestLimits[$service]['reset'] = strtotime('tomorrow');
        }
        
        // Kullanım kontrolü
        if ($limit['used'] >= $limit['daily']) {
            Log::warning($service . ' API günlük limit aşıldı');
            return false;
        }
        
        // Kullanım sayısını artır
        $this->requestLimits[$service]['used']++;
        
        return true;
    }
    
    /**
     * Hata mesajını önbelleğe al
     * 
     * @param string $word Kelime
     * @param string $errorMessage Hata mesajı
     */
    private function cacheError($word, $errorMessage)
    {
        $errors = Cache::get('learning_errors', []);
        $errors[$word] = [
            'message' => $errorMessage,
            'time' => now()->toDateTimeString()
        ];
        
        // Son 100 hatayı tut
        if (count($errors) > 100) {
            $errors = array_slice($errors, -100, 100, true);
        }
        
        Cache::put('learning_errors', $errors, now()->addDays(1));
    }
    
    /**
     * Durumu getir
     * 
     * @return array Durum bilgisi
     */
    public function getStatus()
    {
        return [
            'isLearning' => $this->isLearning,
            'wordLimit' => $this->wordLimit,
            'wordsLearned' => $this->wordsLearned,
            'startTime' => $this->startTime,
            'duration' => $this->startTime ? time() - $this->startTime : 0,
            'errorCount' => count($this->errorLog)
        ];
    }
    
    /**
     * Öğrenme durumunu getir
     * 
     * @return array Durum bilgisi
     */
    public function getLearningStatus()
    {
        // Öğrenilen kelime sayısı
        $wordCount = AIData::count();
        
        // Kategori sayısı
        $categoryStats = $this->categoryManager->getStats();
        
        // İlişki sayısı
        $relationStats = $this->wordRelations->getStats();
        
        return [
            'word_count' => $wordCount,
            'category_count' => $categoryStats['total_categories'] ?? 0,
            'categorized_word_count' => $categoryStats['total_categorized_words'] ?? 0,
            'relation_count' => ($relationStats['synonym_pairs'] ?? 0) + 
                               ($relationStats['antonym_pairs'] ?? 0) + 
                               ($relationStats['association_pairs'] ?? 0),
            'synonym_count' => $relationStats['synonym_pairs'] ?? 0,
            'antonym_count' => $relationStats['antonym_pairs'] ?? 0,
            'association_count' => $relationStats['association_pairs'] ?? 0,
            'definition_count' => $relationStats['definitions'] ?? 0,
            'is_learning' => $this->isLearning,
            'last_learned' => AIData::max('updated_at')
        ];
    }
    
    /**
     * Manuel kelime ekle
     * 
     * @param array $words Kelimeler
     * @return bool Başarı durumu
     */
    public function addManualWords($words)
    {
        try {
            if (empty($words) || !is_array($words)) {
                return false;
            }
            
            // Mevcut manuel kelimeleri al
            $manualWords = Cache::get('manual_words_to_learn', []);
            
            // Yeni kelimeleri ekle (tekrarları önle)
            foreach ($words as $word) {
                if (!in_array($word, $manualWords)) {
                    $manualWords[] = $word;
                }
            }
            
            // Önbelleğe kaydet
            Cache::put('manual_words_to_learn', $manualWords, now()->addDays(7));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Manuel kelime ekleme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Oxford API'dan verileri topla
     * 
     * @param string $word Kelime
     * @return array Toplanan veriler
     */
    private function collectFromOxford($word)
    {
        try {
            // İstek limitini kontrol et
            if (!$this->checkRequestLimit('oxford')) {
                return [];
            }
            
            // API anahtarı kontrolü
            $apiKey = $this->apiKeys['oxford'];
            $appId = env('OXFORD_APP_ID', '');
            if (empty($apiKey) || empty($appId)) {
                return [];
            }
            
            // Oxford API URL'i
            $url = "https://od-api.oxforddictionaries.com/api/v2/entries/tr/" . urlencode($word);
            
            $response = Http::withHeaders([
                'app_id' => $appId,
                'app_key' => $apiKey
            ])->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $result = [
                    'definitions' => [],
                    'examples' => [],
                    'synonyms' => [],
                    'antonyms' => [],
                    'word_types' => [],
                    'related_words' => []
                ];
                
                // Veriyi ayrıştır
                if (isset($data['results'][0]['lexicalEntries'])) {
                    foreach ($data['results'][0]['lexicalEntries'] as $lexicalEntry) {
                        // Kelime türü
                        if (isset($lexicalEntry['lexicalCategory']['text'])) {
                            $result['word_types'][] = $lexicalEntry['lexicalCategory']['text'];
                        }
                        
                        // Tanımlar ve örnekler
                        if (isset($lexicalEntry['entries'][0]['senses'])) {
                            foreach ($lexicalEntry['entries'][0]['senses'] as $sense) {
                                if (isset($sense['definitions'])) {
                                    foreach ($sense['definitions'] as $definition) {
                                        $result['definitions'][] = $definition;
                                    }
                                }
                                
                                if (isset($sense['examples'])) {
                                    foreach ($sense['examples'] as $example) {
                                        $result['examples'][] = $example['text'];
                                    }
                                }
                                
                                // Eş/zıt anlamlılar
                                if (isset($sense['synonyms'])) {
                                    foreach ($sense['synonyms'] as $synonym) {
                                        $result['synonyms'][] = $synonym['text'];
                                    }
                                }
                                
                                if (isset($sense['antonyms'])) {
                                    foreach ($sense['antonyms'] as $antonym) {
                                        $result['antonyms'][] = $antonym['text'];
                                    }
                                }
                            }
                        }
                    }
                }
                
                return $result;
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Oxford veri toplama hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Wiktionary'den verileri topla
     * 
     * @param string $word Kelime
     * @return array Toplanan veriler
     */
    private function collectFromWiktionary($word)
    {
        try {
            // İstek limitini kontrol et
            if (!$this->checkRequestLimit('wiktionary')) {
                return [];
            }
            
            // Wiktionary API URL'i
            $url = "https://tr.wiktionary.org/api/rest_v1/page/definition/" . urlencode($word);
            $response = Http::get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $result = [
                    'definitions' => [],
                    'examples' => [],
                    'synonyms' => [],
                    'antonyms' => [],
                    'word_types' => [],
                    'related_words' => []
                ];
                
                // Veriyi ayrıştır
                if (isset($data['tr'])) {
                    foreach ($data['tr'] as $entry) {
                        if (isset($entry['partOfSpeech'])) {
                            $result['word_types'][] = $entry['partOfSpeech'];
                        }
                        
                        if (isset($entry['definitions'])) {
                            foreach ($entry['definitions'] as $definition) {
                                $result['definitions'][] = $definition['definition'];
                                
                                // Örnekler
                                if (isset($definition['examples'])) {
                                    foreach ($definition['examples'] as $example) {
                                        $result['examples'][] = $example;
                                    }
                                }
                            }
                        }
                    }
                }
                
                return $result;
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Wiktionary veri toplama hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Öğrenme ilerlemesini getir
     * 
     * @return array İlerleme bilgisi
     */
    public function getProgress()
    {
        // Öğrenilen kelime sayısı
        $wordCount = AIData::count();
        
        // Son öğrenilen kelime
        $lastWord = AIData::orderBy('created_at', 'desc')->first();
        
        // Tahmini bitiş süresi hesapla
        $estimatedEnd = null;
        if ($this->isLearning && $this->wordsLearned > 0 && $this->startTime) {
            $elapsedTime = time() - $this->startTime;
            $avgTimePerWord = $elapsedTime / $this->wordsLearned;
            $remainingWords = $this->wordLimit - $this->wordsLearned;
            $remainingTime = $avgTimePerWord * $remainingWords;
            $estimatedEnd = date('Y-m-d H:i:s', time() + $remainingTime);
        }
        
        return [
            'is_learning' => $this->isLearning,
            'word_limit' => $this->wordLimit,
            'learned_count' => $this->wordsLearned,
            'word_count' => $wordCount,
            'progress_percent' => $this->wordLimit > 0 ? round(($this->wordsLearned / $this->wordLimit) * 100) : 0,
            'start_time' => $this->startTime ? date('Y-m-d H:i:s', $this->startTime) : null,
            'elapsed_time' => $this->startTime ? time() - $this->startTime : 0,
            'estimated_end' => $estimatedEnd,
            'last_word' => $lastWord ? $lastWord->word : null,
            'error_count' => count($this->errorLog)
        ];
    }
}
