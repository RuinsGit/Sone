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
            
            Log::info("Toplam $totalWords kelime öğrenilecek");
            
            // Kelimeleri öğren
            foreach ($words as $word) {
                try {
                    // Her kelimeyi öğrenmeden önce log
                    Log::info("Öğrenilecek kelime: $word");
                    $result = $this->learnWord($word);
                    
                    if ($result['success']) {
                        $this->wordsLearned++;
                        Log::info("$word kelimesi başarıyla öğrenildi");
                    } else {
                        $this->errorLog[] = [
                            'word' => $word,
                            'error' => $result['message']
                        ];
                        Log::warning("$word kelimesi öğrenilemedi: " . $result['message']);
                    }
                    
                    // Her 10 kelimede bir cache'e ilerleme durumunu kaydet
                    if ($this->wordsLearned % 10 == 0) {
                        Cache::put('learning_progress', [
                            'total' => $totalWords,
                            'learned' => $this->wordsLearned,
                            'errors' => count($this->errorLog),
                            'last_word' => $word,
                            'updated_at' => now()->toDateTimeString()
                        ], now()->addDay());
                    }
                    
                    // Kelime limiti kontrolü
                    if ($this->wordsLearned >= $this->wordLimit) {
                        break;
                    }
                    
                    // Kısa bir süre bekleyerek API limitlerini aşmayı engelle
                    usleep(500000); // 0.5 saniye
                } catch (\Exception $e) {
                    Log::error("Kelime öğrenme işleminde hata: " . $e->getMessage());
                    $this->errorLog[] = [
                        'word' => $word,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->isLearning = false;
            
            // Son durumu cache'e kaydet
            Cache::put('learning_progress', [
                'total' => $totalWords,
                'learned' => $this->wordsLearned,
                'errors' => count($this->errorLog),
                'last_word' => end($words),
                'updated_at' => now()->toDateTimeString(),
                'completed' => true
            ], now()->addDay());
            
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
            
            // Veritabanında öğrenilmiş kelimeler
            $learnedWords = AIData::pluck('word')->toArray();
            
            // Manuel kelimelerden öğrenilmemiş olanları filtrele
            $filteredManualWords = array_filter($manualWords, function($word) use ($learnedWords) {
                return !in_array($word, $learnedWords);
            });
            
            // Eğer yeterli manuel kelime varsa, onları kullan
            if (count($filteredManualWords) >= $limit) {
                Log::info("Manuel kelime listesinden " . count($filteredManualWords) . " kelime öğrenilecek");
                return array_slice($filteredManualWords, 0, $limit);
            }
            
            // Manuel kelimeler + otomatik kelimeler
            $remainingLimit = $limit - count($filteredManualWords);
            
            Log::info("Manuel kelime listesinden " . count($filteredManualWords) . " kelime, otomatik kaynaklardan " . $remainingLimit . " kelime öğrenilecek");
            
            // Otomatik kelimeleri topla
            $automaticWords = $this->getAutomaticWordsToLearn($remainingLimit);
            
            return array_merge($filteredManualWords, $automaticWords);
        } catch (\Exception $e) {
            Log::error('Öğrenilecek kelime getirme hatası: ' . $e->getMessage());
            return $this->getCommonTurkishWords($limit); // Yedek çözüm olarak yaygın kelimeleri kullan
        }
    }
    
    /**
     * Otomatik öğrenilecek kelimeleri getir
     * 
     * @param int $limit Kelime limiti
     * @return array Kelime listesi
     */
    public function getAutomaticWordsToLearn($limit = 100)
    {
        try {
            // Öğrenilen ve öğrenilecek kelimeleri kontrol et
            $learnedWords = AIData::pluck('word')->toArray();
            
            // Daha önce hata alınan kelimeleri kontrol et
            $previousErrors = Cache::get('learning_errors', []);
            $errorWords = array_keys($previousErrors);
            
            $wordsToLearn = [];
            
            // 1. Adım: Hata alınan eski kelimeleri tekrar dene
            $retryErrorWords = array_filter($errorWords, function($word) use ($learnedWords) {
                // Sadece 2 günden eski hataları tekrar dene
                $error = Cache::get('learning_errors', [])[$word] ?? null;
                if ($error && isset($error['time'])) {
                    $errorTime = new \DateTime($error['time']);
                    $diff = $errorTime->diff(new \DateTime());
                    return $diff->days >= 2 && !in_array($word, $learnedWords);
                }
                return false;
            });
            
            if (count($retryErrorWords) > 0) {
                $retryErrorWords = array_slice($retryErrorWords, 0, min(10, $limit / 5));
                $wordsToLearn = array_merge($wordsToLearn, $retryErrorWords);
                Log::info("Hata alınan " . count($retryErrorWords) . " kelime tekrar denenecek");
            }
            
            // 2. Adım: TDK'dan popüler kelimeleri çek
            $tdkWords = $this->fetchTDKPopularWords(50);
            $filteredTdkWords = array_filter($tdkWords, function($word) use ($learnedWords, $wordsToLearn) {
                return !in_array($word, $learnedWords) && !in_array($word, $wordsToLearn);
            });
            
            if (count($filteredTdkWords) > 0) {
                $filteredTdkWords = array_slice($filteredTdkWords, 0, min(30, $limit / 2));
                $wordsToLearn = array_merge($wordsToLearn, $filteredTdkWords);
                Log::info("TDK'dan " . count($filteredTdkWords) . " kelime eklenecek");
            }
            
            // 3. Adım: Yaygın Türkçe kelimelerden ekle
            if (count($wordsToLearn) < $limit) {
                $remainingLimit = $limit - count($wordsToLearn);
                
                // Yaygın kelimeler listesinden öğrenilmemiş olanları seç
                $commonWords = $this->getCommonTurkishWords($limit * 3);
                $filteredCommonWords = array_filter($commonWords, function($word) use ($learnedWords, $wordsToLearn) {
                    return !in_array($word, $learnedWords) && !in_array($word, $wordsToLearn);
                });
                
                // Rastgele kelimeler seç
                shuffle($filteredCommonWords);
                $selectedCommonWords = array_slice($filteredCommonWords, 0, $remainingLimit);
                
                $wordsToLearn = array_merge($wordsToLearn, $selectedCommonWords);
                Log::info("Yaygın kelimelerden " . count($selectedCommonWords) . " kelime eklenecek");
            }
            
            // 4. Adım: Eğer hala yeterli kelime yoksa sözlükten rastgele kelimeler ekle
            if (count($wordsToLearn) < $limit) {
                $remainingLimit = $limit - count($wordsToLearn);
                $randomWords = $this->getRandomDictionaryWords($remainingLimit);
                $wordsToLearn = array_merge($wordsToLearn, $randomWords);
                Log::info("Sözlükten rastgele " . count($randomWords) . " kelime eklenecek");
            }
            
            // Kelimeleri karıştır
            shuffle($wordsToLearn);
            
            // Limiti uygula
            $wordsToLearn = array_slice($wordsToLearn, 0, $limit);
            
            Log::info("Otomatik öğrenilecek toplam " . count($wordsToLearn) . " kelime bulundu");
            return $wordsToLearn;
            
        } catch (\Exception $e) {
            Log::error('Otomatik kelime getirme hatası: ' . $e->getMessage());
            return array_slice($this->getCommonTurkishWords($limit), 0, $limit);
        }
    }
    
    /**
     * TDK'dan popüler kelimeleri çek
     * 
     * @param int $limit Kelime limiti
     * @return array Kelime listesi
     */
    private function fetchTDKPopularWords($limit = 50)
    {
        try {
            // Örnek Türkçe kelime listesi
            $commonWords = [
                'yaşam', 'sevgi', 'mutluluk', 'başarı', 'özgürlük', 'bilim', 'teknoloji', 'sanat', 'kültür', 'tarih',
                'evren', 'doğa', 'insan', 'toplum', 'aile', 'eğitim', 'sağlık', 'spor', 'müzik', 'kitap',
                'film', 'yemek', 'su', 'hava', 'toprak', 'ateş', 'zaman', 'mekan', 'hayat', 'ölüm',
                'umut', 'hayal', 'gerçek', 'duygu', 'düşünce', 'fikir', 'anlam', 'amaç', 'hedef', 'yol',
                'sabır', 'cesaret', 'merhamet', 'adalet', 'barış', 'güven', 'saygı', 'hoşgörü', 'dostluk', 'arkadaşlık',
                'şehir', 'ülke', 'dünya', 'gezegen', 'güneş', 'ay', 'yıldız', 'kainat', 'deniz', 'okyanus',
                'göl', 'nehir', 'dağ', 'orman', 'çöl', 'ada', 'köy', 'kasaba', 'metropol', 'başkent',
                'yöntem', 'kural', 'sistem', 'düzen', 'yapı', 'biçim', 'içerik', 'değer', 'ilke', 'felsefe',
                'bilgi', 'veri', 'analiz', 'sonuç', 'çözüm', 'sorun', 'yenilik', 'gelişim', 'değişim', 'ilerleme',
                'kalem', 'kitap', 'defter', 'yazı', 'okuma', 'öğrenme', 'eğitim', 'okul', 'üniversite', 'fakülte',
                'araştırma', 'inceleme', 'deney', 'gözlem', 'teori', 'uygulama', 'pratik', 'teknik', 'yöntem', 'strateji',
                'sağlık', 'hastalık', 'tedavi', 'ilaç', 'doktor', 'hastane', 'beslenme', 'diyet', 'spor', 'egzersiz',
                'liderlik', 'yönetim', 'organizasyon', 'planlama', 'koordinasyon', 'denetim', 'karar', 'politika', 'strateji', 'hedef',
                'hukuk', 'kanun', 'adalet', 'mahkeme', 'yargı', 'hakim', 'avukat', 'dava', 'suç', 'ceza',
                'ekonomi', 'para', 'finans', 'banka', 'yatırım', 'ticaret', 'pazar', 'şirket', 'işletme', 'sanayi',
                'çevre', 'doğa', 'iklim', 'hava', 'su', 'toprak', 'orman', 'ağaç', 'bitki', 'hayvan'
            ];
            
            // Kelime listesini karıştır ve limitle
            shuffle($commonWords);
            return array_slice($commonWords, 0, $limit);
        } catch (\Exception $e) {
            Log::error('TDK popüler kelime çekme hatası: ' . $e->getMessage());
            return [];
        }
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
            $randomWords = $cachedWords;
            shuffle($randomWords);
            return array_slice($randomWords, 0, $limit);
        }
        
        // Temel kelimeler
        $commonWords = [
            // Temel isimler
            'zaman', 'insan', 'yıl', 'yol', 'gün', 'hayat', 'el', 'göz', 'kadın', 'iş',
            'su', 'çocuk', 'yer', 'baş', 'ev', 'dünya', 'yüz', 'anne', 'ülke', 'kitap',
            'söz', 'baba', 'bilgi', 'kapı', 'ses', 'arkadaş', 'aile', 'sevgi', 'para', 'konu',
            'durum', 'oda', 'sokak', 'okul', 'düşünce', 'gece', 'ağaç', 'masa', 'çalışma', 'pencere',
            'bilgisayar', 'telefon', 'araba', 'kalem', 'hava', 'deniz', 'oyun', 'sanat', 'müzik', 'yemek',
            'köy', 'şehir', 'dil', 'tarih', 'yaşam', 'doğa', 'kültür', 'sistem', 'hak', 'kişi',
            
            // Sık kullanılan fiiller
            'olmak', 'yapmak', 'gitmek', 'gelmek', 'almak', 'vermek', 'bakmak', 'söylemek', 'görmek', 'bilmek',
            'istemek', 'bulmak', 'düşünmek', 'anlamak', 'çalışmak', 'yaşamak', 'durmak', 'başlamak', 'beklemek', 'geçmek',
            'kullanmak', 'sevmek', 'yemek', 'içmek', 'koşmak', 'uyumak', 'okumak', 'yazmak', 'dinlemek', 'konuşmak',
            'oturmak', 'kalkmak', 'açmak', 'kapamak', 'satmak', 'almak', 'öğrenmek', 'anlatmak', 'sormak', 'aramak',
            
            // Sık kullanılan sıfatlar
            'güzel', 'büyük', 'küçük', 'yeni', 'eski', 'önemli', 'uzun', 'kısa', 'sıcak', 'soğuk',
            'iyi', 'kötü', 'doğru', 'yanlış', 'hızlı', 'yavaş', 'kolay', 'zor', 'mutlu', 'üzgün',
            'açık', 'kapalı', 'yüksek', 'alçak', 'yakın', 'uzak', 'genç', 'yaşlı', 'zengin', 'fakir',
            'erken', 'geç', 'geniş', 'dar', 'dolu', 'boş', 'ağır', 'hafif', 'ucuz', 'pahalı',
            
            // Daha fazla Türkçe kelime
            'özgürlük', 'barış', 'savaş', 'adalet', 'huzur', 'güç', 'enerji', 'değer', 'amaç', 'hedef',
            'rüya', 'umut', 'korku', 'cesaret', 'başarı', 'sevgi', 'nefret', 'mutluluk', 'üzüntü', 'öfke',
            'toplum', 'devlet', 'hükümet', 'yasa', 'kural', 'hak', 'görev', 'sorumluluk', 'özgürlük', 'adalet',
            'doğa', 'çevre', 'iklim', 'hava', 'su', 'toprak', 'ağaç', 'orman', 'nehir', 'dağ',
            'sağlık', 'hastalık', 'tedavi', 'hastane', 'doktor', 'ilaç', 'spor', 'egzersiz', 'beslenme', 'diyet',
            'eğitim', 'okul', 'öğretmen', 'öğrenci', 'ders', 'sınav', 'not', 'diploma', 'üniversite', 'bilim',
            'sanat', 'müzik', 'resim', 'tiyatro', 'sinema', 'edebiyat', 'şiir', 'roman', 'hikaye', 'yazar'
        ];
        
        // Önbelleğe kaydet
        Cache::put('common_turkish_words', $commonWords, now()->addDays(7));
        
        // Kelimeleri karıştır ve limitle
        shuffle($commonWords);
        return array_slice($commonWords, 0, $limit);
    }
    
    /**
     * Sözlükten rastgele kelimeler getir
     * 
     * @param int $limit Kelime limiti
     * @return array Kelime listesi
     */
    private function getRandomDictionaryWords($limit = 20)
    {
        // Türkçe'de yaygın olarak kullanılan daha fazla kelime
        $extraWords = [
            'kedi', 'köpek', 'kuş', 'balık', 'ağaç', 'çiçek', 'gökyüzü', 'güneş', 'ay', 'yıldız',
            'okyanus', 'deniz', 'göl', 'nehir', 'dağ', 'tepe', 'vadi', 'orman', 'çöl', 'ada',
            'burun', 'kulak', 'göz', 'ağız', 'diş', 'saç', 'el', 'ayak', 'bacak', 'kol',
            'kalp', 'beyin', 'akciğer', 'mide', 'karaciğer', 'böbrek', 'kemik', 'kas', 'damar', 'kan',
            'kitap', 'kalem', 'defter', 'silgi', 'masa', 'sandalye', 'kapı', 'pencere', 'duvar', 'tavan',
            'zemin', 'halı', 'koltuk', 'yatak', 'dolap', 'televizyon', 'bilgisayar', 'telefon', 'saat', 'lamba',
            'ekmek', 'su', 'çay', 'kahve', 'süt', 'yoğurt', 'peynir', 'et', 'tavuk', 'balık',
            'elma', 'portakal', 'muz', 'çilek', 'kiraz', 'karpuz', 'kavun', 'üzüm', 'armut', 'şeftali',
            'domates', 'biber', 'patlıcan', 'kabak', 'patates', 'soğan', 'sarımsak', 'salatalık', 'havuç', 'ıspanak',
            'araba', 'bisiklet', 'motosiklet', 'otobüs', 'tren', 'uçak', 'gemi', 'tekne', 'helikopter', 'roket',
            'okul', 'hastane', 'otel', 'restoran', 'market', 'mağaza', 'banka', 'postane', 'kütüphane', 'müze',
            'anne', 'baba', 'kardeş', 'dede', 'nine', 'teyze', 'hala', 'amca', 'dayı', 'kuzen',
            'öğretmen', 'doktor', 'hemşire', 'polis', 'asker', 'mühendis', 'avukat', 'şoför', 'aşçı', 'garson',
            'pazartesi', 'salı', 'çarşamba', 'perşembe', 'cuma', 'cumartesi', 'pazar', 'sabah', 'öğle', 'akşam',
            'gece', 'ocak', 'şubat', 'mart', 'nisan', 'mayıs', 'haziran', 'temmuz', 'ağustos', 'eylül',
            'ekim', 'kasım', 'aralık', 'ilkbahar', 'yaz', 'sonbahar', 'kış', 'bugün', 'dün', 'yarın',
            'bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi', 'sekiz', 'dokuz', 'on',
            'on bir', 'on iki', 'on üç', 'on dört', 'on beş', 'yirmi', 'otuz', 'kırk', 'elli', 'yüz',
            'bin', 'milyon', 'milyar', 'birinci', 'ikinci', 'üçüncü', 'dördüncü', 'beşinci', 'yarım', 'çeyrek',
            'kırmızı', 'mavi', 'yeşil', 'sarı', 'turuncu', 'mor', 'pembe', 'siyah', 'beyaz', 'gri',
            'altın', 'gümüş', 'bronz', 'demir', 'bakır', 'çelik', 'alüminyum', 'kurşun', 'kalay', 'nikel',
            'sevmek', 'nefret etmek', 'gülmek', 'ağlamak', 'konuşmak', 'susmak', 'bakmak', 'görmek', 'duymak', 'işitmek',
            'koklamak', 'tatmak', 'dokunmak', 'hissetmek', 'düşünmek', 'unutmak', 'hatırlamak', 'anlamak', 'bilmek', 'öğrenmek',
            'başlamak', 'bitirmek', 'çalışmak', 'dinlenmek', 'uyumak', 'uyanmak', 'yemek', 'içmek', 'yürümek', 'koşmak',
            'oturmak', 'kalkmak', 'gelmek', 'gitmek', 'beklemek', 'gelmek', 'almak', 'vermek', 'satmak', 'kazanmak'
        ];
        
        // Kelimeleri karıştır
        shuffle($extraWords);
        
        // Limitle
        return array_slice($extraWords, 0, $limit);
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
    
    /**
     * Öğrenme işlemini durdur
     * 
     * @return array İşlem sonucu
     */
    public function stopLearning()
    {
        if (!$this->isLearning) {
            return [
                'success' => false,
                'message' => 'Öğrenme işlemi zaten durdurulmuş.',
                'data' => $this->getLearningStatus()
            ];
        }
        
        $this->isLearning = false;
        
        // Öğrenme durumunu önbelleğe kaydet
        $status = [
            'total' => $this->wordLimit,
            'learned' => $this->wordsLearned,
            'errors' => count($this->errorLog),
            'last_updated' => now()->toDateTimeString(),
            'start_time' => $this->startTime ? date('Y-m-d H:i:s', $this->startTime) : null,
            'elapsed_time' => $this->startTime ? time() - $this->startTime : 0,
            'stopped_manually' => true,
            'is_learning' => false
        ];
        
        Cache::put('learning_progress', $status, now()->addDay());
        
        Log::info('Öğrenme işlemi manuel olarak durduruldu.');
        
        return [
            'success' => true,
            'message' => 'Öğrenme işlemi durduruldu.',
            'data' => $status
        ];
    }
}
