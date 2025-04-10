<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WordCategory;
use App\Models\WordCategoryItem;
use App\AI\Core\CategoryManager;
use Illuminate\Support\Facades\Log;

class InitializeCategorySystem extends Command
{
    protected $signature = 'ai:init-categories {--force : Mevcut kategorileri sil ve yeniden oluştur}';
    protected $description = 'Yapay zeka için temel kategori sistemini oluştur';

    private $categoryManager;

    public function __construct(CategoryManager $categoryManager)
    {
        parent::__construct();
        $this->categoryManager = $categoryManager;
    }

    public function handle()
    {
        $this->info('Kategori sistemi başlatılıyor...');
        
        // Force parametresi kontrol ediliyor
        $force = $this->option('force');
        
        if ($force) {
            if ($this->confirm('Bu işlem tüm kategori verilerini silecek. Devam etmek istiyor musunuz?')) {
                $this->truncateCategories();
            } else {
                $this->info('İşlem iptal edildi.');
                return 0;
            }
        }
        
        // Mevcut kategori sayısını kontrol et
        $existingCount = WordCategory::count();
        if ($existingCount > 0 && !$force) {
            $this->info("Sistemde zaten $existingCount kategori bulunuyor.");
            $this->info("Var olan kategorileri koruyarak, eksik olanları ekliyorum...");
            
            // Ana kategorileri oluştur (varsa atla)
            $this->createMainCategories(false);
            
            // Örnek kelimeler ekle (tekrarları atla)
            $this->addExampleWords();
            
            $this->info('Kategori güncelleme işlemi tamamlandı!');
        } else {
            // Ana kategorileri oluştur
            $this->createMainCategories(true);
            
            // Örnek kelimeler ekle
            $this->addExampleWords();
            
            $this->info('Kategori sistemi başarıyla oluşturuldu!');
        }
        
        // İstatistikleri göster
        $stats = $this->categoryManager->getStats();
        $this->info('Toplam kategori sayısı: ' . $stats['total_categories']);
        $this->info('Toplam kategorize edilmiş kelime sayısı: ' . $stats['total_categorized_words']);
        
        return 0;
    }
    
    /**
     * Kategori tablolarını temizle
     */
    private function truncateCategories()
    {
        $this->info('Kategori verileri temizleniyor...');
        
        try {
            WordCategoryItem::truncate();
            WordCategory::truncate();
            
            $this->info('Tüm kategori verileri silindi.');
        } catch (\Exception $e) {
            $this->error('Veri temizleme hatası: ' . $e->getMessage());
        }
    }
    
    /**
     * Ana kategorileri oluştur
     */
    private function createMainCategories($overwrite = false)
    {
        $this->info('Ana kategoriler oluşturuluyor...');
        
        $mainCategories = [
            'Duygular' => 'İnsan duyguları ve hislerle ilgili kategoriler',
            'Doğa' => 'Doğa, çevre ve dünya ile ilgili kategoriler',
            'İnsan' => 'İnsan vücudu, davranışları ve ilişkileri',
            'Teknoloji' => 'Teknoloji, bilim ve makineler',
            'Sanat' => 'Sanat, müzik, edebiyat ve yaratıcılık',
            'Bilim' => 'Bilimsel kavramlar ve araştırmalar',
            'Toplum' => 'Toplum, kültür ve sosyal yapılar',
            'Sağlık' => 'Sağlık, tıp ve iyilik hali',
            'Yemek' => 'Yiyecek, içecek ve mutfak kültürü',
            'Spor' => 'Spor, egzersiz ve fiziksel aktiviteler'
        ];
        
        // Alt kategoriler
        $subCategories = [
            'Duygular' => [
                'Mutluluk' => 'Sevinç, neşe ve memnuniyet duyguları',
                'Üzüntü' => 'Keder, acı ve mutsuzluk duyguları',
                'Öfke' => 'Kızgınlık, sinir ve hiddet duyguları',
                'Korku' => 'Endişe, kaygı ve korkuyla ilgili duygular',
                'Sevgi' => 'Aşk, sevgi ve bağlılık duyguları',
                'Heyecan' => 'Heyecan, coşku ve merak duyguları'
            ],
            'Doğa' => [
                'Hayvanlar' => 'Tüm hayvan türleri ve özellikleri',
                'Bitkiler' => 'Ağaçlar, çiçekler ve bitkiler',
                'Hava Durumu' => 'İklim, yağış ve hava koşulları',
                'Coğrafya' => 'Dağlar, nehirler, göller ve coğrafi yapılar',
                'Çevre' => 'Çevre koruması ve sürdürülebilirlik'
            ],
            'Teknoloji' => [
                'Bilgisayarlar' => 'Bilgisayar sistemleri ve yazılımlar',
                'İnternet' => 'Web, sosyal medya ve çevrimiçi hizmetler',
                'Mobil Cihazlar' => 'Telefonlar, tabletler ve mobil teknolojiler',
                'Yapay Zeka' => 'AI, makine öğrenimi ve robotik',
                'Yazılım' => 'Programlama, kod yazma ve uygulama geliştirme'
            ]
        ];
        
        $mainCategoryIds = [];
        $created = 0;
        $skipped = 0;
        
        // Ana kategorileri oluştur
        foreach ($mainCategories as $name => $description) {
            // Kategori var mı kontrol et
            $existing = WordCategory::where('name', $name)->first();
            
            if ($existing && !$overwrite) {
                $mainCategoryIds[$name] = $existing->id;
                $skipped++;
                continue;
            }
            
            $categoryId = $this->categoryManager->createCategory($name, $description);
            $mainCategoryIds[$name] = $categoryId;
            $created++;
            $this->info("Ana kategori oluşturuldu: $name");
        }
        
        // Alt kategorileri oluştur
        foreach ($subCategories as $parentName => $children) {
            if (isset($mainCategoryIds[$parentName])) {
                $parentId = $mainCategoryIds[$parentName];
                
                foreach ($children as $name => $description) {
                    // Alt kategori var mı kontrol et
                    $existing = WordCategory::where('name', $name)
                        ->where('parent_id', $parentId)
                        ->first();
                        
                    if ($existing && !$overwrite) {
                        $skipped++;
                        continue;
                    }
                    
                    $this->categoryManager->createCategory($name, $description, $parentId);
                    $created++;
                    $this->info("Alt kategori oluşturuldu: $name (üst kategori: $parentName)");
                }
            }
        }
        
        $this->info("Toplam $created yeni kategori oluşturuldu, $skipped mevcut kategori atlandı.");
    }
    
    /**
     * Örnek kelimeler ekle
     */
    private function addExampleWords()
    {
        $this->info('Örnek kelimeler ekleniyor...');
        
        // Kategorilere göre düzenlenmiş kelimeler ve türleri
        $categoryWords = [
            'Mutluluk' => [
                ['mutlu', 'sıfat'], ['sevinç', 'isim'], ['neşe', 'isim'], 
                ['keyif', 'isim'], ['gülümseme', 'isim'], ['kahkaha', 'isim'], 
                ['eğlence', 'isim'], ['tatmin', 'isim']
            ],
            'Üzüntü' => [
                ['üzgün', 'sıfat'], ['keder', 'isim'], ['acı', 'isim'], 
                ['gözyaşı', 'isim'], ['yas', 'isim'], ['hüzün', 'isim'], 
                ['mutsuzluk', 'isim'], ['kırgınlık', 'isim']
            ],
            'Öfke' => [
                ['kızgın', 'sıfat'], ['öfke', 'isim'], ['sinir', 'isim'], 
                ['hiddet', 'isim'], ['nefret', 'isim'], ['kin', 'isim'], 
                ['çıldırmak', 'fiil'], ['kızmak', 'fiil']
            ],
            'Korku' => [
                ['korku', 'isim'], ['endişe', 'isim'], ['kaygı', 'isim'], 
                ['panik', 'isim'], ['dehşet', 'isim'], ['ürkmek', 'fiil'], 
                ['tedirgin', 'sıfat'], ['fobi', 'isim']
            ],
            'Sevgi' => [
                ['sevgi', 'isim'], ['aşk', 'isim'], ['tutku', 'isim'], 
                ['bağlılık', 'isim'], ['şefkat', 'isim'], ['sadakat', 'isim'], 
                ['sevmek', 'fiil'], ['beğeni', 'isim']
            ],
            'Hayvanlar' => [
                ['kedi', 'isim'], ['köpek', 'isim'], ['kuş', 'isim'], 
                ['balık', 'isim'], ['aslan', 'isim'], ['kaplan', 'isim'], 
                ['fil', 'isim'], ['zürafa', 'isim'], ['sincap', 'isim']
            ],
            'Bitkiler' => [
                ['ağaç', 'isim'], ['çiçek', 'isim'], ['çimen', 'isim'], 
                ['yaprak', 'isim'], ['orman', 'isim'], ['bitki', 'isim'], 
                ['tohum', 'isim'], ['kök', 'isim'], ['dal', 'isim']
            ],
            'Bilgisayarlar' => [
                ['bilgisayar', 'isim'], ['laptop', 'isim'], ['ekran', 'isim'], 
                ['klavye', 'isim'], ['fare', 'isim'], ['işlemci', 'isim'], 
                ['bellek', 'isim'], ['donanım', 'isim']
            ],
            'İnternet' => [
                ['internet', 'isim'], ['web', 'isim'], ['site', 'isim'], 
                ['tarayıcı', 'isim'], ['bağlantı', 'isim'], ['çevrimiçi', 'sıfat'], 
                ['indirmek', 'fiil'], ['yüklemek', 'fiil']
            ],
            'Yapay Zeka' => [
                ['yapay', 'sıfat'], ['zeka', 'isim'], ['algoritma', 'isim'], 
                ['öğrenme', 'isim'], ['model', 'isim'], ['veri', 'isim'], 
                ['tahmin', 'isim'], ['analiz', 'isim'], ['robot', 'isim']
            ]
        ];
        
        $addedCount = 0;
        $skippedCount = 0;
        
        foreach ($categoryWords as $categoryName => $words) {
            $categoryId = $this->categoryManager->getCategoryIdByName($categoryName);
            
            if ($categoryId) {
                $addedForCategory = 0;
                
                foreach ($words as $wordInfo) {
                    $word = $wordInfo[0];
                    $nov = $wordInfo[1] ?? null;
                    
                    // Kelime bu kategoride ve bu türde zaten var mı kontrol et
                    if ($this->categoryManager->wordExists($word, $categoryId, $nov)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    if ($this->categoryManager->addWordToCategory($word, $categoryId, 0.9, null, $nov)) {
                        $addedCount++;
                        $addedForCategory++;
                    }
                }
                
                if ($addedForCategory > 0) {
                    $this->info("'$categoryName' kategorisine $addedForCategory kelime eklendi.");
                }
            }
        }
        
        $this->info("Toplam $addedCount yeni kelime kategorilere eklendi, $skippedCount mevcut kelime atlandı.");
    }
} 