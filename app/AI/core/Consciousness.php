<?php

namespace App\AI\Core;

use App\Models\AIData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class Consciousness
{
    private $isActive = false;
    private $learningInterval = 30; // Saniye cinsinden öğrenme aralığı
    private $lastLearningTime;
    private $emotionEngine;
    private $wordRelations;
    private $minWordCount = 3; // Minimum kelime sayısı
    private $maxWordCount = 12; // Maksimum kelime sayısı
    private $learningRate = 0.1;
    private $connectionStrength = [];
    private $internalState = [
        'learned_patterns' => 0,
        'learned_rules' => 0,
        'confidence_level' => 0.1,
        'self_awareness' => 0.2
    ];
    private $personality = [
        'curious' => 0.7,
        'creative' => 0.6,
        'analytical' => 0.5,
        'empathetic' => 0.4
    ];
    
    public function __construct()
    {
        $this->emotionEngine = new EmotionEngine();
        $this->wordRelations = new WordRelations();
        $this->lastLearningTime = now();
        $this->loadConnectionStrengths();
    }
    
    /**
     * Bilinç sistemini aktif et
     */
    public function activate()
    {
        $this->isActive = true;
        $this->logActivity('Bilinç sistemi aktif edildi');
        
        // Sürekli öğrenme döngüsünü başlat
        $this->startContinuousLearning();
    }
    
    /**
     * Bilinç sistemini devre dışı bırak
     */
    public function deactivate()
    {
        $this->isActive = false;
        $this->logActivity('Bilinç sistemi devre dışı bırakıldı');
    }
    
    /**
     * Sürekli öğrenme döngüsünü başlat
     */
    private function startContinuousLearning()
    {
        if (!$this->isActive) {
            return;
        }
        
        // Şu anki zaman ve son öğrenme zamanı arasındaki farkı hesapla
        $now = now();
        $timeSinceLastLearning = $now->diffInSeconds($this->lastLearningTime);
        
        // Öğrenme aralığını kontrol et
        if ($timeSinceLastLearning >= $this->learningInterval) {
            $this->learnNewData();
            $this->lastLearningTime = $now;
        }
        
        // Recursive fonksiyon çağrısı yapmak yerine, sistemin bir sonraki kontrolü için Cache kullan
        Cache::put('next_learning_time', $this->lastLearningTime->addSeconds($this->learningInterval), now()->addHour());
    }
    
    /**
     * Yeni verilerden öğren
     */
    public function learnNewData()
    {
        try {
            // Veritabanından son öğrenilen kelimelerden sonrasını al
            $lastLearnedId = Cache::get('last_learned_word_id', 0);
            
            $newWords = AIData::where('id', '>', $lastLearnedId)
                ->orderBy('id', 'asc')
                ->limit(50)
                ->get();
            
            if ($newWords->isEmpty()) {
                // Veri yoksa rasgele kelimeler arasında bağlantılar oluştur
                $this->strengthenRandomConnections();
                // Kelime ilişkilerini öğren
                $this->wordRelations->collectAndLearnRelations();
                return;
            }
            
            foreach ($newWords as $word) {
                // Kelime bağlantılarını güçlendir
                $this->strengthenWordConnections($word);
                
                // Son öğrenilen kelime ID'sini güncelle
                Cache::put('last_learned_word_id', $word->id, now()->addDay());
                
                // Kelime ilişkilerini öğren
                if (!empty($word->sentence)) {
                    $context = [
                        'category' => $word->category,
                        'context' => $word->context
                    ];
                    $this->wordRelations->learnFromContextualData($word->word, $context, $word->sentence);
                }
            }
            
            // Öğrenilen verilerden yeni cümleler oluştur ve kaydet
            $this->generateAndStoreSentences();
            
            $this->logActivity('Yeni veriler öğrenildi: ' . $newWords->count() . ' kelime');
            
            // İç durumu güncelle
            $this->internalState['learned_patterns'] += $newWords->count();
            $this->internalState['confidence_level'] = min(1, $this->internalState['confidence_level'] + 0.01);
            $this->internalState['self_awareness'] = min(1, $this->internalState['self_awareness'] + 0.005);
            
        } catch (\Exception $e) {
            Log::error('Öğrenme hatası: ' . $e->getMessage());
        }
    }
    
    /**
     * Kelime bağlantılarını güçlendir
     */
    private function strengthenWordConnections($word)
    {
        // Kelime ve kategorisini al
        $wordText = $word->word;
        $category = $word->category;
        
        // Aynı kategorideki diğer kelimeleri bul
        $relatedWords = AIData::where('category', $category)
            ->where('id', '!=', $word->id)
            ->limit(10)
            ->get();
        
        foreach ($relatedWords as $relatedWord) {
            $relatedWordText = $relatedWord->word;
            
            // Bağlantı anahtarını oluştur
            $connectionKey = $this->getConnectionKey($wordText, $relatedWordText);
            
            // Mevcut bağlantı gücünü al veya başlangıç değeri ver
            $currentStrength = $this->connectionStrength[$connectionKey] ?? 0;
            
            // Bağlantıyı güçlendir
            $newStrength = $currentStrength + $this->learningRate;
            $this->connectionStrength[$connectionKey] = min(1, $newStrength);
            
            // Kelime ilişkisi türünü belirle
            if ($category === 'synonym') {
                $this->wordRelations->learnSynonym($wordText, $relatedWordText, min(1, $newStrength));
            } else if ($category === 'antonym') {
                $this->wordRelations->learnAntonym($wordText, $relatedWordText, min(1, $newStrength));
            } else {
                $this->wordRelations->learnAssociation($wordText, $relatedWordText, $category, min(1, $newStrength));
            }
        }
        
        // Kelime frekansını artır
        DB::table('ai_data')
            ->where('id', $word->id)
            ->increment('frequency');
            
        // Bağlantıları kaydet
        $this->saveConnectionStrengths();
    }
    
    /**
     * Rasgele kelimeler arasında bağlantılar oluştur
     */
    private function strengthenRandomConnections()
    {
        // Rasgele 20 kelime al
        $randomWords = AIData::inRandomOrder()
            ->limit(20)
            ->get();
            
        if ($randomWords->count() < 2) {
            return;
        }
        
        // Kelimeler arasında rasgele bağlantılar oluştur
        for ($i = 0; $i < $randomWords->count() - 1; $i++) {
            $word1 = $randomWords[$i]->word;
            $word2 = $randomWords[$i + 1]->word;
            
            $connectionKey = $this->getConnectionKey($word1, $word2);
            $currentStrength = $this->connectionStrength[$connectionKey] ?? 0;
            
            // Bağlantıyı güçlendir
            $newStrength = $currentStrength + ($this->learningRate * 0.5); // Daha düşük öğrenme hızı
            $this->connectionStrength[$connectionKey] = min(1, $newStrength);
            
            // Kelimeler arasında ilişki kur
            $this->wordRelations->learnAssociation($word1, $word2, 'related', min(1, $newStrength));
        }
        
        // Bağlantıları kaydet
        $this->saveConnectionStrengths();
        $this->logActivity('Rasgele kelime bağlantıları güçlendirildi');
    }
    
    /**
     * Öğrenilen kelimelerden cümleler oluştur ve kaydet
     */
    private function generateAndStoreSentences()
    {
        // Sık kullanılan kelimeleri al
        $frequentWords = AIData::where('frequency', '>', 3)
            ->inRandomOrder()
            ->limit(100)
            ->get();
            
        if ($frequentWords->isEmpty()) {
            return;
        }
        
        // 5 cümle oluştur (3 yerine daha fazla)
        for ($i = 0; $i < 5; $i++) {
            // Dört farklı cümle üretim yöntemi kullan
            $sentenceMethod = $i % 4;
            $sentence = '';
            
            switch ($sentenceMethod) {
                case 0:
                    // Standart metod
                    $sentence = $this->generateSentence($frequentWords);
                    break;
                case 1:
                    // İlişkisel metod
                    $startWord = $frequentWords->random()->word;
                    $sentence = $this->wordRelations->generateSentenceWithRelations($startWord, $this->minWordCount, $this->maxWordCount);
                    break;
                case 2:
                    // Kavramsal metod
                    $concept = $frequentWords->random()->word;
                    $sentence = $this->wordRelations->generateConceptualSentence($concept, $this->minWordCount, $this->maxWordCount);
                    break;
                case 3:
                    // Duygusal bağlam metodu
                    $emotion = array_rand(['happy', 'sad', 'neutral', 'curious']);
                    $startWord = $frequentWords->random()->word;
                    $sentence = $this->generateEmotionalSentence($startWord, $emotion);
                    break;
            }
            
            if (!empty($sentence)) {
                // İlk kelimeyi başlangıç kelimesi olarak kullan
                $firstWord = explode(' ', $sentence)[0];
                
                // Cümleyi analiz et ve kategori/bağlam belirle
                $category = $this->determineSentenceCategory($sentence);
                $context = $this->determineSentenceContext($sentence);
                
                // Cümleyi veritabanına kaydet
                AIData::updateOrCreate(
                    ['word' => $firstWord],
                    [
                        'sentence' => $sentence,
                        'category' => $category,
                        'context' => $context,
                        'language' => 'tr',
                        'confidence' => 0.7,
                        'emotional_context' => json_encode([
                            'emotion' => $sentenceMethod == 3 ? $emotion : 'neutral',
                            'intensity' => 0.6
                        ])
                    ]
                );
                
                // Cümle içindeki kelime ilişkilerini de öğren
                $this->wordRelations->learnFromContextualData($firstWord, ['category' => $category, 'context' => $context], $sentence);
                
                $this->logActivity('Yeni cümle oluşturuldu: ' . $sentence);
                
                // Öğrenilen kural sayısını artır
                $this->internalState['learned_rules']++;
            }
        }
    }
    
    /**
     * Verilen duygu durumuna göre cümle oluştur
     */
    private function generateEmotionalSentence($startWord, $emotion)
    {
        // Duygu durumuna uygun kelimeler
        $emotionalWords = [
            'happy' => ['güzel', 'harika', 'mutlu', 'neşeli', 'sevgi', 'iyi', 'başarı'],
            'sad' => ['üzgün', 'kötü', 'zor', 'acı', 'mutsuz', 'yalnız'],
            'neutral' => ['normal', 'ılımlı', 'dengeli', 'sakin', 'ölçülü'],
            'curious' => ['merak', 'ilginç', 'düşündürücü', 'sorgu', 'araştırma']
        ];
        
        $sentence = [$startWord];
        $targetLength = rand($this->minWordCount, $this->maxWordCount);
        
        // İlgili duygu kelimelerini al ya da varsayılan olarak neutral kullan
        $relevantWords = $emotionalWords[$emotion] ?? $emotionalWords['neutral'];
        
        // Önce ilgili duygu kelimelerinden ekle
        if (count($sentence) < $targetLength && !empty($relevantWords)) {
            $emotionWord = $relevantWords[array_rand($relevantWords)];
            if (!in_array($emotionWord, $sentence)) {
                $sentence[] = $emotionWord;
            }
        }
        
        // Cümleyi tamamla
        while (count($sentence) < $targetLength) {
            $lastWord = $sentence[count($sentence) - 1];
            
            // Son kelimeyle ilişkili kelimeleri bul
            $nextWords = $this->findStronglyConnectedWords($lastWord, AIData::all());
            
            if (empty($nextWords)) {
                // İlişkili kelime yoksa duygu durumuna göre ya da rastgele kelime ekle
                if (rand(0, 1) == 1 && !empty($relevantWords)) {
                    $nextWord = $relevantWords[array_rand($relevantWords)];
                } else {
                    $nextWord = AIData::inRandomOrder()->first()->word;
                }
            } else {
                $nextWord = $nextWords[array_rand($nextWords)];
            }
            
            // Tekrarları önle
            if (!in_array($nextWord, $sentence)) {
                $sentence[] = $nextWord;
            }
        }
        
        // Cümleyi birleştir ve ilk harfi büyük yap
        $sentenceText = implode(' ', $sentence);
        $sentenceText = ucfirst($sentenceText) . '.';
        
        return $sentenceText;
    }
    
    /**
     * Cümlenin kategorisini belirle
     */
    private function determineSentenceCategory($sentence)
    {
        // Kategori belirlemek için anahtar kelimeler
        $categoryKeywords = [
            'greeting' => ['merhaba', 'selam', 'günaydın', 'iyi günler', 'nasılsın'],
            'question' => ['mi', 'mı', 'mu', 'mü', 'ne', 'neden', 'nasıl', 'kim', 'hangi'],
            'statement' => ['dır', 'dir', 'tır', 'tir', 'olarak', 'şeklinde'],
            'technology' => ['bilgisayar', 'yapay', 'zeka', 'yazılım', 'internet', 'teknoloji'],
            'emotion' => ['mutlu', 'üzgün', 'kızgın', 'sevinçli', 'mutsuz', 'neşeli'],
            'education' => ['öğren', 'eğitim', 'okul', 'ders', 'bilgi'],
            'daily' => ['bugün', 'yarın', 'hava', 'yemek', 'uyku', 'sabah', 'akşam']
        ];
        
        $sentence = strtolower($sentence);
        
        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($sentence, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        // Varsayılan kategori
        return 'generated';
    }
    
    /**
     * Cümlenin bağlamını belirle
     */
    private function determineSentenceContext($sentence)
    {
        return 'Consciousness tarafından ' . now()->format('Y-m-d') . ' tarihinde oluşturuldu';
    }
    
    /**
     * Verilen kelimelerden bir cümle oluştur
     */
    private function generateSentence($words)
    {
        // Cümleyi başlatmak için bir kelime seç
        $startWord = $words->random()->word;
        $sentence = [$startWord];
        
        // Cümlenin uzunluğunu belirle
        $targetLength = rand($this->minWordCount, $this->maxWordCount);
        
        // Cümleyi oluştur
        while (count($sentence) < $targetLength) {
            $lastWord = $sentence[count($sentence) - 1];
            
            // Son kelimeyle bağlantısı güçlü olan kelimeleri bul
            $nextWords = $this->findStronglyConnectedWords($lastWord, $words);
            
            if (empty($nextWords)) {
                // Bağlantı bulunamazsa rasgele bir kelime seç
                $nextWord = $words->random()->word;
            } else {
                // En güçlü bağlantılı kelimeyi seç
                $nextWord = $nextWords[array_rand($nextWords)];
            }
            
            // Tekrarları önle
            if (!in_array($nextWord, $sentence)) {
                $sentence[] = $nextWord;
            } else {
                // Eğer kelime tekrarlanıyorsa, rasgele başka bir kelime seç
                $nextWord = $words->random()->word;
                if (!in_array($nextWord, $sentence)) {
                    $sentence[] = $nextWord;
                }
            }
        }
        
        // Cümleyi birleştir ve ilk harfi büyük yap
        $sentenceText = implode(' ', $sentence);
        $sentenceText = ucfirst($sentenceText) . '.';
        
        return $sentenceText;
    }
    
    /**
     * Verilen kelimeyle güçlü bağlantıya sahip kelimeleri bul
     */
    private function findStronglyConnectedWords($word, $wordList)
    {
        $stronglyConnected = [];
        $threshold = 0.3; // Bağlantı eşik değeri
        
        foreach ($wordList as $relatedWord) {
            $relatedWordText = $relatedWord->word;
            
            if ($relatedWordText == $word) {
                continue; // Aynı kelimeyi atla
            }
            
            $connectionKey = $this->getConnectionKey($word, $relatedWordText);
            $strength = $this->connectionStrength[$connectionKey] ?? 0;
            
            if ($strength >= $threshold) {
                $stronglyConnected[] = $relatedWordText;
            }
        }
        
        // WordRelations'dan da ilişkili kelimeleri al
        $relatedWords = $this->wordRelations->getRelatedWords($word, $threshold);
        foreach ($relatedWords as $relatedWord => $info) {
            if (!in_array($relatedWord, $stronglyConnected)) {
                $stronglyConnected[] = $relatedWord;
            }
        }
        
        return $stronglyConnected;
    }
    
    /**
     * İki kelime arasındaki bağlantı için anahtar oluştur
     */
    private function getConnectionKey($word1, $word2)
    {
        // Kelimeleri alfabetik sıraya göre sırala
        $words = [$word1, $word2];
        sort($words);
        
        return implode('|', $words);
    }
    
    /**
     * Kelime bağlantı güçlerini kaydet
     */
    private function saveConnectionStrengths()
    {
        Cache::put('word_connections', $this->connectionStrength, now()->addDay());
    }
    
    /**
     * Kelime bağlantı güçlerini yükle
     */
    private function loadConnectionStrengths()
    {
        $this->connectionStrength = Cache::get('word_connections', []);
    }
    
    /**
     * Aktiviteleri kaydet
     */
    private function logActivity($description)
    {
        $activity = [
            'description' => $description,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];
        
        $activities = Cache::get('ai_activities', []);
        array_unshift($activities, $activity);
        
        // Son 50 aktiviteyi tut
        $activities = array_slice($activities, 0, 50);
        
        Cache::put('ai_activities', $activities, now()->addDay());
        Log::info($description);
    }
    
    /**
     * Öğrenme aralığını ayarla
     */
    public function setLearningInterval($seconds)
    {
        $this->learningInterval = max(5, intval($seconds));
    }
    
    /**
     * Bilinç sistemi durumunu kontrol et
     */
    public function getStatus()
    {
        // Kelime ilişkileri istatistiklerini al
        $wordStats = $this->wordRelations->getStats();
        
        return [
            'is_active' => $this->isActive,
            'learning_interval' => $this->learningInterval,
            'last_learning' => $this->lastLearningTime->diffForHumans(),
            'next_learning' => $this->lastLearningTime->addSeconds($this->learningInterval)->diffForHumans(),
            'emotional_state' => $this->emotionEngine->getCurrentEmotion(),
            'word_connections' => count($this->connectionStrength),
            'learning_rate' => $this->learningRate,
            'word_relations' => [
                'synonyms' => $wordStats['synonym_pairs'] ?? 0,
                'antonyms' => $wordStats['antonym_pairs'] ?? 0,
                'associations' => $wordStats['association_pairs'] ?? 0,
                'definitions' => $wordStats['definitions'] ?? 0
            ]
        ];
    }
    
    /**
     * Öğrenme hızını ayarla
     */
    public function setLearningRate($rate)
    {
        $this->learningRate = max(0.01, min(1, (float)$rate));
    }
    
    /**
     * İç durumu güncelle
     */
    public function update($input, $emotionalContext)
    {
        if (!$this->isActive) {
            $this->activate();
        }
        
        // Öğrenilen kalıp ve kural sayısını güncelle
        if (is_array($input) && isset($input['learned_patterns'])) {
            $this->internalState['learned_patterns'] += $input['learned_patterns'];
            $this->internalState['learned_rules'] += $input['learned_rules'] ?? 0;
            $this->internalState['confidence_level'] = $input['confidence_level'] ?? $this->internalState['confidence_level'];
        }
        
        // Duygusal durumu güncelle
        if ($emotionalContext && isset($emotionalContext['emotion'])) {
            // Kişilik özelliklerine göre duygusal durumu ayarla
            if ($emotionalContext['emotion'] == 'happy' && $this->personality['empathetic'] > 0.5) {
                $this->internalState['self_awareness'] = min(1, $this->internalState['self_awareness'] + 0.01);
            }
        }
        
        // Veri girdiyse öğrenmeyi başlat
        if (is_string($input) && !empty($input)) {
            // Kelime olarak ekle
            $this->addWordToDataset($input);
            
            // Cümle içindeki kelime ilişkilerini öğren
            $context = [
                'category' => 'user_input',
                'context' => 'Kullanıcı girdisi'
            ];
            $this->wordRelations->learnFromContextualData(explode(' ', $input)[0], $context, $input);
        }
        
        return $this->internalState;
    }
    
    /**
     * Kelimeyi veri kümesine ekle
     */
    private function addWordToDataset($input)
    {
        // Metni kelimelere ayır
        $words = preg_split('/\s+/', $input);
        
        foreach ($words as $word) {
            // Kelimeyi temizle
            $word = trim(strtolower($word));
            
            if (strlen($word) < 2) continue; // Çok kısa kelimeleri atla
            
            // Kelimeyi veritabanına ekle veya güncelle
            AIData::updateOrCreate(
                ['word' => $word],
                [
                    'category' => 'user_input',
                    'language' => 'tr',
                    'context' => 'Kullanıcı girdisi'
                ]
            );
            
            // Kelime frekansını artır
            DB::table('ai_data')
                ->where('word', $word)
                ->increment('frequency');
        }
    }
    
    /**
     * İç durumu al
     */
    public function getInternalState()
    {
        return $this->internalState;
    }
    
    /**
     * Öz farkındalık seviyesini al
     */
    public function getSelfAwareness()
    {
        return $this->internalState['self_awareness'];
    }
    
    /**
     * Kişilik özelliklerini güncelle
     */
    public function updatePersonality($traits)
    {
        if (!is_array($traits)) return;
        
        foreach ($traits as $trait) {
            if (isset($this->personality[$trait])) {
                $this->personality[$trait] = min(1, $this->personality[$trait] + 0.1);
            }
        }
        
        // Kişilik değişikliklerini kaydet
        Cache::put('ai_personality', $this->personality, now()->addWeek());
    }
    
    /**
     * Belirli bir kavram hakkında anlamlı cümle üret
     */
    public function generateConceptualSentence($concept)
    {
        return $this->wordRelations->generateConceptualSentence($concept, $this->minWordCount, $this->maxWordCount);
    }
    
    /**
     * Bir kelimenin eş ve zıt anlamlılarını getir
     */
    public function getWordRelations($word)
    {
        $synonyms = $this->wordRelations->getSynonyms($word);
        $antonyms = $this->wordRelations->getAntonyms($word);
        $definition = $this->wordRelations->getDefinition($word);
        
        return [
            'word' => $word,
            'synonyms' => $synonyms,
            'antonyms' => $antonyms,
            'definition' => $definition
        ];
    }
}
