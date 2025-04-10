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
     * Verilen kavramla ilgili anlamlı bir cümle oluştur
     */
    public function generateConceptualSentence($concept, $minLength = 3, $maxLength = 12)
    {
        if (!$this->isValidWord($concept)) {
            return '';
        }
        
        try {
            // Kavramın tanımını al
            $definition = $this->getDefinition($concept);
            
            // Cümle kalıpları
            $sentenceTemplates = [
                "%concept%, %definition%",
                "%concept% aslında %definition%",
                "%concept% kavramı %definition%",
                "Bildiğim kadarıyla %concept%, %definition%",
                "%concept% demek, %definition% demektir"
            ];
            
            // Eğer tanım varsa ve uzunsa, farklı bir cümle olarak kullan
            if (!empty($definition) && strlen($definition) > 10) {
                // Tanımın ilk harfini küçült
                $definition = lcfirst($definition);
                
                // Rastgele bir kalıp seç ve cümle oluştur
                $template = $sentenceTemplates[array_rand($sentenceTemplates)];
                $sentence = str_replace(["%concept%", "%definition%"], [$concept, $definition], $template);
                
                // İlk harfi büyük yap
                return ucfirst($sentence);
            }
            
            // İlişkili kelimeleri al (ağırlığı 0.2'den büyük olanlar)
            $relatedWords = $this->getRelatedWords($concept, 0.2);
            
            // Yeterli ilişkili kelime yoksa minimum gereksinimi azalt veya boş dön
            if (count($relatedWords) < 2) {
                $relatedWords = $this->getRelatedWords($concept, 0.0); // Tüm ilişkili kelimeleri al
                
                if (count($relatedWords) < 1) {
                    return '';
                }
            }
            
            // İlişki tiplerine göre kelimeleri ayır
            $synonyms = [];
            $antonyms = [];
            $associations = [];
            
            foreach ($relatedWords as $word => $info) {
                if ($info['type'] === 'synonym') {
                    $synonyms[$word] = $info['strength'];
                } else if ($info['type'] === 'antonym') {
                    $antonyms[$word] = $info['strength'];
                } else {
                    $associations[$word] = $info['strength'];
                }
            }
            
            // Cümle oluşturma stratejileri
            
            // 1. Strateji: Eş anlamlılar varsa
            if (!empty($synonyms)) {
                $synonymList = array_keys($synonyms);
                shuffle($synonymList);
                $selectedSynonyms = array_slice($synonymList, 0, min(2, count($synonymList)));
                
                $templates = [
                    "%concept% kelimesi %synonyms% anlamına gelir.",
                    "%concept% sözcüğü ile %synonyms% aynı anlama gelir.",
                    "%concept% ve %synonyms% benzer kavramlardır.",
                    "%concept% dendiğinde %synonyms% da anlaşılabilir."
                ];
                
                $template = $templates[array_rand($templates)];
                $synText = implode(' ve ', $selectedSynonyms);
                $sentence = str_replace(["%concept%", "%synonyms%"], [$concept, $synText], $template);
                
                return ucfirst($sentence);
            }
            
            // 2. Strateji: Zıt anlamlılar varsa
            if (!empty($antonyms)) {
                $antonymList = array_keys($antonyms);
                shuffle($antonymList);
                $selectedAntonyms = array_slice($antonymList, 0, min(2, count($antonymList)));
                
                $templates = [
                    "%concept% kelimesinin zıt anlamı %antonyms% olabilir.",
                    "%concept% ve %antonyms% birbirine zıt kavramlardır.",
                    "%concept% kelimesinin karşıtı %antonyms% olarak düşünülebilir.",
                    "%antonyms% kavramı, %concept% kavramının tersidir."
                ];
                
                $template = $templates[array_rand($templates)];
                $antText = implode(' ve ', $selectedAntonyms);
                $sentence = str_replace(["%concept%", "%antonyms%"], [$concept, $antText], $template);
                
                return ucfirst($sentence);
            }
            
            // 3. Strateji: İlişkili kelimeler varsa
            if (!empty($associations)) {
                $assocList = array_keys($associations);
                shuffle($assocList);
                $selectedAssocs = array_slice($assocList, 0, min(3, count($assocList)));
                
                $templates = [
                    "%concept% denince akla %associations% gelir.",
                    "%concept% kavramı %associations% ile ilişkilidir.",
                    "%concept% ile %associations% arasında bağlantı vardır.",
                    "%concept% konuşulduğunda genellikle %associations% da konuşulur.",
                    "Genellikle %concept% ve %associations% bir arada düşünülür."
                ];
                
                $template = $templates[array_rand($templates)];
                
                // Bağlaçlarla daha doğal bir metin oluştur
                if (count($selectedAssocs) == 1) {
                    $assocText = $selectedAssocs[0];
                } else if (count($selectedAssocs) == 2) {
                    $assocText = $selectedAssocs[0] . ' ve ' . $selectedAssocs[1];
                } else {
                    $lastIndex = count($selectedAssocs) - 1;
                    $assocText = implode(', ', array_slice($selectedAssocs, 0, $lastIndex));
                    $assocText .= ' ve ' . $selectedAssocs[$lastIndex];
                }
                
                $sentence = str_replace(["%concept%", "%associations%"], [$concept, $assocText], $template);
                
                return ucfirst($sentence);
            }
            
            // 4. Strateji: Rastgele bir cümle oluştur (yeterli kelime varsa)
            // Cümle için kullanılacak kelimeleri seç
            $allRelatedWords = array_keys($relatedWords);
            if (count($allRelatedWords) >= 2) {
                shuffle($allRelatedWords);
                $selectedWords = array_slice($allRelatedWords, 0, min(3, count($allRelatedWords)));
                
                $templates = [
                    "%concept% bağlamında %words% önemli kavramlardır.",
                    "%concept% dünyasında %words% sık rastlanan terimlerdir.",
                    "%concept% hakkında konuşurken %words% kavramları da akılda tutulmalıdır.",
                    "%concept% ile %words% birbirleriyle ilişkilidir."
                ];
                
                $template = $templates[array_rand($templates)];
                
                // Kelimeleri bağlaçlarla birleştir
                if (count($selectedWords) == 1) {
                    $wordsText = $selectedWords[0];
                } else if (count($selectedWords) == 2) {
                    $wordsText = $selectedWords[0] . ' ve ' . $selectedWords[1];
                } else {
                    $lastIndex = count($selectedWords) - 1;
                    $wordsText = implode(', ', array_slice($selectedWords, 0, $lastIndex));
                    $wordsText .= ' ve ' . $selectedWords[$lastIndex];
                }
                
                $sentence = str_replace(["%concept%", "%words%"], [$concept, $wordsText], $template);
                
                return ucfirst($sentence);
            }
            
            // Hiçbir strateji çalışmazsa boş dön
            return '';
            
        } catch (\Exception $e) {
            Log::error('Cümle oluşturma hatası: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * İlişkiler kullanarak cümle oluştur
     */
    public function generateSentenceWithRelations($startWord, $minLength = 3, $maxLength = 12)
    {
        if (!$this->isValidWord($startWord)) {
            return '';
        }
        
        try {
            // Cümleyi başlat
            $sentence = [$startWord];
            $usedWords = [$startWord];
            
            // Hedef uzunluğa ulaşana kadar kelime ekle
            while (count($sentence) < $maxLength) {
                // Son kelimeyi al
                $lastWord = $sentence[count($sentence) - 1];
                
                // Son kelimeyle ilişkili kelimeleri al
                $relatedWords = $this->getRelatedWords($lastWord, 0.3);
                
                // İlişkili kelime yoksa döngüyü bitir
                if (empty($relatedWords)) {
                    break;
                }
                
                // İlişkili kelimeleri ağırlıklarına göre sırala
                $weightedWords = [];
                foreach ($relatedWords as $word => $info) {
                    // Daha önce kullanılmamış kelimeleri tercih et
                    if (!in_array($word, $usedWords)) {
                        $weight = $info['strength'];
                        if ($info['type'] === 'synonym') $weight *= 1.5;
                        if ($info['type'] === 'antonym') $weight *= 0.8;
                        $weightedWords[$word] = $weight;
                    }
                }
                
                // İlişkili kelime kalmadıysa döngüyü bitir
                if (empty($weightedWords)) {
                    break;
                }
                
                // En yüksek ağırlıklı kelimeyi seç
                arsort($weightedWords);
                $nextWord = key($weightedWords);
                
                // Kelimeyi cümleye ekle
                $sentence[] = $nextWord;
                $usedWords[] = $nextWord;
            }
            
            // Minimum uzunluğa ulaşmadıysa boş dön
            if (count($sentence) < $minLength) {
                return '';
            }
            
            // Cümleyi birleştir
            $result = implode(' ', $sentence);
            $result = ucfirst($result) . '.';
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('İlişkisel cümle oluşturma hatası: ' . $e->getMessage());
            return '';
        }
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
