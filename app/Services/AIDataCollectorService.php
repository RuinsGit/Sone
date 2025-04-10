<?php

namespace App\Services;

use App\Models\AIData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AIDataCollectorService
{
    private $sources = [
        'wikipedia' => 'https://tr.wikipedia.org/w/api.php',
        'sozluk' => 'https://sozluk.gov.tr/gts',
        'news' => 'https://newsapi.org/v2/top-headlines',
        'twitter' => 'https://api.twitter.com/2/tweets/search/recent',
        'reddit' => 'https://www.reddit.com/r/turkish/hot.json',
        'eksi' => 'https://eksisozluk.com/basliklar/gundem'
    ];
    
    public function collectData()
    {
        try {
            $result = [
                'total' => 0,
                'sources' => [
                    'wikipedia' => 0,
                    'dictionary' => 0,
                    'news' => 0,
                    'twitter' => 0,
                    'reddit' => 0
                ]
            ];
            
            // Her bir kaynaktan veri toplamayı dene, hata olursa atlayıp diğerine geç
            try {
                $result['sources']['wikipedia'] = $this->collectFromWikipedia();
                $result['total'] += $result['sources']['wikipedia'];
            } catch (\Exception $e) {
                Log::error('Wikipedia veri toplama başarısız: ' . $e->getMessage());
            }
            
            try {
                $result['sources']['dictionary'] = $this->collectFromDictionary();
                $result['total'] += $result['sources']['dictionary'];
            } catch (\Exception $e) {
                Log::error('Sözlük veri toplama başarısız: ' . $e->getMessage());
            }
            
            try {
                $result['sources']['news'] = $this->collectFromNews();
                $result['total'] += $result['sources']['news'];
            } catch (\Exception $e) {
                Log::error('Haber veri toplama başarısız: ' . $e->getMessage());
            }
            
            try {
                $result['sources']['twitter'] = $this->collectFromTwitter();
                $result['total'] += $result['sources']['twitter'];
            } catch (\Exception $e) {
                Log::error('Twitter veri toplama başarısız: ' . $e->getMessage());
            }
            
            try {
                $this->processCollectedData();
            } catch (\Exception $e) {
                Log::error('Toplanan verileri işleme başarısız: ' . $e->getMessage());
            }
            
            Log::info('Veri toplama tamamlandı. Toplam: ' . $result['total'] . ' öğe toplandı.');
            return $result;
        } catch (\Exception $e) {
            Log::error('Veri toplama ana işlemi hatası: ' . $e->getMessage());
            return ['total' => 0, 'error' => $e->getMessage()];
        }
    }
    
    private function collectFromWikipedia()
    {
        try {
            $response = Http::get($this->sources['wikipedia'], [
                'action' => 'query',
                'format' => 'json',
                'list' => 'random',
                'rnlimit' => 250,
                'rnnamespace' => 0
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Veri doğru formatta mı kontrol et
                if (!isset($data['query']) || !isset($data['query']['random']) || !is_array($data['query']['random'])) {
                    Log::warning("Wikipedia API yanıtı geçersiz format: " . json_encode($data));
                    return 0;
                }
                
                $articles = $data['query']['random'];
                $processedCount = 0;
                
                foreach ($articles as $article) {
                    try {
                        // Makale içeriğini al
                        if (!isset($article['id'])) {
                            continue;
                        }
                        
                        $content = $this->getWikipediaContent($article['id']);
                        
                        if (!empty($content)) {
                            // Kelimeleri ve cümleleri ayır
                            $this->processContent($content, 'wikipedia');
                            $processedCount++;
                            
                            // Her 10 makalede kaydet
                            if ($processedCount % 10 == 0) {
                                $this->saveRelations();
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Makale işlenirken hata: " . ($article['id'] ?? 'bilinmeyen') . " - " . $e->getMessage());
                        // Tek makale hatası tüm işlemi durdurmasın
                        continue;
                    }
                }
                
                $this->saveRelations();
                Log::info("Wikipedia'dan {$processedCount} makale işlendi.");
                return $processedCount;
            } else {
                Log::warning("Wikipedia API yanıt vermedi - HTTP durum: " . $response->status());
                return 0;
            }
        } catch (\Exception $e) {
            Log::error('Wikipedia veri toplama hatası: ' . $e->getMessage());
            return 0;
        }
    }
    
    private function getWikipediaContent($pageId)
    {
        try {
            $response = Http::get($this->sources['wikipedia'], [
                'action' => 'query',
                'format' => 'json',
                'prop' => 'extracts',
                'pageids' => $pageId,
                'explaintext' => true
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!isset($data['query']) || !isset($data['query']['pages']) || 
                    !isset($data['query']['pages'][$pageId]) || !isset($data['query']['pages'][$pageId]['extract'])) {
                    Log::warning("Wikipedia içerik yanıtı geçersiz format: " . json_encode($data));
                    return '';
                }
                
                return $data['query']['pages'][$pageId]['extract'];
            } else {
                Log::warning("Wikipedia içerik API yanıt vermedi - HTTP durum: " . $response->status());
                return '';
            }
        } catch (\Exception $e) {
            Log::error("Wikipedia içerik getirme hatası: " . $pageId . " - " . $e->getMessage());
            return '';
        }
    }
    
    private function collectFromDictionary()
    {
        try {
            // TDK sözlüğünden kelime ve anlamları topla
            $commonWords = $this->getCommonTurkishWords();
            $savedCount = 0;
            
            foreach ($commonWords as $word) {
                try {
                    $response = Http::get($this->sources['sozluk'], [
                        'ara' => $word
                    ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        
                        // Veri boş mu veya doğru formatta mı kontrolü
                        if (empty($data) || !is_array($data) || empty($data[0]) || !isset($data[0]['anlamlarListe'])) {
                            Log::warning("TDK API yanıtı geçersiz format: " . json_encode($data));
                            continue;
                        }
                        
                        if (!empty($data[0]['anlamlarListe'])) {
                            foreach ($data[0]['anlamlarListe'] as $meaning) {
                                // Gerekli alanları kontrol et
                                if (!isset($meaning['anlam'])) {
                                    continue;
                                }
                                
                                // ozelliklerListe mevcut mu kontrol et
                                $context = null;
                                if (isset($meaning['ozelliklerListe']) && is_array($meaning['ozelliklerListe']) && 
                                    !empty($meaning['ozelliklerListe'][0]) && isset($meaning['ozelliklerListe'][0]['tam_adi'])) {
                                    $context = $meaning['ozelliklerListe'][0]['tam_adi'];
                                }
                                
                                $result = AIData::updateOrCreate(
                                    ['word' => $word],
                                    [
                                        'sentence' => $meaning['anlam'],
                                        'category' => isset($data[0]['kategori']) ? $data[0]['kategori'] : 'general',
                                        'context' => $context,
                                        'language' => 'tr',
                                        'frequency' => DB::raw('COALESCE(frequency, 0) + 1'),
                                        'confidence' => 0.8,
                                        'metadata' => json_encode([
                                            'source' => 'tdk',
                                            'collected_at' => now()
                                        ])
                                    ]
                                );
                                
                                if ($result) {
                                    $savedCount++;
                                    
                                    // Eş anlamlı ve zıt anlamlıları kur
                                    if (isset($meaning['atasozu'])) {
                                        foreach ($meaning['atasozu'] as $atasozu) {
                                            $this->processContent($atasozu, 'atasozu');
                                        }
                                    }
                                    
                                    // Eş anlamlıları işle
                                    if (isset($data[0]['es_anlamlilar'])) {
                                        $synonyms = explode(', ', $data[0]['es_anlamlilar']);
                                        foreach ($synonyms as $synonym) {
                                            $this->learnSynonym($word, trim($synonym), 0.8);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        Log::warning("TDK API yanıt vermedi: " . $word . " - HTTP durum: " . $response->status());
                    }
                } catch (\Exception $e) {
                    Log::error("Kelime işlenirken hata: " . $word . " - " . $e->getMessage());
                    // Tek kelime işleme hatası tüm işlemi durdurmasın, devam edelim
                    continue;
                }
                
                // Her 10 kelimede bir bekle
                if ($savedCount % 10 == 0) {
                    sleep(1);
                    $this->saveRelations();
                }
            }
            
            $this->saveRelations();
            Log::info("Sözlükten {$savedCount} kelime kaydedildi.");
            return $savedCount;
            
        } catch (\Exception $e) {
            Log::error('Sözlük veri toplama hatası: ' . $e->getMessage());
            // Veri toplama hatasını çağırana iletmek yerine hata kaydı tutup işleme devam edelim
            return 0;
        }
    }
    
    private function collectFromNews()
    {
        try {
            // API anahtarını kontrol et
            $apiKey = config('services.newsapi.key');
            if (empty($apiKey)) {
                Log::warning("News API anahtarı bulunamadı.");
                return 0;
            }
            
            $response = Http::get($this->sources['news'], [
                'country' => 'tr',
                'apiKey' => $apiKey
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!isset($data['articles']) || !is_array($data['articles'])) {
                    Log::warning("News API yanıtı geçersiz format: " . json_encode($data));
                    return 0;
                }
                
                $articles = $data['articles'];
                $processedCount = 0;
                
                foreach ($articles as $article) {
                    try {
                        if (!isset($article['title']) || !isset($article['description'])) {
                            continue;
                        }
                        
                        $this->processContent($article['title'] . ' ' . $article['description'], 'news');
                        $processedCount++;
                    } catch (\Exception $e) {
                        Log::error("Haber işlenirken hata: " . ($article['title'] ?? 'bilinmeyen') . " - " . $e->getMessage());
                        // Tek haber hatası tüm işlemi durdurmasın
                        continue;
                    }
                }
                
                Log::info("Haber kaynaklarından {$processedCount} haber işlendi.");
                return $processedCount;
            } else {
                Log::warning("News API yanıt vermedi - HTTP durum: " . $response->status());
                return 0;
            }
        } catch (\Exception $e) {
            Log::error('Haber veri toplama hatası: ' . $e->getMessage());
            return 0;
        }
    }
    
    private function collectFromTwitter()
    {
        try {
            // Twitter API anahtarını kontrol et
            $apiKey = config('services.twitter.bearer_token');
            if (empty($apiKey)) {
                Log::warning("Twitter API anahtarı bulunamadı.");
                return 0;
            }
            
            $response = Http::withToken($apiKey)
                ->get($this->sources['twitter'], [
                    'query' => 'lang:tr',
                    'max_results' => 100
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!isset($data['data']) || !is_array($data['data'])) {
                    Log::warning("Twitter API yanıtı geçersiz format: " . json_encode($data));
                    return 0;
                }
                
                $tweets = $data['data'];
                $processedCount = 0;
                
                foreach ($tweets as $tweet) {
                    try {
                        if (!isset($tweet['text'])) {
                            continue;
                        }
                        
                        $this->processContent($tweet['text'], 'twitter');
                        $processedCount++;
                    } catch (\Exception $e) {
                        Log::error("Tweet işlenirken hata: " . substr($tweet['text'] ?? 'bilinmeyen', 0, 30) . "... - " . $e->getMessage());
                        continue;
                    }
                }
                
                $this->saveRelations();
                Log::info("Twitter'dan {$processedCount} tweet işlendi.");
                return $processedCount;
            } else {
                Log::warning("Twitter API yanıt vermedi - HTTP durum: " . $response->status());
                return 0;
            }
        } catch (\Exception $e) {
            Log::error('Twitter veri toplama hatası: ' . $e->getMessage());
            return 0;
        }
    }
    
    private function processContent($content, $source)
    {
        if (empty($content) || !is_string($content)) {
            Log::warning('İşlenecek içerik boş veya string değil');
            return 0;
        }
        
        try {
            // Metni cümlelere ayır - birden fazla ayırıcı karakteri destekle
            $sentences = preg_split('/(?<=[.!?;])\s+/', $content);
            $savedCount = 0;
            
            foreach ($sentences as $sentence) {
                if (empty($sentence) || strlen($sentence) < 5) {
                    continue; // Çok kısa cümleleri atla
                }
                
                try {
                    // Türkçe karakterleri destekleyecek şekilde kelimelere ayır
                    $words = preg_split('/\s+/', mb_strtolower($sentence, 'UTF-8'));
                    
                    // Aynı cümledeki kelimeleri birbiriyle ilişkilendir
                    $relationships = [];
                    
                    foreach ($words as $word) {
                        // Kelimeyi temizle
                        $word = trim(preg_replace('/[^\p{L}\p{N}]/u', '', $word));
                        
                        if (strlen($word) > 2) { // 2 karakterden uzun kelimeleri al
                            try {
                                $result = AIData::updateOrCreate(
                                    ['word' => $word],
                                    [
                                        'sentence' => $sentence,
                                        'category' => $this->determineCategory($word, $sentence),
                                        'context' => $source,
                                        'language' => 'tr',
                                        'frequency' => DB::raw('COALESCE(frequency, 0) + 1'),
                                        'confidence' => 0.6,
                                        'metadata' => json_encode([
                                            'source' => $source,
                                            'collected_at' => now()
                                        ])
                                    ]
                                );
                                
                                if ($result) {
                                    $savedCount++;
                                    $relationships[] = $word;
                                }
                            } catch (\Exception $e) {
                                Log::error("Kelime kaydetme hatası: {$word} - " . $e->getMessage());
                                continue;
                            }
                        }
                    }
                    
                    // Kelimeleri birbirleriyle ilişkilendir
                    $this->createWordRelationships($relationships, $sentence);
                } catch (\Exception $e) {
                    Log::error("Cümle işleme hatası: " . substr($sentence, 0, 30) . "... - " . $e->getMessage());
                    continue;
                }
            }
            
            Log::info("{$source} kaynağından {$savedCount} kelime kaydedildi.");
            return $savedCount;
            
        } catch (\Exception $e) {
            Log::error("İçerik işleme hatası ({$source}): " . $e->getMessage());
            return 0;
        }
    }
    
    private function determineCategory($word, $sentence)
    {
        // Basit kategori belirleme
        $categories = [
            'selamlama' => ['merhaba', 'selam', 'günaydın', 'iyi'],
            'duygu' => ['mutlu', 'üzgün', 'kızgın', 'sevinçli'],
            'soru' => ['ne', 'nasıl', 'neden', 'niçin'],
            'zaman' => ['bugün', 'yarın', 'dün', 'şimdi']
        ];
        
        foreach ($categories as $category => $keywords) {
            if (in_array($word, $keywords)) {
                return $category;
            }
        }
        
        return 'genel';
    }
    
    private function updateUsageExamples($word, $sentence)
    {
        $currentExamples = AIData::where('word', $word)->value('usage_examples') ?? [];
        
        if (is_string($currentExamples)) {
            $currentExamples = json_decode($currentExamples, true) ?? [];
        }
        
        if (!in_array($sentence, $currentExamples)) {
            $currentExamples[] = $sentence;
        }
        
        // Maksimum 10 örnek tut
        return array_slice($currentExamples, -10);
    }
    
    private function getCommonTurkishWords()
    {
        try {
            // Veritabanından en sık kullanılan kelimeleri al
            $dbWords = AIData::select('word')
                ->where('language', 'tr')
                ->orderBy('frequency', 'desc')
                ->limit(100)
                ->pluck('word')
                ->toArray();
            
            if (count($dbWords) >= 50) {
                // Veritabanında yeterli kelime varsa onları kullan
                return $dbWords;
            }
        } catch (\Exception $e) {
            Log::error("Veritabanından kelime alırken hata: " . $e->getMessage());
        }
        
        // En sık kullanılan Türkçe kelimeler (genişletilmiş liste)
        return [
            // Temel kelimeler
            'merhaba', 'nasıl', 'bugün', 'iyi', 'güzel', 'evet', 'hayır',
            'ben', 'sen', 'o', 'biz', 'siz', 'onlar', 'var', 'yok',
            'gelmek', 'gitmek', 'yapmak', 'etmek', 'olmak', 'bilmek',
            'görmek', 'bakmak', 'anlamak', 'söylemek', 'konuşmak',
            
            // Genişletilmiş kelimeler
            'zaman', 'kişi', 'yıl', 'yol', 'şey', 'gün', 'ev', 'su', 'iş',
            'göz', 'kadın', 'adam', 'çocuk', 'para', 'saat', 'kitap', 'el',
            'hayat', 'anne', 'baba', 'arkadaş', 'dost', 'sevgi', 'aşk',
            'mutluluk', 'hüzün', 'düşünce', 'fikir', 'beyin', 'kalp', 'nefes',
            'hava', 'ateş', 'toprak', 'doğa', 'çiçek', 'ağaç', 'kuş', 'kedi',
            'köpek', 'yemek', 'içmek', 'uyumak', 'koşmak', 'gülmek', 'ağlamak',
            'sevmek', 'anlamak', 'bilmek', 'öğrenmek', 'okumak', 'yazmak'
        ];
    }
    
    private function processCollectedData()
    {
        // Toplanan verileri işle ve ilişkileri kur
        $words = AIData::all();
        
        foreach ($words as $word) {
            // İlişkili kelimeleri bul
            $relatedWords = AIData::where('sentence', 'like', "%{$word->word}%")
                ->where('word', '!=', $word->word)
                ->pluck('word')
                ->toArray();
            
            // Duygusal bağlamı analiz et
            $emotionalContext = $this->analyzeEmotionalContext($word->sentence);
            
            // Verileri güncelle
            $word->update([
                'related_words' => array_slice($relatedWords, 0, 20),
                'emotional_context' => $emotionalContext,
                'confidence' => $this->calculateConfidence($word)
            ]);
        }
    }
    
    private function analyzeEmotionalContext($text)
    {
        $emotions = [
            'positive' => ['iyi', 'güzel', 'harika', 'mutlu', 'sevgi'],
            'negative' => ['kötü', 'üzgün', 'kızgın', 'korku', 'endişe'],
            'neutral' => ['var', 'yok', 'olmak', 'yapmak', 'etmek']
        ];
        
        $scores = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0
        ];
        
        foreach ($emotions as $emotion => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $scores[$emotion]++;
                }
            }
        }
        
        return $scores;
    }
    
    private function calculateConfidence($word)
    {
        // Güven skorunu hesapla
        $factors = [
            'frequency' => $word->frequency,
            'has_sentence' => !empty($word->sentence),
            'has_examples' => !empty($word->usage_examples),
            'has_related' => !empty($word->related_words)
        ];
        
        $score = 0;
        $score += $factors['frequency'] * 0.4;
        $score += $factors['has_sentence'] ? 0.2 : 0;
        $score += $factors['has_examples'] ? 0.2 : 0;
        $score += $factors['has_related'] ? 0.2 : 0;
        
        return min(1, $score);
    }
    
    private function learnSynonym($word1, $word2, $strength = 0.5)
    {
        try {
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            return $wordRelations->learnSynonym($word1, $word2, $strength);
        } catch (\Exception $e) {
            Log::error("Eş anlamlı öğrenme hatası: {$word1} - {$word2} - " . $e->getMessage());
            return false;
        }
    }
    
    private function learnAntonym($word1, $word2, $strength = 0.5)
    {
        try {
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            return $wordRelations->learnAntonym($word1, $word2, $strength);
        } catch (\Exception $e) {
            Log::error("Zıt anlamlı öğrenme hatası: {$word1} - {$word2} - " . $e->getMessage());
            return false;
        }
    }
    
    private function saveRelations()
    {
        try {
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            return $wordRelations->collectAndLearnRelations();
        } catch (\Exception $e) {
            Log::error("İlişkileri kaydetme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    private function createWordRelationships($words, $sentence) 
    {
        if (count($words) < 2) return false;
        
        try {
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Cümle düzeyinde tanımları öğren
            foreach ($words as $word) {
                $wordRelations->learnFromContextualData($word, null, $sentence);
            }
            
            // Kelimeler arasındaki ilişkileri kur
            for ($i = 0; $i < count($words); $i++) {
                for ($j = $i + 1; $j < count($words); $j++) {
                    if ($i != $j) {
                        $wordRelations->learnAssociation($words[$i], $words[$j], 'related', 0.3);
                    }
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error("Kelime ilişkilerini oluşturma hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belirli bir kelime ile ilgili tüm verileri topla
     */
    public function collectWordData($word)
    {
        if (empty($word) || strlen($word) < 2) {
            return [
                'success' => false,
                'message' => 'Geçersiz kelime: Kelime en az 2 karakter olmalıdır'
            ];
        }
        
        try {
            $result = [
                'word' => $word,
                'found' => false,
                'definition' => '',
                'synonyms' => [],
                'antonyms' => [],
                'related_words' => [],
                'categories' => [],
                'examples' => []
            ];
            
            // TDK Sözlükten kelime bilgisi al
            $tdkData = $this->collectWordFromTDK($word);
            if ($tdkData['found']) {
                $result['found'] = true;
                $result['definition'] = $tdkData['definition'];
                $result['synonyms'] = $tdkData['synonyms'];
                $result['examples'] = $tdkData['examples'];
                $result['categories'] = $tdkData['categories'];
            }
            
            // Wikipedia'dan kelime bilgisi al
            $wikiData = $this->collectWordFromWikipedia($word);
            if ($wikiData['found']) {
                $result['found'] = true;
                if (empty($result['definition']) && !empty($wikiData['definition'])) {
                    $result['definition'] = $wikiData['definition'];
                }
                $result['related_words'] = array_merge($result['related_words'], $wikiData['related_words']);
            }
            
            // Eğer kelime bulunduysa veritabanına kaydet
            if ($result['found']) {
                try {
                    $this->saveWordToDatabase($word, $result);
                    
                    // WordRelations sınıfını başlat
                    $wordRelations = app(\App\AI\Core\WordRelations::class);
                    
                    // Eş ve zıt anlamlıları öğren
                    if (!empty($result['synonyms'])) {
                        foreach ($result['synonyms'] as $synonym) {
                            try {
                                $this->learnSynonym($word, $synonym, 0.9);
                            } catch (\Exception $e) {
                                Log::warning("Eş anlamlı öğrenirken hata: $word - $synonym: " . $e->getMessage());
                            }
                        }
                    }
                    
                    if (!empty($result['antonyms'])) {
                        foreach ($result['antonyms'] as $antonym) {
                            try {
                                $this->learnAntonym($word, $antonym, 0.9);
                            } catch (\Exception $e) {
                                Log::warning("Zıt anlamlı öğrenirken hata: $word - $antonym: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // İlişkili kelimeleri öğren
                    if (!empty($result['related_words'])) {
                        foreach ($result['related_words'] as $relatedWord) {
                            try {
                                // WordRelations sınıfındaki metodu düzgün çağır
                                $wordRelations->learnAssociation($word, $relatedWord, 'web_collected', 0.7);
                            } catch (\Exception $e) {
                                Log::warning("İlişkili kelime öğrenirken hata: $word - $relatedWord: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Kelime tanımını öğren
                    if (!empty($result['definition'])) {
                        try {
                            $wordRelations->learnDefinition($word, $result['definition'], true);
                            
                            // Tanımdan kategori analizi yap
                            $categoryManager = app(\App\AI\Core\CategoryManager::class);
                            $categoryManager->learnFromText($result['definition']);
                        } catch (\Exception $e) {
                            Log::warning("Tanım öğrenirken hata: $word: " . $e->getMessage());
                        }
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Kelime ilişkilerini kaydetme hatası: $word - " . $e->getMessage());
                    // İlişki kaydetme hatası olsa bile kelime bulundu sayılır
                }
                
                return [
                    'success' => true,
                    'word' => $word,
                    'data' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Kelime bulunamadı: $word"
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("Kelime toplama hatası ($word): " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Kelime veri toplama hatası: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * TDK Sözlükten kelime bilgisi topla
     */
    private function collectWordFromTDK($word)
    {
        $result = [
            'found' => false,
            'definition' => '',
            'synonyms' => [],
            'antonyms' => [],
            'examples' => [],
            'categories' => []
        ];
        
        try {
            $response = Http::get($this->sources['sozluk'], [
                'ara' => $word
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Veri boş mu veya doğru formatta mı kontrolü
                if (empty($data) || !is_array($data) || empty($data[0]) || !isset($data[0]['anlamlarListe'])) {
                    return $result;
                }
                
                $result['found'] = true;
                
                // Tanımları al
                if (!empty($data[0]['anlamlarListe'])) {
                    foreach ($data[0]['anlamlarListe'] as $index => $meaning) {
                        // İlk tanımı ana tanım olarak al
                        if ($index === 0) {
                            $result['definition'] = $meaning['anlam'];
                        }
                        
                        // Her tanımı örnek olarak kaydet
                        $result['examples'][] = $meaning['anlam'];
                        
                        // Kelime kategorisini belirle
                        if (isset($meaning['ozelliklerListe']) && is_array($meaning['ozelliklerListe']) &&
                            !empty($meaning['ozelliklerListe'][0]) && isset($meaning['ozelliklerListe'][0]['tam_adi'])) {
                            $result['categories'][] = $meaning['ozelliklerListe'][0]['tam_adi'];
                        }
                    }
                }
                
                // Eş anlamlıları al
                if (isset($data[0]['es_anlamlilar']) && !empty($data[0]['es_anlamlilar'])) {
                    $synonyms = explode(', ', $data[0]['es_anlamlilar']);
                    $result['synonyms'] = array_map('trim', $synonyms);
                }
                
                // Örnek cümleler
                if (isset($data[0]['atasozu']) && is_array($data[0]['atasozu'])) {
                    foreach ($data[0]['atasozu'] as $example) {
                        $result['examples'][] = $example;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("TDK veri toplama hatası ($word): " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Wikipedia'dan kelime bilgisi topla
     */
    private function collectWordFromWikipedia($word)
    {
        $result = [
            'found' => false,
            'definition' => '',
            'related_words' => []
        ];
        
        try {
            // Önce kelime ile ilgili sayfa ara
            $searchResponse = Http::get($this->sources['wikipedia'], [
                'action' => 'query',
                'format' => 'json',
                'list' => 'search',
                'srsearch' => $word,
                'srlimit' => 1
            ]);
            
            if (!$searchResponse->successful() || 
                !isset($searchResponse->json()['query']) || 
                !isset($searchResponse->json()['query']['search']) || 
                empty($searchResponse->json()['query']['search'])) {
                return $result;
            }
            
            $pageId = $searchResponse->json()['query']['search'][0]['pageid'];
            
            // Sayfa içeriğini al
            $contentResponse = Http::get($this->sources['wikipedia'], [
                'action' => 'query',
                'format' => 'json',
                'prop' => 'extracts',
                'exintro' => true,
                'explaintext' => true,
                'pageids' => $pageId
            ]);
            
            if ($contentResponse->successful() && 
                isset($contentResponse->json()['query']) && 
                isset($contentResponse->json()['query']['pages']) && 
                isset($contentResponse->json()['query']['pages'][$pageId]) && 
                isset($contentResponse->json()['query']['pages'][$pageId]['extract'])) {
                
                $result['found'] = true;
                $content = $contentResponse->json()['query']['pages'][$pageId]['extract'];
                
                // İlk paragrafı tanım olarak kullan
                $paragraphs = explode("\n", $content);
                if (!empty($paragraphs[0])) {
                    $result['definition'] = $paragraphs[0];
                }
                
                // İçerikten ilgili kelimeleri çıkar
                $words = preg_split('/\s+/', $content);
                $words = array_filter($words, function($w) {
                    return strlen($w) > 3 && !in_array($w, ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden']);
                });
                
                $wordCounts = array_count_values($words);
                arsort($wordCounts);
                
                // En çok geçen 10 kelimeyi ilişkili kelimeler olarak al
                $relatedWords = array_slice(array_keys($wordCounts), 0, 10);
                $result['related_words'] = $relatedWords;
            }
            
        } catch (\Exception $e) {
            Log::error("Wikipedia veri toplama hatası ($word): " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Kelimeyi veritabanına kaydet
     */
    private function saveWordToDatabase($word, $data)
    {
        try {
            // Kategori belirle
            $category = !empty($data['categories']) ? $data['categories'][0] : 'general';
            
            // Cümle oluştur
            $sentence = !empty($data['definition']) ? $data['definition'] : '';
            
            // Kelimenin ilgili verilerini kaydet
            AIData::updateOrCreate(
                ['word' => $word],
                [
                    'sentence' => $sentence,
                    'category' => $category,
                    'context' => 'Web aramasından toplandı',
                    'language' => 'tr',
                    'frequency' => DB::raw('COALESCE(frequency, 0) + 5'),
                    'confidence' => 0.9,
                    'related_words' => json_encode($data['related_words']),
                    'usage_examples' => json_encode($data['examples']),
                    'metadata' => json_encode([
                        'source' => 'web_collector',
                        'collected_at' => now(),
                        'synonyms' => $data['synonyms'],
                        'antonyms' => $data['antonyms'],
                        'categories' => $data['categories']
                    ])
                ]
            );
            
            return true;
        } catch (\Exception $e) {
            Log::error("Kelime kaydetme hatası ($word): " . $e->getMessage());
            return false;
        }
    }
} 