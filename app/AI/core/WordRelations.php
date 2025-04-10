<?php

namespace App\AI\Core;

use App\Models\WordRelation;
use App\Models\WordDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WordRelations
{
    private $relationTypes = ['synonym', 'antonym', 'association', 'definition'];
    private $synonyms = [];
    private $antonyms = [];
    private $associations = [];
    private $definitions = [];
    private $language = 'tr';
    private $cacheTime = 1440; // Dakika cinsinden (24 saat)
    private $minWordLength = 2;
    
    public function __construct()
    {
        $this->loadCachedData();
    }
    
    /**
     * Önbelleğe alınmış verileri yükle
     */
    private function loadCachedData()
    {
        $this->synonyms = Cache::get('word_synonyms', []);
        $this->antonyms = Cache::get('word_antonyms', []);
        $this->associations = Cache::get('word_associations', []);
        $this->definitions = Cache::get('word_definitions', []);
    }
    
    /**
     * Önbelleğe alınmış verileri kaydet
     */
    private function saveCachedData()
    {
        Cache::put('word_synonyms', $this->synonyms, now()->addMinutes($this->cacheTime));
        Cache::put('word_antonyms', $this->antonyms, now()->addMinutes($this->cacheTime));
        Cache::put('word_associations', $this->associations, now()->addMinutes($this->cacheTime));
        Cache::put('word_definitions', $this->definitions, now()->addMinutes($this->cacheTime));
    }
    
    /**
     * Eş anlamlı kelime ilişkisi oluştur
     */
    public function learnSynonym($word1, $word2, $strength = 0.5)
    {
        if (!$this->isValidWord($word1) || !$this->isValidWord($word2)) {
            return false;
        }
        
        // Kelimeler aynıysa işlem yapma
        if ($word1 === $word2) {
            return false;
        }
        
        // Önbellekte güncelle
        if (!isset($this->synonyms[$word1])) {
            $this->synonyms[$word1] = [];
        }
        
        if (!isset($this->synonyms[$word2])) {
            $this->synonyms[$word2] = [];
        }
        
        $this->synonyms[$word1][$word2] = $strength;
        $this->synonyms[$word2][$word1] = $strength;
        
        // Veritabanında güncelle
        try {
            WordRelation::updateOrCreate(
                [
                    'word' => $word1,
                    'related_word' => $word2,
                    'relation_type' => 'synonym',
                    'language' => $this->language
                ],
                [
                    'strength' => $strength
                ]
            );
            
            WordRelation::updateOrCreate(
                [
                    'word' => $word2,
                    'related_word' => $word1,
                    'relation_type' => 'synonym',
                    'language' => $this->language
                ],
                [
                    'strength' => $strength
                ]
            );
            
            $this->saveCachedData();
            return true;
        } catch (\Exception $e) {
            Log::error('Eş anlamlı kaydetme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Zıt anlamlı kelime ilişkisi oluştur
     */
    public function learnAntonym($word1, $word2, $strength = 0.5)
    {
        if (!$this->isValidWord($word1) || !$this->isValidWord($word2)) {
            return false;
        }
        
        // Kelimeler aynıysa işlem yapma
        if ($word1 === $word2) {
            return false;
        }
        
        // Önbellekte güncelle
        if (!isset($this->antonyms[$word1])) {
            $this->antonyms[$word1] = [];
        }
        
        if (!isset($this->antonyms[$word2])) {
            $this->antonyms[$word2] = [];
        }
        
        $this->antonyms[$word1][$word2] = $strength;
        $this->antonyms[$word2][$word1] = $strength;
        
        // Veritabanında güncelle
        try {
            WordRelation::updateOrCreate(
                [
                    'word' => $word1,
                    'related_word' => $word2,
                    'relation_type' => 'antonym',
                    'language' => $this->language
                ],
                [
                    'strength' => $strength
                ]
            );
            
            WordRelation::updateOrCreate(
                [
                    'word' => $word2,
                    'related_word' => $word1,
                    'relation_type' => 'antonym',
                    'language' => $this->language
                ],
                [
                    'strength' => $strength
                ]
            );
            
            $this->saveCachedData();
            return true;
        } catch (\Exception $e) {
            Log::error('Zıt anlamlı kaydetme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * İlişkili kelime bağlantısı oluştur
     */
    public function learnAssociation($word1, $word2, $context = 'related', $strength = 0.3)
    {
        if (!$this->isValidWord($word1) || !$this->isValidWord($word2)) {
            return false;
        }
        
        // Kelimeler aynıysa işlem yapma
        if ($word1 === $word2) {
            return false;
        }
        
        // Önbellekte güncelle
        if (!isset($this->associations[$word1])) {
            $this->associations[$word1] = [];
        }
        
        if (!isset($this->associations[$word2])) {
            $this->associations[$word2] = [];
        }
        
        // İlişkili kelimeleri güncelle
        $this->associations[$word1][$word2] = [
            'strength' => $strength,
            'context' => $context
        ];
        
        $this->associations[$word2][$word1] = [
            'strength' => $strength,
            'context' => $context
        ];
        
        // Veritabanında güncelle
        try {
            WordRelation::updateOrCreate(
                [
                    'word' => $word1,
                    'related_word' => $word2,
                    'relation_type' => 'association',
                    'language' => $this->language
                ],
                [
                    'strength' => $strength,
                    'context' => $context
                ]
            );
            
            $this->saveCachedData();
            return true;
        } catch (\Exception $e) {
            Log::error('İlişki kaydetme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kelime tanımı ekle
     */
    public function learnDefinition($word, $definition, $isVerified = false)
    {
        if (!$this->isValidWord($word) || empty($definition)) {
            return false;
        }
        
        // Tanımı kısalt - çok uzun olması veritabanı hatası oluşturabilir
        if (strlen($definition) > 1000) {
            $definition = substr($definition, 0, 997) . '...';
        }
        
        // Önbellekte güncelle
        $this->definitions[$word] = $definition;
        
        // Veritabanında güncelle
        try {
            WordDefinition::updateOrCreate(
                [
                    'word' => $word,
                    'language' => $this->language
                ],
                [
                    'definition' => $definition,
                    'is_verified' => $isVerified
                ]
            );
            
            $this->saveCachedData();
            return true;
        } catch (\Exception $e) {
            Log::error('Tanım kaydetme hatası: ' . $e->getMessage() . ' - Kelime: ' . $word);
            
            // Kritik hata oluştuğunda farklı bir yöntemle dene
            try {
                DB::table('word_definitions')->updateOrInsert(
                    ['word' => $word, 'language' => $this->language],
                    [
                        'definition' => $definition,
                        'is_verified' => $isVerified ? 1 : 0,
                        'updated_at' => now(),
                        'created_at' => now()
                    ]
                );
                return true;
            } catch (\Exception $e2) {
                Log::error('Alternatif tanım kaydetme hatası: ' . $e2->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Kelimeyi ve cümleyi analiz ederek öğren
     */
    public function learnFromContextualData($word, $contextData, $sentence)
    {
        if (!$this->isValidWord($word) || empty($sentence)) {
            return false;
        }
        
        // Cümle uzunluğunu kontrol et
        if (strlen($sentence) > 1000) {
            $sentence = substr($sentence, 0, 997) . '...';
        }
        
        try {
            // Kelime bir tanım cümlesi olabilir
            $this->detectAndLearnDefinition($word, $sentence);
            
            // Cümleden eş ve zıt anlamlı kelimeleri tespit et
            $this->detectSynonymsAndAntonyms($word, $sentence);
            
            // Cümledeki diğer kelimelerle ilişkilendir
            $this->associateWithOtherWords($word, $sentence, $contextData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Bağlamsal öğrenme hatası: ' . $e->getMessage() . ' - Kelime: ' . $word);
            return false;
        }
    }
    
    /**
     * Cümlede kelime tanımı olup olmadığını kontrol et
     */
    private function detectAndLearnDefinition($word, $sentence)
    {
        // Tanımlama kalıplarını kontrol et
        $patterns = [
            '/' . preg_quote($word, '/') . '\s+(?:demek|kelimesi|sözcüğü)\s+(.+)(?:demektir|anlamındadır|anlamına gelir)/',
            '/' . preg_quote($word, '/') . '\s+(?:bir|bir tür|bir çeşit)\s+(.+)/',
            '/(.+)\s+(?:olarak bilinen|olarak adlandırılan)\s+' . preg_quote($word, '/') . '/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sentence, $matches) && isset($matches[1])) {
                $definition = trim($matches[1]);
                if (!empty($definition)) {
                    $this->learnDefinition($word, $definition);
                    return true;
                }
            }
        }
        
        // Eğer cümle kısa ve kelimeyle başlıyorsa, tanım olabilir
        if (str_word_count($sentence) <= 20 && stripos($sentence, $word) === 0) {
            $this->learnDefinition($word, $sentence);
            return true;
        }
        
        return false;
    }
    
    /**
     * Cümlede eş ve zıt anlamlı kelimeleri tespit et
     */
    private function detectSynonymsAndAntonyms($word, $sentence)
    {
        // Eş anlamlı kalıpları
        $synonymPatterns = [
            '/' . preg_quote($word, '/') . '\s+(?:yani|veya|diğer adıyla|başka bir deyişle)\s+(\w+)/',
            '/(\w+)\s+(?:yani|veya|diğer adıyla|başka bir deyişle)\s+' . preg_quote($word, '/') . '/',
            '/' . preg_quote($word, '/') . '\s+(?:ile|ve)\s+(\w+)\s+(?:aynı|benzer|eş)/'
        ];
        
        // Zıt anlamlı kalıpları
        $antonymPatterns = [
            '/' . preg_quote($word, '/') . '\s+(?:değil|aksine|tersine)\s+(\w+)/',
            '/(\w+)\s+(?:değil|aksine|tersine)\s+' . preg_quote($word, '/') . '/',
            '/' . preg_quote($word, '/') . '\s+(?:ile|ve)\s+(\w+)\s+(?:zıt|karşıt|ters)/'
        ];
        
        // Eş anlamlıları bul
        foreach ($synonymPatterns as $pattern) {
            if (preg_match_all($pattern, $sentence, $matches) && isset($matches[1])) {
                foreach ($matches[1] as $synonym) {
                    $synonym = trim($synonym);
                    if ($this->isValidWord($synonym)) {
                        $this->learnSynonym($word, $synonym, 0.7);
                    }
                }
            }
        }
        
        // Zıt anlamlıları bul
        foreach ($antonymPatterns as $pattern) {
            if (preg_match_all($pattern, $sentence, $matches) && isset($matches[1])) {
                foreach ($matches[1] as $antonym) {
                    $antonym = trim($antonym);
                    if ($this->isValidWord($antonym)) {
                        $this->learnAntonym($word, $antonym, 0.7);
                    }
                }
            }
        }
    }
    
    /**
     * Kelimeyi cümledeki diğer kelimelerle ilişkilendir
     */
    private function associateWithOtherWords($word, $sentence, $contextData)
    {
        // Cümleyi kelimelere ayır
        $words = preg_split('/\s+/', strtolower($sentence));
        $words = array_filter($words, function($w) {
            return $this->isValidWord($w);
        });
        
        // Kelime bağlamını belirle
        $context = 'sentence';
        if ($contextData && isset($contextData['category'])) {
            $context = $contextData['category'];
        }
        
        // Her kelimeyle ilişki kur
        foreach ($words as $w) {
            if ($w !== $word) {
                $this->learnAssociation($word, $w, $context, 0.3);
            }
        }
    }
    
    /**
     * Kelime ilişkilerini topla ve öğren
     */
    public function collectAndLearnRelations()
    {
        try {
            // Veritabanından kelime ilişkilerini al
            $relations = DB::table('word_relations')
                ->select('word', 'related_word', 'relation_type', 'strength', 'context')
                ->where('language', $this->language)
                ->get();
            
            // Kelime tanımlarını al
            $definitions = DB::table('word_definitions')
                ->select('word', 'definition')
                ->where('language', $this->language)
                ->get();
            
            $processed = 0;
            $learned = 0;
            
            // İlişkileri işle
            foreach ($relations as $relation) {
                $processed++;
                
                switch ($relation->relation_type) {
                    case 'synonym':
                        $this->learnSynonym($relation->word, $relation->related_word, $relation->strength);
                        $learned++;
                        break;
                    case 'antonym':
                        $this->learnAntonym($relation->word, $relation->related_word, $relation->strength);
                        $learned++;
                        break;
                    case 'association':
                        $this->learnAssociation($relation->word, $relation->related_word, $relation->context, $relation->strength);
                        $learned++;
                        break;
                }
            }
            
            // Tanımları işle
            foreach ($definitions as $def) {
                $processed++;
                $this->learnDefinition($def->word, $def->definition);
                $learned++;
            }
            
            $this->saveCachedData();
            
            return [
                'success' => true,
                'processed' => $processed,
                'learned' => $learned
            ];
            
        } catch (\Exception $e) {
            Log::error('İlişki toplama hatası: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'İlişki toplama hatası: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Eş anlamlı kelimeleri getir
     */
    public function getSynonyms($word)
    {
        if (!$this->isValidWord($word)) {
            return [];
        }
        
        // Öncelikle önbellekten kontrol et
        if (isset($this->synonyms[$word])) {
            return $this->synonyms[$word];
        }
        
        // Veritabanından getir
        try {
            $relations = WordRelation::where('word', $word)
                ->where('relation_type', 'synonym')
                ->where('language', $this->language)
                ->get();
            
            $synonyms = [];
            foreach ($relations as $relation) {
                $synonyms[$relation->related_word] = $relation->strength;
            }
            
            // Önbelleğe ekle
            $this->synonyms[$word] = $synonyms;
            $this->saveCachedData();
            
            return $synonyms;
        } catch (\Exception $e) {
            Log::error('Eş anlamlı getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Zıt anlamlı kelimeleri getir
     */
    public function getAntonyms($word)
    {
        if (!$this->isValidWord($word)) {
            return [];
        }
        
        // Öncelikle önbellekten kontrol et
        if (isset($this->antonyms[$word])) {
            return $this->antonyms[$word];
        }
        
        // Veritabanından getir
        try {
            $relations = WordRelation::where('word', $word)
                ->where('relation_type', 'antonym')
                ->where('language', $this->language)
                ->get();
            
            $antonyms = [];
            foreach ($relations as $relation) {
                $antonyms[$relation->related_word] = $relation->strength;
            }
            
            // Önbelleğe ekle
            $this->antonyms[$word] = $antonyms;
            $this->saveCachedData();
            
            return $antonyms;
        } catch (\Exception $e) {
            Log::error('Zıt anlamlı getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * İlişkili kelimeleri getir
     */
    public function getRelatedWords($word, $threshold = 0.0)
    {
        if (!$this->isValidWord($word)) {
            return [];
        }
        
        $relatedWords = [];
        
        // Eş anlamlıları ekle
        $synonyms = $this->getSynonyms($word);
        foreach ($synonyms as $synonym => $strength) {
            if ($strength >= $threshold) {
                $relatedWords[$synonym] = [
                    'type' => 'synonym',
                    'strength' => $strength
                ];
            }
        }
        
        // Zıt anlamlıları ekle
        $antonyms = $this->getAntonyms($word);
        foreach ($antonyms as $antonym => $strength) {
            if ($strength >= $threshold) {
                $relatedWords[$antonym] = [
                    'type' => 'antonym',
                    'strength' => $strength
                ];
            }
        }
        
        // İlişkili kelimeleri ekle
        if (isset($this->associations[$word])) {
            foreach ($this->associations[$word] as $related => $info) {
                if ($info['strength'] >= $threshold) {
                    $relatedWords[$related] = [
                        'type' => 'association',
                        'strength' => $info['strength'],
                        'context' => $info['context']
                    ];
                }
            }
        } else {
            // Veritabanından getir
            try {
                $relations = WordRelation::where('word', $word)
                    ->where('relation_type', 'association')
                    ->where('strength', '>=', $threshold)
                    ->where('language', $this->language)
                    ->get();
                
                foreach ($relations as $relation) {
                    $relatedWords[$relation->related_word] = [
                        'type' => 'association',
                        'strength' => $relation->strength,
                        'context' => $relation->context
                    ];
                }
            } catch (\Exception $e) {
                Log::error('İlişkili kelime getirme hatası: ' . $e->getMessage());
            }
        }
        
        return $relatedWords;
    }
    
    /**
     * Kelime tanımını getir
     */
    public function getDefinition($word)
    {
        if (!$this->isValidWord($word)) {
            return '';
        }
        
        // Öncelikle önbellekten kontrol et
        if (isset($this->definitions[$word])) {
            return $this->definitions[$word];
        }
        
        // Veritabanından getir
        try {
            $definition = WordDefinition::where('word', $word)
                ->where('language', $this->language)
                ->first();
            
            if ($definition) {
                // Önbelleğe ekle
                $this->definitions[$word] = $definition->definition;
                $this->saveCachedData();
                
                return $definition->definition;
            }
        } catch (\Exception $e) {
            Log::error('Tanım getirme hatası: ' . $e->getMessage());
        }
        
        return '';
    }
    
    /**
     * Bir kavram hakkında anlamlı cümle üret
     */
    public function generateConceptualSentence($concept, $minLength = 3, $maxLength = 12)
    {
        if (empty($concept) || !$this->isValidWord($concept)) {
            return '';
        }
        
        // Kavram için tanım kontrolü
        $definition = $this->getDefinition($concept);
        
        // Kavramla ilişkili kelimeleri al
        $relations = $this->getRelatedWords($concept, 0.3);
        $synonyms = $this->getSynonyms($concept);
        
        // İlişkili kavramlardan bir alt kavram seç
        $subConcepts = [];
        if (!empty($relations)) {
            foreach ($relations as $word => $info) {
                $subConcepts[] = $word;
            }
        }
        
        // Eş anlamlıları ekle
        foreach ($synonyms as $word => $strength) {
            if (!in_array($word, $subConcepts)) {
                $subConcepts[] = $word;
            }
        }
        
        // Veritabanından ilişkili kelimeler çek
        try {
            $dbRelations = WordRelation::where('word', $concept)
                ->where('relation_type', 'association')
                ->where('strength', '>', 0.2)
                ->limit(10)
                ->get();
                
            foreach ($dbRelations as $relation) {
                if (!in_array($relation->related_word, $subConcepts)) {
                    $subConcepts[] = $relation->related_word;
                }
            }
        } catch (\Exception $e) {
            Log::error('Kavram ilişkili kelime hatası: ' . $e->getMessage());
        }
        
        // Türkçe dil kurallarına uygun olarak cümle oluştur
        $sentence = [];
        
        // Kavramın kendisi ile başla
        $sentence[] = $concept;
        
        // Cümleyi oluşturmak için gereken bağlaçlar ve yapılar
        $connectors = ['ve', 'ile', 'için', 'gibi', 'olarak', 'sayesinde', 'nedeniyle', 'dolayısıyla'];
        $verbs = ['vardır', 'oluşur', 'sağlar', 'içerir', 'bulunur', 'görülür', 'yapılır', 'edilir', 'olur'];
        $ending = ['dir', 'dır', 'tir', 'tır', 'dur', 'dür'];
        $adjectives = ['güzel', 'önemli', 'değerli', 'gerekli', 'yararlı', 'etkili', 'muhteşem'];
        
        // İlişkili kelime yoksa definition'dan kelimeler kullan
        if (empty($subConcepts) && !empty($definition)) {
            $words = explode(' ', $definition);
            foreach ($words as $word) {
                $word = trim($word, '.,;:?!');
                if (strlen($word) > 3 && !in_array($word, $sentence)) {
                    $subConcepts[] = $word;
                }
            }
        }
        
        // Hala yoksa rasgele veri ekle
        if (empty($subConcepts)) {
            $subConcepts = ['önemli', 'yararlı', 'değerli', 'gerekli'];
        }
        
        // Kavram hakkında anlamlı bir cümle oluştur
        $targetLength = mt_rand($minLength, $maxLength);
        
        // Önce bir sıfat ekle (50% olasılık)
        if (count($sentence) < $targetLength && mt_rand(0, 1) == 1) {
            $adjective = $adjectives[array_rand($adjectives)];
            if (!in_array($adjective, $sentence)) {
                $sentence[] = $adjective;
            }
        }
        
        // Alt kavramları ekle
        while (count($sentence) < $targetLength - 2 && !empty($subConcepts)) {
            // Bir bağlaç ekle
            if (count($sentence) < $targetLength && mt_rand(0, 2) == 1) {
                $connector = $connectors[array_rand($connectors)];
                if (!in_array($connector, $sentence)) {
                    $sentence[] = $connector;
                }
            }
            
            // Rasgele bir alt kavram seç
            $nextWord = $subConcepts[array_rand($subConcepts)];
            
            // Tekrarları önle
            if (!in_array($nextWord, $sentence) && $this->isValidWord($nextWord)) {
                $sentence[] = $nextWord;
            }
            
            // Kullanılan alt kavramı kaldır
            $subConcepts = array_diff($subConcepts, [$nextWord]);
            
            if (empty($subConcepts)) {
                break;
            }
        }
        
        // Fiil ekle
        if (count($sentence) < $targetLength) {
            $verb = $verbs[array_rand($verbs)];
            $sentence[] = $verb;
        }
        
        // Tanım varsa ve cümle hala kısa ise, tanımdan birkaç kelime ekle
        if (count($sentence) < $minLength && !empty($definition)) {
            $defWords = explode(' ', $definition);
            $selected = array_slice($defWords, 0, $minLength - count($sentence));
            
            foreach ($selected as $word) {
                if (!in_array($word, $sentence) && strlen($word) > 2) {
                    $sentence[] = $word;
                }
            }
        }
        
        // Cümleyi düzgün bir şekilde birleştir
        $result = implode(' ', $sentence);
        
        // Cümlenin ilk harfini büyüt
        $result = ucfirst($result);
        
        // Cümle sonuna nokta ekle
        if (substr($result, -1) != '.') {
            $result .= '.';
        }
        
        // Gereksiz boşlukları temizle
        $result = preg_replace('/\s+/', ' ', $result);
        
        // Noktalama işaretlerinden önceki boşlukları temizle
        $result = preg_replace('/\s+\./', '.', $result);
        
        return $result;
    }
    
    /**
     * Kelimelerin ilişkilerini kullanarak bir cümle üret
     */
    public function generateSentenceWithRelations($startWord, $minLength = 3, $maxLength = 12)
    {
        if (empty($startWord) || !$this->isValidWord($startWord)) {
            return '';
        }
        
        $sentence = [$startWord];
        $usedWords = [$startWord];
        $targetLength = mt_rand($minLength, $maxLength);
        
        // Türkçe cümle yapısı için bağlaçlar ve fiiller
        $connectors = ['ve', 'ile', 'için', 'gibi', 'olarak', 'sayesinde'];
        $verbs = ['vardır', 'oluşur', 'sağlar', 'içerir', 'bulunur', 'görülür'];
        
        // Cümleyi ilişkili kelimelerle oluştur
        while (count($sentence) < $targetLength) {
            $lastWord = $sentence[count($sentence) - 1];
            
            // Eğer son kelime bir bağlaç veya fiil ise, herhangi bir kelime ekleyebiliriz
            $isConnector = in_array($lastWord, $connectors);
            $isVerb = in_array($lastWord, $verbs);
            
            // İlişkili kelimeleri bul
            $nextWordCandidates = [];
            
            // Son kelimeyle ilişkili kelimeleri ekle
            if (!$isConnector && !$isVerb) {
                // Önce eş anlamlılar
                $synonyms = $this->getSynonyms($lastWord);
                foreach ($synonyms as $word => $strength) {
                    if (!in_array($word, $usedWords)) {
                        $nextWordCandidates[$word] = $strength;
                    }
                }
                
                // Sonra ilişkili kelimeler
                $related = $this->getRelatedWords($lastWord);
                foreach ($related as $word => $info) {
                    if (!in_array($word, $usedWords)) {
                        $nextWordCandidates[$word] = $info['strength'];
                    }
                }
                
                // Veritabanından ekstra ilişkiler çek
                try {
                    $dbRelations = WordRelation::where('word', $lastWord)
                        ->where(function($query) {
                            $query->where('relation_type', 'association')
                                  ->orWhere('relation_type', 'synonym');
                        })
                        ->where('strength', '>', 0.2)
                        ->limit(5)
                        ->get();
                        
                    foreach ($dbRelations as $relation) {
                        if (!in_array($relation->related_word, $usedWords)) {
                            $nextWordCandidates[$relation->related_word] = $relation->strength;
                        }
                    }
                } catch (\Exception $e) {
                    // Veritabanı hatası - görmezden gel
                }
            }
            
            // Cümle yapısını iyileştirmek için bağlaç veya fiil ekleme
            if (count($sentence) >= 2 && count($sentence) < $targetLength - 1 && mt_rand(0, 2) == 0) {
                // Cümleye bir bağlaç ekle (33% olasılık)
                $connector = $connectors[array_rand($connectors)];
                if (!in_array($connector, $usedWords)) {
                    $sentence[] = $connector;
                    $usedWords[] = $connector;
                    continue;
                }
            }
            
            // Cümle sonuna fiil ekle
            if (count($sentence) >= $minLength && count($sentence) >= $targetLength - 1) {
                $verb = $verbs[array_rand($verbs)];
                if (!in_array($verb, $usedWords)) {
                    $sentence[] = $verb;
                    $usedWords[] = $verb;
                    break; // Cümleyi sonlandır
                }
            }
            
            // İlişkili kelime bulunduysa, içinden en yüksek skora sahip olanı seç
            if (!empty($nextWordCandidates)) {
                arsort($nextWordCandidates);
                $nextWord = array_key_first($nextWordCandidates);
                $sentence[] = $nextWord;
                $usedWords[] = $nextWord;
            } else {
                // İlişkili kelime bulunamadıysa rastgele bir kelime çek
                try {
                    $randomWord = WordRelation::inRandomOrder()
                        ->whereNotIn('word', $usedWords)
                        ->where('language', $this->language)
                        ->first();
                        
                    if ($randomWord) {
                        $sentence[] = $randomWord->word;
                        $usedWords[] = $randomWord->word;
                    } else {
                        // Random kelime de bulunamadıysa, sabit bir kelime kullan
                        $fallbackWords = ['ve', 'önemli', 'gerekli', 'vardır'];
                        foreach ($fallbackWords as $word) {
                            if (!in_array($word, $usedWords)) {
                                $sentence[] = $word;
                                $usedWords[] = $word;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Veritabanı hatası - cümleyi mevcut haliyle tamamla
                    break;
                }
            }
        }
        
        // Cümleyi oluştur
        $result = implode(' ', $sentence);
        
        // İlk harfi büyüt ve nokta ekle
        $result = ucfirst($result);
        if (substr($result, -1) != '.') {
            $result .= '.';
        }
        
        return $result;
    }
    
    /**
     * İstatistikleri getir
     */
    public function getStats()
    {
        try {
            $stats = [
                'synonym_pairs' => WordRelation::where('relation_type', 'synonym')->where('language', $this->language)->count(),
                'antonym_pairs' => WordRelation::where('relation_type', 'antonym')->where('language', $this->language)->count(),
                'association_pairs' => WordRelation::where('relation_type', 'association')->where('language', $this->language)->count(),
                'definitions' => WordDefinition::where('language', $this->language)->count()
            ];
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('İstatistik alma hatası: ' . $e->getMessage());
            return [
                'synonym_pairs' => 0,
                'antonym_pairs' => 0,
                'association_pairs' => 0,
                'definitions' => 0
            ];
        }
    }
    
    /**
     * Kelime geçerli mi kontrol et
     */
    public function isValidWord($word)
    {
        if (empty($word) || strlen($word) < $this->minWordLength) {
            return false;
        }
        
        // Özel karakterleri filtrele
        $word = preg_replace('/[^a-zA-ZğüşıöçĞÜŞİÖÇ0-9\s]/', '', $word);
        
        return !empty($word);
    }
    
    /**
     * Belirli bir kelimenin tanımlarını getir
     *
     * @param string $word Kelime
     * @return array Tanımlar listesi
     */
    public function getDefinitions($word)
    {
        if (!$this->isValidWord($word)) {
            return [];
        }
        
        try {
            // Veritabanından tanımları getir
            $definitions = WordDefinition::where('word', $word)
                ->orderBy('verified', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
                
            $result = [];
            foreach ($definitions as $definition) {
                $result[] = [
                    'definition' => $definition->definition,
                    'verified' => $definition->verified,
                    'created_at' => $definition->created_at->format('Y-m-d H:i:s')
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Tanımları getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Belirli bir kelimenin örnek cümlelerini getir
     *
     * @param string $word Kelime
     * @return array Örnek cümleler listesi
     */
    public function getExamples($word)
    {
        if (!$this->isValidWord($word)) {
            return [];
        }
        
        try {
            // Öncelikle tanımlarla ilişkilendirilmiş örnekleri getir
            $definitions = WordDefinition::where('word', $word)
                ->whereNotNull('examples')
                ->orderBy('verified', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
                
            $examples = [];
            foreach ($definitions as $definition) {
                $exampleData = json_decode($definition->examples, true);
                if (is_array($exampleData)) {
                    foreach ($exampleData as $example) {
                        $examples[] = [
                            'text' => $example,
                            'source' => 'definition',
                            'verified' => $definition->verified
                        ];
                    }
                }
            }
            
            // Kullanım sıklığına göre sırala
            usort($examples, function($a, $b) {
                // Önce doğrulanmış olanları göster
                if ($a['verified'] != $b['verified']) {
                    return $b['verified'] <=> $a['verified'];
                }
                
                // Sonra metne göre rastgele sırala
                return strlen($b['text']) <=> strlen($a['text']);
            });
            
            return $examples;
        } catch (\Exception $e) {
            Log::error('Örnekleri getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Üretilen cümlenin doğruluk payını hesapla
     * 
     * @param string $sentence Cümle
     * @param string $mainWord Ana kelime
     * @return float Doğruluk puanı (0-1 arası)
     */
    public function calculateSentenceAccuracy($sentence, $mainWord)
    {
        try {
            $accuracy = 0.5; // Başlangıç puanı
            
            // Cümle boş mu kontrol et
            if (empty($sentence) || strlen($sentence) < 5) {
                return 0.0;
            }
            
            // Ana kelime cümlede geçiyor mu?
            if (stripos($sentence, $mainWord) !== false) {
                $accuracy += 0.2; // Ana kelime cümlede geçiyorsa bonus
            }
            
            // Cümle içindeki diğer kelimeleri kontrol et
            $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $sentence));
            $mainWordData = null;
            $validRelations = 0;
            $totalRelations = 0;
            
            // Ana kelime verilerini al
            $mainWordData = \App\Models\AIData::where('word', $mainWord)->first();
            
            if ($mainWordData) {
                // Ana kelimenin sıklık ve güven puanlarını kullan
                $accuracy += min(0.1, ($mainWordData->frequency / 100)); // Sıklığı yüksekse bonus
                $accuracy += min(0.1, $mainWordData->confidence); // Güven puanı yüksekse bonus
                
                // İlişkili kelimeleri kontrol et
                $relatedWords = json_decode($mainWordData->related_words, true) ?: [];
                
                foreach ($words as $word) {
                    if (strlen($word) >= 3 && $word != $mainWord) {
                        $totalRelations++;
                        
                        // İlişkili kelimeler listesinde mi?
                        if (is_array($relatedWords)) {
                            foreach ($relatedWords as $relWord) {
                                $relWordText = is_array($relWord) ? ($relWord['word'] ?? '') : $relWord;
                                if (strtolower($relWordText) == strtolower($word)) {
                                    $validRelations++;
                                    break;
                                }
                            }
                        }
                        
                        // Eş anlamlılar listesinde mi?
                        $synonyms = $this->getSynonyms($mainWord);
                        if (array_key_exists(strtolower($word), $synonyms)) {
                            $validRelations++;
                        }
                        
                        // Zıt anlamlılar listesinde mi?
                        $antonyms = $this->getAntonyms($mainWord);
                        if (array_key_exists(strtolower($word), $antonyms)) {
                            $validRelations++;
                        }
                    }
                }
            }
            
            // Geçerli ilişki oranını hesapla
            if ($totalRelations > 0) {
                $relationRatio = $validRelations / $totalRelations;
                $accuracy += $relationRatio * 0.2; // İlişki oranına göre bonus (max 0.2)
            }
            
            // Cümle uzunluğuna göre bonus/ceza
            $wordCount = count($words);
            if ($wordCount < 3) {
                $accuracy -= 0.1; // Çok kısa cümleler için ceza
            } else if ($wordCount > 15) {
                $accuracy -= 0.1; // Çok uzun cümleler için ceza
            }
            
            // Cümle yapısal kontroller
            if (substr($sentence, -1) == '.' || substr($sentence, -1) == '?' || substr($sentence, -1) == '!') {
                $accuracy += 0.05; // Noktalama doğruysa bonus
            }
            
            if (mb_strtoupper(mb_substr($sentence, 0, 1)) === mb_substr($sentence, 0, 1)) {
                $accuracy += 0.05; // İlk harf büyükse bonus
            }
            
            return min(1.0, max(0.0, $accuracy)); // 0-1 arasında sınırla
            
        } catch (\Exception $e) {
            \Log::error('Cümle doğruluk hesaplama hatası: ' . $e->getMessage());
            return 0.3; // Hata durumunda düşük bir değer döndür
        }
    }
    
    /**
     * Kelimelerin anlam ilişkilerini kullanarak akıllı cümleler oluştur
     * Sinonim ve antonim kullanımına öncelik verir
     * Oluşturulan cümlelerin doğruluğunu kontrol eder
     *
     * @param string $mainWord Ana kelime
     * @param bool $saveToDatabase Veritabanına kaydedilsin mi
     * @param int $count Kaç cümle oluşturulacak
     * @param float $minAccuracy Minimum doğruluk puanı (0-1 arası)
     * @return array Oluşturulan cümleler
     */
    public function generateSmartSentences($mainWord, $saveToDatabase = true, $count = 3, $minAccuracy = 0.6)
    {
        if (!$this->isValidWord($mainWord)) {
            return [];
        }
        
        Log::info("$mainWord kelimesi için akıllı cümleler oluşturuluyor");
        
        // Kelime veritabanında var mı kontrol et
        $wordExists = \App\Models\AIData::where('word', $mainWord)->exists();
        if (!$wordExists) {
            Log::warning("$mainWord kelimesi veritabanında bulunamadı");
            // Fallback olarak basit bir cümle oluştur
            return [$mainWord . " kelimesi hakkında bilgi toplanıyor."];
        }
        
        $sentences = [];
        $attempts = 0;
        $maxAttempts = $count * 5; // Her başarılı cümle için en fazla 5 deneme
        
        // Mutlaka bir şekilde cümle oluşturmaya çalış
        if (empty($sentences) && $attempts < $maxAttempts) {
            // Eş anlamlı ile bir cümle dene
            $sentence = $this->createSentenceWithSynonyms($mainWord);
            if (!empty($sentence) && !in_array($sentence, $sentences)) {
                // Cümle doğruluğunu kontrol et
                $accuracy = $this->calculateSentenceAccuracy($sentence, $mainWord);
                if ($accuracy >= $minAccuracy) {
                    $sentences[] = $sentence;
                    if ($saveToDatabase) {
                        $this->saveSentence($mainWord, $sentence, 'generated', $accuracy);
                    }
                }
            }
        }
        
        if (empty($sentences) && $attempts < $maxAttempts) {
            // Zıt anlamlı ile bir cümle dene
            $sentence = $this->createSentenceWithAntonyms($mainWord);
            if (!empty($sentence) && !in_array($sentence, $sentences)) {
                // Cümle doğruluğunu kontrol et
                $accuracy = $this->calculateSentenceAccuracy($sentence, $mainWord);
                if ($accuracy >= $minAccuracy) {
                    $sentences[] = $sentence;
                    if ($saveToDatabase) {
                        $this->saveSentence($mainWord, $sentence, 'generated', $accuracy);
                    }
                }
            }
        }
        
        while (count($sentences) < $count && $attempts < $maxAttempts) {
            $attempts++;
            
            // Rastgele bir cümle stratejisi seç
            $strategy = mt_rand(1, 5);
            $sentence = '';
            
            switch ($strategy) {
                case 1:
                case 2:
                    // Eş anlamlı kullanarak cümle oluştur (daha sık olsun)
                    $sentence = $this->createSentenceWithSynonyms($mainWord);
                    break;
                case 3:
                    // Zıt anlamlı kullanarak cümle oluştur
                    $sentence = $this->createSentenceWithAntonyms($mainWord);
                    break;
                case 4:
                case 5:
                    // İlişkili kelimelerle cümle oluştur (daha sık olsun)
                    $sentence = $this->createSentenceWithRelatedWords($mainWord);
                    break;
            }
            
            // Cümle geçerli mi kontrol et
            if (!empty($sentence) && $this->isValidSentence($sentence) && !in_array($sentence, $sentences)) {
                // Cümle doğruluğunu kontrol et
                $accuracy = $this->calculateSentenceAccuracy($sentence, $mainWord);
                
                if ($accuracy >= $minAccuracy) {
                    $sentences[] = $sentence;
                    
                    // Veritabanına kaydet
                    if ($saveToDatabase) {
                        $this->saveSentence($mainWord, $sentence, 'generated', $accuracy);
                    }
                } else {
                    Log::info("$mainWord için oluşturulan cümle doğruluk eşiğini geçemedi: $sentence (Doğruluk: $accuracy)");
                }
            }
        }
        
        // Hala cümle oluşturulamamışsa fallback cümleler kullan
        if (empty($sentences)) {
            $fallbackSentences = [
                "$mainWord çok önemli bir kelimedir.",
                "$mainWord hakkında bilgi toplamaya devam ediyorum.",
                "$mainWord kelimesi Türkçe'de yaygın olarak kullanılır."
            ];
            $sentences[] = $fallbackSentences[array_rand($fallbackSentences)];
            
            if ($saveToDatabase) {
                $this->saveSentence($mainWord, $sentences[0], 'generated_fallback', 0.4);
            }
        }
        
        Log::info("$mainWord kelimesi için " . count($sentences) . " akıllı cümle oluşturuldu");
        return $sentences;
    }
    
    /**
     * Eş anlamlıları kullanarak cümle oluştur
     * 
     * @param string $mainWord Ana kelime
     * @return string Oluşturulan cümle
     */
    private function createSentenceWithSynonyms($mainWord)
    {
        // Eş anlamlıları al
        $synonyms = $this->getSynonyms($mainWord);
        
        if (empty($synonyms)) {
            return '';
        }
        
        // Rastgele bir eş anlamlı seç
        $synonym = array_rand($synonyms);
        
        // Cümle şablonları
        $templates = [
            "$mainWord kelimesi $synonym ile aynı anlama gelir.",
            "$mainWord ve $synonym kelimelerinin anlamları benzerdir.",
            "$mainWord yerine $synonym kelimesi de kullanılabilir.",
            "$mainWord, $synonym anlamını taşır.",
            "$mainWord kelimesinin eş anlamlısı $synonym kelimesidir.",
            "Türkçe'de $mainWord kelimesi yerine $synonym da kullanılır.",
            "$mainWord ile $synonym aynı anlama gelen iki kelimedir."
        ];
        
        return $templates[array_rand($templates)];
    }
    
    /**
     * Zıt anlamlıları kullanarak cümle oluştur
     * 
     * @param string $mainWord Ana kelime
     * @return string Oluşturulan cümle
     */
    private function createSentenceWithAntonyms($mainWord)
    {
        // Zıt anlamlıları al
        $antonyms = $this->getAntonyms($mainWord);
        
        if (empty($antonyms)) {
            return '';
        }
        
        // Rastgele bir zıt anlamlı seç
        $antonym = array_rand($antonyms);
        
        // Cümle şablonları
        $templates = [
            "$mainWord kelimesinin zıttı $antonym'dir.",
            "$mainWord ve $antonym kelimeleri zıt anlamlara sahiptir.",
            "$mainWord değilse, $antonym olabilir.",
            "Eğer bir şey $mainWord değilse, $antonym olma ihtimali yüksektir.",
            "$mainWord ile $antonym arasında önemli farklar vardır."
        ];
        
        return $templates[array_rand($templates)];
    }
    
    /**
     * İlişkili kelimeleri kullanarak cümle oluştur
     * 
     * @param string $mainWord Ana kelime
     * @return string Oluşturulan cümle
     */
    private function createSentenceWithRelatedWords($mainWord)
    {
        // İlişkili kelimeleri al
        $related = $this->getRelatedWords($mainWord, 0.3);
        
        if (count($related) < 2) {
            return '';
        }
        
        // En az 2 ilişkili kelimeyi seç
        $words = array_keys($related);
        shuffle($words);
        $relatedWord1 = $words[0];
        $relatedWord2 = $words[1];
        
        // Cümle şablonları
        $templates = [
            "$mainWord, $relatedWord1 ve $relatedWord2 ile ilişkilidir.",
            "$mainWord konusunda $relatedWord1 ve $relatedWord2 önemli faktörlerdir.",
            "$mainWord, $relatedWord1 kadar $relatedWord2 ile de bağlantılıdır.",
            "$mainWord hakkında konuşurken $relatedWord1 ve $relatedWord2 'den bahsetmek gerekir."
        ];
        
        return $templates[array_rand($templates)];
    }
    
    /**
     * Cümlenin geçerli olup olmadığını kontrol et
     * 
     * @param string $sentence Kontrol edilecek cümle
     * @return bool Geçerli mi
     */
    private function isValidSentence($sentence)
    {
        // Minimum uzunluk kontrolü
        if (strlen($sentence) < 10) {
            return false;
        }
        
        // Noktalama ve sözdizimi kontrolü
        if (!preg_match('/^[A-ZĞÜŞİÖÇ].*[.!?]$/', $sentence)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Cümleyi veritabanına kaydet
     * 
     * @param string $word Kelime
     * @param string $sentence Cümle
     * @param string $source Kaynak
     * @param float $accuracy Doğruluk puanı
     * @return bool Başarı durumu
     */
    public function saveSentence($word, $sentence, $source = 'generated', $accuracy = 0.5)
    {
        try {
            // Kelime verilerini al veya oluştur
            $aiData = \App\Models\AIData::firstOrNew(['word' => $word]);
            
            // Örnekler listesini al
            $examples = json_decode($aiData->usage_examples ?? '[]', true) ?: [];
            
            // Cümlenin zaten kayıtlı olup olmadığını kontrol et
            foreach ($examples as $example) {
                if (is_array($example) && isset($example['text']) && $example['text'] === $sentence) {
                    // Zaten kayıtlı, güncelle
                    $example['accuracy'] = $accuracy;
                    $example['updated_at'] = now()->toDateTimeString();
                    return true;
                } else if (is_string($example) && $example === $sentence) {
                    // Eski formatta kayıtlı, yeni formata dönüştür
                    $examples = array_filter($examples, function($e) use ($sentence) {
                        return $e !== $sentence;
                    });
                    break;
                }
            }
            
            // Yeni formatta ekle
            $examples[] = [
                'text' => $sentence,
                'source' => $source,
                'accuracy' => $accuracy,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString()
            ];
            
            // Örnekleri güncelle
            $aiData->usage_examples = json_encode($examples);
            
            // Eğer yeni oluşturuluyorsa diğer alanları da doldur
            if (!$aiData->exists) {
                $aiData->sentence = $sentence;
                $aiData->category = 'genel';
                $aiData->context = 'Otomatik oluşturuldu';
                $aiData->language = 'tr';
                $aiData->frequency = 1;
                $aiData->confidence = $accuracy;
            }
            
            // Kaydet
            $aiData->save();
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Cümle kaydetme hatası: ' . $e->getMessage());
            return false;
        }
    }
}
