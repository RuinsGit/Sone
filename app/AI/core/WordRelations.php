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
}
