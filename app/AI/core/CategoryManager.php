<?php

namespace App\AI\Core;

use App\Models\WordCategory;
use App\Models\WordCategoryItem;
use App\Models\WordRelation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CategoryManager
{
    private $categories = [];
    private $wordCategories = [];
    private $language = 'tr';
    private $minConfidence = 0.3;
    private $cacheTime = 720; // Dakika cinsinden (12 saat)
    
    public function __construct()
    {
        $this->loadCategories();
    }
    
    /**
     * Kategorileri yükle
     */
    private function loadCategories()
    {
        // Önce önbellekten kontrol et
        $cachedCategories = Cache::get('ai_categories', null);
        $cachedWordCategories = Cache::get('ai_word_categories', null);
        
        if ($cachedCategories && $cachedWordCategories) {
            $this->categories = $cachedCategories;
            $this->wordCategories = $cachedWordCategories;
            return;
        }
        
        // Kategorileri yükle
        try {
            $categories = WordCategory::with('children')
                ->whereNull('parent_id')
                ->get();
                
            foreach ($categories as $category) {
                $this->categories[$category->id] = [
                    'name' => $category->name,
                    'description' => $category->description,
                    'parent_id' => $category->parent_id,
                    'level' => $category->level,
                    'children' => $this->getChildrenIds($category)
                ];
            }
            
            // Kelimelerin kategorilerini yükle
            $wordCategories = WordCategoryItem::select('word', 'category_id', 'strength')
                ->where('language', $this->language)
                ->where('strength', '>=', $this->minConfidence)
                ->get();
                
            foreach ($wordCategories as $item) {
                if (!isset($this->wordCategories[$item->word])) {
                    $this->wordCategories[$item->word] = [];
                }
                
                $this->wordCategories[$item->word][$item->category_id] = $item->strength;
            }
            
            // Önbelleğe al
            Cache::put('ai_categories', $this->categories, now()->addMinutes($this->cacheTime));
            Cache::put('ai_word_categories', $this->wordCategories, now()->addMinutes($this->cacheTime));
            
        } catch (\Exception $e) {
            Log::error('Kategori yükleme hatası: ' . $e->getMessage());
        }
    }
    
    /**
     * Alt kategori ID'lerini döndür
     */
    private function getChildrenIds($category)
    {
        $childrenIds = [];
        
        if ($category->children->isEmpty()) {
            return $childrenIds;
        }
        
        foreach ($category->children as $child) {
            $childrenIds[] = $child->id;
            $childrenIds = array_merge($childrenIds, $this->getChildrenIds($child));
        }
        
        return $childrenIds;
    }
    
    /**
     * Yeni kategori oluştur
     */
    public function createCategory($name, $description = '', $parentId = null)
    {
        try {
            // Kategori var mı kontrol et
            $existingCategory = WordCategory::where('name', $name)->first();
            
            if ($existingCategory) {
                return $existingCategory->id;
            }
            
            // Ebeveyn seviyesini belirle
            $level = 0;
            if ($parentId) {
                $parent = WordCategory::find($parentId);
                if ($parent) {
                    $level = $parent->level + 1;
                }
            }
            
            // Yeni kategori oluştur
            $category = WordCategory::create([
                'name' => $name,
                'description' => $description,
                'parent_id' => $parentId,
                'level' => $level
            ]);
            
            // Kategorileri güncelle
            $this->categories[$category->id] = [
                'name' => $name,
                'description' => $description,
                'parent_id' => $parentId,
                'level' => $level,
                'children' => []
            ];
            
            // Önbelleği güncelle
            Cache::put('ai_categories', $this->categories, now()->addMinutes($this->cacheTime));
            
            return $category->id;
        } catch (\Exception $e) {
            Log::error('Kategori oluşturma hatası: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Kelimeyi kategoriye ekle
     */
    public function addWordToCategory($word, $categoryId, $strength = 1.0, $context = null, $nov = null)
    {
        try {
            // Kelime ve kategori kontrolü
            if (empty($word) || !$categoryId) {
                return false;
            }
            
            // Kategori var mı kontrol et
            if (!isset($this->categories[$categoryId]) && !WordCategory::find($categoryId)) {
                return false;
            }
            
            // Kelime zaten bu kategoride ve bu türde var mı kontrol et
            $existingItem = WordCategoryItem::where('word', $word)
                ->where('category_id', $categoryId)
                ->where('nov', $nov)
                ->where('language', $this->language)
                ->first();
                
            if ($existingItem) {
                // Mevcut kaydı güncelle (güç değerini en yüksek olanla güncelle)
                if ($strength > $existingItem->strength) {
                    $existingItem->strength = $strength;
                }
                $existingItem->usage_count = $existingItem->usage_count + 1;
                $existingItem->save();
            } else {
                // Yeni kayıt oluştur
                WordCategoryItem::create([
                    'word' => $word,
                    'nov' => $nov,
                    'category_id' => $categoryId,
                    'language' => $this->language,
                    'strength' => $strength,
                    'context' => $context,
                    'usage_count' => 1
                ]);
            }
            
            // Kategorinin kullanım sayısını artır
            WordCategory::where('id', $categoryId)->increment('usage_count');
            
            // Kelime kategorilerini güncelle
            if (!isset($this->wordCategories[$word])) {
                $this->wordCategories[$word] = [];
            }
            
            $novKey = $nov ? $categoryId . ':' . $nov : $categoryId;
            $this->wordCategories[$word][$novKey] = $strength;
            
            // Önbelleği güncelle
            Cache::put('ai_word_categories', $this->wordCategories, now()->addMinutes($this->cacheTime));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Kelimeyi kategoriye ekleme hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kelimenin kategorilerini getir
     */
    public function getWordCategories($word)
    {
        if (!$word) {
            return [];
        }
        
        // Önbellekte var mı kontrol et
        if (isset($this->wordCategories[$word])) {
            $categoryIds = array_keys($this->wordCategories[$word]);
            
            $result = [];
            foreach ($categoryIds as $categoryId) {
                if (isset($this->categories[$categoryId])) {
                    $result[$categoryId] = [
                        'name' => $this->categories[$categoryId]['name'],
                        'strength' => $this->wordCategories[$word][$categoryId],
                        'level' => $this->categories[$categoryId]['level']
                    ];
                }
            }
            
            return $result;
        }
        
        // Veritabanından getir
        try {
            $items = WordCategoryItem::where('word', $word)
                ->where('language', $this->language)
                ->where('strength', '>=', $this->minConfidence)
                ->with('category')
                ->get();
                
            $result = [];
            foreach ($items as $item) {
                if ($item->category) {
                    $result[$item->category_id] = [
                        'name' => $item->category->name,
                        'strength' => $item->strength,
                        'level' => $item->category->level
                    ];
                    
                    // Önbelleğe ekle
                    if (!isset($this->wordCategories[$word])) {
                        $this->wordCategories[$word] = [];
                    }
                    
                    $this->wordCategories[$word][$item->category_id] = $item->strength;
                }
            }
            
            // Önbelleği güncelle
            Cache::put('ai_word_categories', $this->wordCategories, now()->addMinutes($this->cacheTime));
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Kelime kategorileri getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Kategoriye göre kelime getir
     */
    public function getWordsByCategory($categoryId, $limit = 20, $minStrength = 0.5)
    {
        if (!$categoryId) {
            return [];
        }
        
        try {
            // Ana kategori ve alt kategorileri dahil et
            $categoryIds = [$categoryId];
            
            if (isset($this->categories[$categoryId]['children'])) {
                $categoryIds = array_merge($categoryIds, $this->categories[$categoryId]['children']);
            }
            
            $items = WordCategoryItem::whereIn('category_id', $categoryIds)
                ->where('language', $this->language)
                ->where('strength', '>=', $minStrength)
                ->orderBy('strength', 'desc')
                ->orderBy('usage_count', 'desc')
                ->limit($limit)
                ->get();
                
            $words = [];
            foreach ($items as $item) {
                $words[] = [
                    'word' => $item->word,
                    'strength' => $item->strength,
                    'usage_count' => $item->usage_count,
                    'category_id' => $item->category_id
                ];
            }
            
            return $words;
        } catch (\Exception $e) {
            Log::error('Kategoriye göre kelime getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Metni analiz edip kelimelere kategoriler ata
     */
    public function analyzeText($text)
    {
        if (empty($text)) {
            return [];
        }
        
        // Metni hazırla
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
        
        // Kelimeleri ayır
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, function($word) {
            return mb_strlen($word) >= 2;
        });
        
        $wordCounts = array_count_values($words);
        $categoriesFound = [];
        
        // En sık kullanılan kelimeleri incele (en fazla 10)
        $frequentWords = array_slice($wordCounts, 0, 10, true);
        
        foreach ($frequentWords as $word => $count) {
            // Kelimenin kategorilerini al
            $wordCategories = $this->getWordCategories($word);
            
            foreach ($wordCategories as $categoryId => $info) {
                $categoryName = $info['name'];
                $strength = $info['strength'] * ($count / max(array_values($wordCounts)));
                
                if (!isset($categoriesFound[$categoryId])) {
                    $categoriesFound[$categoryId] = [
                        'name' => $categoryName,
                        'score' => 0,
                        'words' => []
                    ];
                }
                
                $categoriesFound[$categoryId]['score'] += $strength;
                $categoriesFound[$categoryId]['words'][$word] = $count;
            }
        }
        
        // Kategorileri skora göre sırala
        uasort($categoriesFound, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $categoriesFound;
    }
    
    /**
     * Kategoriye göre cümle üret
     */
    public function generateSentenceByCategory($categoryId, $minWords = 4, $maxWords = 12)
    {
        try {
            // Kategoriden kelimeler al
            $words = $this->getWordsByCategory($categoryId, 30);
            
            if (empty($words)) {
                return '';
            }
            
            // Cümle yapısı (Türkçe gramer kurallarına uygun)
            $sentenceWords = [];
            
            // Cümle uzunluğunu belirle
            $sentenceLength = rand($minWords, $maxWords);
            
            // Kategori adını al
            $categoryName = '';
            if (isset($this->categories[$categoryId]['name'])) {
                $categoryName = $this->categories[$categoryId]['name'];
            } else {
                $category = WordCategory::find($categoryId);
                if ($category) {
                    $categoryName = $category->name;
                }
            }
            
            // Kelimelerden rastgele seç
            $selectedWords = [];
            $selectedWordCount = min(count($words), $sentenceLength);
            
            for ($i = 0; $i < $selectedWordCount; $i++) {
                $randomIndex = array_rand($words);
                $selectedWords[] = $words[$randomIndex]['word'];
                unset($words[$randomIndex]);
                if (empty($words)) break;
            }
            
            // Cümle kalıpları
            $templates = [
                "$categoryName konusunda %s önemlidir.",
                "$categoryName, %s içerir.",
                "%s, $categoryName ile ilgilidir.",
                "$categoryName için %s gereklidir.",
                "%s sayesinde $categoryName gelişir.",
                "$categoryName, %s ile oluşur.",
                "%s, $categoryName açısından değerlidir."
            ];
            
            // Rastgele bir şablon seç
            $templateIndex = array_rand($templates);
            $template = $templates[$templateIndex];
            
            // Seçilen kelimeleri birleştir
            $wordList = implode(' ve ', $selectedWords);
            
            // Şablona yerleştir ve döndür
            return sprintf($template, $wordList);
        } catch (\Exception $e) {
            Log::error('Kategoriye göre cümle üretme hatası: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Tüm kategorileri getir
     */
    public function getAllCategories()
    {
        return $this->categories;
    }
    
    /**
     * Metinden öğrenip kategorileri güncelle
     */
    public function learnFromText($text, $contextCategory = null)
    {
        if (empty($text)) {
            return 0;
        }
        
        // Metni analiz et
        $analysis = $this->analyzeText($text);
        
        // Metin içindeki kelimeleri ayır
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, function($word) {
            return mb_strlen($word) >= 2;
        });
        
        // Aynı kelimeleri bir kez işlemek için tekrarları kaldır
        $uniqueWords = array_unique($words);
        
        // Kelimeler arasında ilişkiler oluştur
        $this->createRelationsBetweenWords($uniqueWords);
        
        // Eğer kontekst kategori belirtilmişse, o kategoriye ait kelimeleri güncelle
        if ($contextCategory) {
            // Kategori ID'sini bul veya oluştur
            $categoryId = $this->getCategoryIdByName($contextCategory);
            
            // Her kelimeyi kategoriye ekle (bir kez)
            $addedCount = 0;
            foreach ($uniqueWords as $word) {
                if ($this->addWordToCategory($word, $categoryId, 0.8)) {
                    $addedCount++;
                }
            }
            
            return $addedCount;
        }
        
        // Bulunan kategorilere göre kelimeleri güncelle
        $addedCount = 0;
        foreach ($analysis as $categoryId => $info) {
            foreach ($info['words'] as $word => $count) {
                $strength = min(1.0, 0.5 + ($count / 10));
                if ($this->addWordToCategory($word, $categoryId, $strength)) {
                    $addedCount++;
                }
            }
        }
        
        return $addedCount;
    }
    
    /**
     * Kelimeler arasında ilişkiler oluştur
     */
    private function createRelationsBetweenWords($words)
    {
        if (count($words) < 2) {
            return 0;
        }
        
        $wordRelations = new WordRelations();
        $relationsCreated = 0;
        
        // Her kelimenin, kendisinden sonraki kelimelerle ilişkisini kur
        $wordsArray = array_values($words);
        
        // Her kelime çifti için işlem yap (birbirine yakın olanlar daha güçlü ilişki)
        for ($i = 0; $i < count($wordsArray); $i++) {
            $word1 = $wordsArray[$i];
            
            // Kelimenin ana kategorisini bul
            $category1 = $this->getStrongestCategory($word1);
            
            for ($j = $i + 1; $j < min($i + 5, count($wordsArray)); $j++) {
                $word2 = $wordsArray[$j];
                
                // İki kelime aynıysa ilişki kurma
                if ($word1 === $word2) continue;
                
                // Kelimenin ana kategorisini bul
                $category2 = $this->getStrongestCategory($word2);
                
                // İlişki gücünü hesapla (yakınlık ve kategori benzerlikleri)
                $strength = 0.5;
                
                // Yakınlık faktörü: Yakın kelimeler daha güçlü ilişkilendirilir
                $proximityFactor = 1 - (($j - $i) / 5);
                $strength *= $proximityFactor;
                
                // Kategori faktörü: Aynı kategorideki kelimeler daha güçlü ilişkilendirilir
                if ($category1 && $category2 && $category1['id'] === $category2['id']) {
                    $strength += 0.3;
                }
                
                // İlişki kur (association)
                $context = null;
                if ($category1) {
                    $context = $category1['name'];
                } elseif ($category2) {
                    $context = $category2['name'];
                }
                
                if ($wordRelations->learnAssociation($word1, $word2, $context, min(1.0, $strength))) {
                    $relationsCreated++;
                }
            }
        }
        
        return $relationsCreated;
    }
    
    /**
     * Kelime var mı kontrol et
     */
    public function wordExists($word, $categoryId = null, $nov = null)
    {
        if (!$word) {
            return false;
        }
        
        $query = WordCategoryItem::where('word', $word)
            ->where('language', $this->language);
        
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        
        if ($nov) {
            $query->where('nov', $nov);
        }
        
        return $query->exists();
    }
    
    /**
     * Kategorinin adına göre ID'sini getir, yoksa oluştur
     */
    public function getCategoryIdByName($categoryName)
    {
        foreach ($this->categories as $id => $category) {
            if (strtolower($category['name']) === strtolower($categoryName)) {
                return $id;
            }
        }
        
        // Kategori yoksa oluştur
        return $this->createCategory($categoryName);
    }
    
    /**
     * Kelimenin en güçlü kategorisini getir
     */
    public function getStrongestCategory($word)
    {
        $categories = $this->getWordCategories($word);
        
        if (empty($categories)) {
            return null;
        }
        
        $strongestCategoryId = null;
        $maxStrength = 0;
        
        foreach ($categories as $categoryId => $info) {
            if ($info['strength'] > $maxStrength) {
                $maxStrength = $info['strength'];
                $strongestCategoryId = $categoryId;
            }
        }
        
        if ($strongestCategoryId && isset($this->categories[$strongestCategoryId])) {
            return [
                'id' => $strongestCategoryId,
                'name' => $this->categories[$strongestCategoryId]['name'],
                'strength' => $maxStrength
            ];
        }
        
        return null;
    }
    
    /**
     * Bir kelime topluluğundan yeni cümleler üret
     */
    public function generateSentencesFromWords($words, $count = 3)
    {
        if (empty($words) || !is_array($words)) {
            return [];
        }
        
        $sentences = [];
        $wordRelations = new WordRelations();
        
        for ($i = 0; $i < $count; $i++) {
            // Rastgele bir kelime seç
            $randomIndex = array_rand($words);
            $startWord = $words[$randomIndex];
            
            // Kelimenin kategorisini bul
            $category = $this->getStrongestCategory($startWord);
            
            if ($category) {
                // Kategoriye göre cümle üret
                $sentence = $this->generateSentenceByCategory($category['id']);
                if (!empty($sentence) && !in_array($sentence, $sentences)) {
                    $sentences[] = $sentence;
                    continue;
                }
            }
            
            // Alternatif olarak ilişkili kelimelerle cümle üret
            $sentence = $wordRelations->generateConceptualSentence($startWord);
            if (!empty($sentence) && !in_array($sentence, $sentences)) {
                $sentences[] = $sentence;
            }
        }
        
        return array_unique($sentences);
    }
    
    /**
     * Kategori istatistiklerini getir
     */
    public function getStats()
    {
        return [
            'total_categories' => count($this->categories),
            'total_categorized_words' => count($this->wordCategories),
            'top_categories' => $this->getTopCategories(10)
        ];
    }
    
    /**
     * En çok kullanılan kategorileri getir
     */
    private function getTopCategories($limit = 10)
    {
        try {
            $categories = WordCategory::orderBy('usage_count', 'desc')
                ->limit($limit)
                ->get(['id', 'name', 'usage_count']);
                
            $result = [];
            foreach ($categories as $category) {
                $result[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'usage_count' => $category->usage_count
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('En çok kullanılan kategorileri getirme hatası: ' . $e->getMessage());
            return [];
        }
    }
} 