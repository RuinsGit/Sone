<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\AI\Core\WordRelations;
use App\AI\Core\CategoryManager;
use App\Models\WordRelation;
use Illuminate\Support\Facades\Log;

class LearnWordRelations extends Command
{
    protected $signature = 'ai:learn-relations {--limit=100 : İşlenecek maksimum kelime sayısı}';
    protected $description = 'Kelimeler arasındaki ilişkileri öğren ve kategorilere ayır';

    private $wordRelations;
    private $categoryManager;

    public function __construct(WordRelations $wordRelations, CategoryManager $categoryManager)
    {
        parent::__construct();
        $this->wordRelations = $wordRelations;
        $this->categoryManager = $categoryManager;
    }

    public function handle()
    {
        $this->info('Kelime ilişkileri öğrenme işlemi başlatılıyor...');
        $limit = $this->option('limit');
        
        try {
            $result = $this->wordRelations->collectAndLearnRelations();
            
            if ($result['success']) {
                $this->info('İlk ilişki öğrenme işlemi tamamlandı!');
                $this->info('İşlenen kelime sayısı: ' . $result['processed']);
                $this->info('Öğrenilen ilişki sayısı: ' . $result['learned']);
                
                // Kategori tabanlı ilişki öğrenme
                $relationCount = $this->learnRelationsFromCategories($limit);
                $this->info("Kategori tabanlı öğrenilen ilişki sayısı: $relationCount");
                
                // Sık kullanılan kelimeleri kategorilere ekle
                $categorizedCount = $this->categorizeFrequentWords($limit);
                $this->info("Sık kullanılan kategorize edilen kelime sayısı: $categorizedCount");
                
                // İstatistikleri göster
                $stats = $this->wordRelations->getStats();
                $this->info('Toplam eş anlamlı kelime çifti: ' . $stats['synonym_pairs']);
                $this->info('Toplam zıt anlamlı kelime çifti: ' . $stats['antonym_pairs']);
                $this->info('Toplam ilişkili kelime çifti: ' . $stats['association_pairs']);
                $this->info('Toplam kelime tanımı: ' . $stats['definitions']);
                
                // Kategori istatistikleri
                $catStats = $this->categoryManager->getStats();
                $this->info('Toplam kategori sayısı: ' . $catStats['total_categories']);
                $this->info('Toplam kategorize edilmiş kelime sayısı: ' . $catStats['total_categorized_words']);
                
                return 0;
            } else {
                $this->info($result['message'] ?? 'İşlenecek veri bulunamadı.');
                return 0;
            }
        } catch (\Exception $e) {
            $this->error('Kelime ilişkileri öğrenme hatası: ' . $e->getMessage());
            Log::error('Kelime ilişkileri öğrenme hatası: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Kategorilere göre kelime ilişkilerini öğren
     */
    private function learnRelationsFromCategories($limit)
    {
        $this->info('Kategorilere göre kelime ilişkileri öğreniliyor...');
        
        $categories = $this->categoryManager->getAllCategories();
        $totalRelations = 0;
        
        // Her kategori için
        foreach ($categories as $categoryId => $category) {
            // Kategorideki kelimeleri al
            $words = $this->categoryManager->getWordsByCategory($categoryId, min(20, $limit / count($categories)));
            
            if (count($words) < 2) {
                continue;
            }
            
            $this->output->write('.');
            
            // Kategori içindeki kelimeler arasında ilişkiler oluştur
            $relations = 0;
            
            for ($i = 0; $i < count($words); $i++) {
                $word1 = $words[$i]['word'];
                
                for ($j = $i + 1; $j < count($words); $j++) {
                    $word2 = $words[$j]['word'];
                    
                    // Kategori içi ilişki kur (aynı kategorideki kelimeler ilişkilidir)
                    if ($this->wordRelations->learnAssociation($word1, $word2, $category['name'], 0.7)) {
                        $relations++;
                    }
                }
            }
            
            $totalRelations += $relations;
        }
        
        $this->output->writeln('');
        return $totalRelations;
    }
    
    /**
     * Sık kullanılan kelimeleri kategorilere ekle
     */
    private function categorizeFrequentWords($limit)
    {
        $this->info('Sık kullanılan kelimeler kategorilere ekleniyor...');
        
        // Sık kullanılan kelime ilişkilerini al
        $relations = WordRelation::where('relation_type', 'association')
            ->where('strength', '>', 0.5)
            ->orderBy('strength', 'desc')
            ->limit($limit)
            ->get();
            
        $categorized = 0;
        
        foreach ($relations as $relation) {
            $word1 = $relation->word;
            $word2 = $relation->related_word;
            $context = $relation->context;
            
            // Kelimelerin türlerini tespit et
            $nov1 = $this->detectWordType($word1);
            $nov2 = $this->detectWordType($word2);
            
            // Bağlam varsa ve kategori olarak kullanılabilirse
            if (!empty($context) && strlen($context) > 2) {
                // Kelimeyi bu bağlamdaki kategoriye ekle
                $categoryId = $this->categoryManager->getCategoryIdByName($context);
                
                if ($categoryId) {
                    if ($this->categoryManager->addWordToCategory($word1, $categoryId, $relation->strength, $context, $nov1)) {
                        $categorized++;
                    }
                    
                    if ($this->categoryManager->addWordToCategory($word2, $categoryId, $relation->strength, $context, $nov2)) {
                        $categorized++;
                    }
                }
            }
            
            // Her 10 kelimede bir görsel geri bildirim
            if ($categorized % 10 === 0) {
                $this->output->write('.');
            }
        }
        
        $this->output->writeln('');
        return $categorized;
    }
    
    /**
     * Kelimenin türünü algıla (isim, fiil, sıfat vb.)
     */
    private function detectWordType($word)
    {
        // Temel Türkçe kelime tür sözlüğü
        $nounSuffixes = ['lık', 'lik', 'luk', 'lük', 'ci', 'cı', 'çi', 'çı', 'siz', 'lar', 'ler'];
        $verbSuffixes = ['mek', 'mak', 'miş', 'muş', 'müş', 'mış', 'ecek', 'acak', 'yor', 'di', 'ince', 'erek', 'arak', 'ip', 'ıp'];
        $adjectiveSuffixes = ['li', 'lı', 'lu', 'lü', 'sal', 'sel', 'siz', 'sız', 'ca', 'ce', 'imsi', 'ımsı'];
        
        // Başlangıç eklerine göre ön kontrol
        if (strlen($word) <= 3) {
            return 'isim'; // Çok kısa kelimeler genelde isimdir
        }
        
        // Fiil kontrol
        foreach ($verbSuffixes as $suffix) {
            if (mb_substr($word, -mb_strlen($suffix)) === $suffix) {
                return 'fiil';
            }
        }
        
        // Sıfat kontrol
        foreach ($adjectiveSuffixes as $suffix) {
            if (mb_substr($word, -mb_strlen($suffix)) === $suffix) {
                return 'sıfat';
            }
        }
        
        // İsim kontrol
        foreach ($nounSuffixes as $suffix) {
            if (mb_substr($word, -mb_strlen($suffix)) === $suffix) {
                return 'isim';
            }
        }
        
        return null; // Türü belirlenemedi
    }
} 