<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AI\Core\Brain;
use App\AI\Learn\LearningSystem;
use App\Models\AIData;
use Illuminate\Support\Facades\DB;
use App\Services\AIDataCollectorService;
use Illuminate\Support\Facades\Schema;

class ManageController extends Controller
{
    private $brain;
    protected $learningSystem;
    
    public function __construct()
    {
        $this->brain = new Brain();
        $this->learningSystem = new LearningSystem();
    }
    
    public function index()
    {
        $status = $this->getSystemStatus();
        return view('ai.manage', compact('status'));
    }
    
    public function updateSettings(Request $request)
    {
        try {
            // Öğrenme oranını güncelle
            $this->learningSystem->setLearningRate($request->input('learning_rate', 0.1));
            
            // Kişilik özelliklerini güncelle
            $this->brain->updateSettings([
                'learning_rate' => $request->input('learning_rate', 0.1),
                'emotional_sensitivity' => $request->input('emotional_sensitivity', 0.7),
                'personality_traits' => $request->input('personality_traits', [])
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ayarlar başarıyla güncellendi',
                'status' => $this->getSystemStatus()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ayarlar güncellenirken hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function trainModel(Request $request)
    {
        try {
            // Debug Log - İşlem başlıyor
            \Log::info('Eğitim işlemi başlatılıyor');
            
            // Veritabanı tablolarını kontrol et
            $wordRelationsTableExists = Schema::hasTable('word_relations');
            $wordDefinitionsTableExists = Schema::hasTable('word_definitions');
            $aiDataTableExists = Schema::hasTable('ai_data');
            
            \Log::info('Tablo durumu: word_relations=' . ($wordRelationsTableExists ? 'var' : 'yok') . 
                      ', word_definitions=' . ($wordDefinitionsTableExists ? 'var' : 'yok') . 
                      ', ai_data=' . ($aiDataTableExists ? 'var' : 'yok'));
            
            if (!$wordRelationsTableExists || !$wordDefinitionsTableExists || !$aiDataTableExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gerekli veritabanı tabloları bulunamadı. Lütfen önce migrate işlemini çalıştırın.',
                ], 500);
            }
            
            // Eğitim için veri kontrolü yap
            $dataCount = AIData::count();
            \Log::info('Veritabanındaki toplam veri sayısı: ' . $dataCount);
            
            if ($dataCount == 0) {
                \Log::warning('Veritabanında hiç veri yok, veri toplanıyor...');
                try {
                    // Veri toplama işlemini başlat
                    $collector = app(AIDataCollectorService::class);
                    $result = $collector->collectData();
                    \Log::info('Veri toplama sonucu: ', $result ?? ['sonuç yok']);
                } catch (\Exception $collectorException) {
                    \Log::error('Veri toplama hatası: ' . $collectorException->getMessage());
                    
                    // Varsayılan veri ekle
                    $this->addDefaultData();
                    $dataCount = AIData::count();
                    \Log::info('Varsayılan veri eklendi. Yeni veri sayısı: ' . $dataCount);
                }
            }
            
            // Eğitim verilerini hazırla
            $trainingData = $this->prepareTrainingData();
            \Log::info('Eğitim için hazırlanan veri sayısı: ' . count($trainingData));
            
            if (empty($trainingData)) {
                \Log::warning('Eğitim verisi oluşturulamadı, varsayılan veri ekleniyor...');
                $this->addDefaultData();
                $trainingData = $this->prepareTrainingData();
                \Log::info('Varsayılan verilerle eğitim için hazırlanan veri sayısı: ' . count($trainingData));
            }
            
            // Öğrenme oranını ayarla
            $learningRate = $request->input('learning_rate', 0.1);
            $this->learningSystem->setLearningRate($learningRate);
            \Log::info('Öğrenme oranı: ' . $learningRate);
            
            // WordRelations sınıfının doğru yüklendiğini kontrol et
            try {
                $wordRelationsClass = app(\App\AI\Core\WordRelations::class);
                if ($wordRelationsClass) {
                    \Log::info('WordRelations sınıfı başarıyla yüklendi');
                    
                    // WordRelations veritabanı durumunu kontrol et
                    $stats = $wordRelationsClass->getStats();
                    \Log::info('WordRelations istatistikleri: ', $stats);
                }
            } catch (\Exception $wordRelationsException) {
                \Log::error('WordRelations sınıfı hatası: ' . $wordRelationsException->getMessage());
            }
            
            // Eğitimi başlat
            \Log::info('LearningSystem->train() çağrılıyor...');
            $result = $this->learningSystem->train($trainingData);
            \Log::info('Eğitim sonucu: ' . ($result ? 'Başarılı' : 'Başarısız'));
            
            // Verileri sürekli öğrenme ile işleme
            \Log::info('Sürekli öğrenme başlatılıyor...');
            $continuousResult = $this->learningSystem->continuousLearning(['force' => true, 'limit' => 50]);
            \Log::info('Sürekli öğrenme sonucu: ', is_array($continuousResult) ? $continuousResult : ['Sonuç alınamadı']);
            
            return response()->json([
                'success' => true,
                'message' => 'Veri toplama ve eğitim başarıyla başlatıldı',
                'status' => $this->learningSystem->getStatus(),
                'data_count' => $dataCount,
                'training_data_count' => count($trainingData)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Eğitim işlemi hatası: ' . $e->getMessage());
            \Log::error('Hata sırası: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'İşlem sırasında bir hata oluştu: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    private function prepareTrainingData()
    {
        try {
            // Veritabanından eğitim verilerini al
            $words = AIData::select('word', 'sentence', 'context', 'category')
                ->where('language', 'tr')
                ->where('frequency', '>', 0)
                ->limit(1000)
                ->get();
                
            $trainingData = [];
            
            foreach ($words as $word) {
                $trainingData[] = [
                    'input' => $word->word,
                    'output' => $word->sentence ?? $word->word,
                    'context' => [
                        'category' => $word->category,
                        'context' => $word->context
                    ]
                ];
            }
            
            return $trainingData;
            
        } catch (\Exception $e) {
            // Eğer veritabanı hatası olursa, varsayılan veri seti döndür
            return [
                [
                    'input' => 'merhaba',
                    'output' => 'Merhaba, size nasıl yardımcı olabilirim?',
                    'context' => ['category' => 'greeting']
                ],
                [
                    'input' => 'nasılsın',
                    'output' => 'İyiyim, teşekkür ederim. Siz nasılsınız?',
                    'context' => ['category' => 'greeting']
                ]
            ];
        }
    }
    
    public function getSystemStatus()
    {
        try {
            $status = $this->learningSystem->getStatus();
            $brainStatus = $this->brain->getEmotionalState();
            
            // Hafıza kullanımını hesapla
            $memoryUsage = [
                'total' => $status['knowledge_base_size']['patterns'] + 
                          $status['knowledge_base_size']['rules'] + 
                          $status['knowledge_base_size']['concepts'],
                'max' => 100000
            ];
            
            return [
                'memory_usage' => number_format(($memoryUsage['total'] / $memoryUsage['max']) * 100, 2),
                'learning_progress' => number_format($status['progress'], 2),
                'is_training' => $status['is_training'],
                'learning_rate' => $status['learning_rate'],
                'emotional_state' => [
                    'happiness' => $brainStatus['intensity'] ?? 0.75,
                    'curiosity' => 0.85
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'memory_usage' => 0,
                'learning_progress' => 0,
                'is_training' => false,
                'learning_rate' => 0.1,
                'emotional_state' => [
                    'happiness' => 0.5,
                    'curiosity' => 0.5
                ]
            ];
        }
    }
    
    public function getDataStats()
    {
        try {
            $stats = [
                'total_words' => AIData::count(),
                'total_sentences' => AIData::whereNotNull('sentence')->count(),
                'categories' => AIData::select('category')
                    ->groupBy('category')
                    ->selectRaw('count(*) as count')
                    ->get(),
                'top_words' => AIData::orderBy('frequency', 'desc')
                    ->limit(10)
                    ->get(['word', 'frequency']),
                'last_updated' => AIData::latest()
                    ->first()
                    ->updated_at
                    ->diffForHumans()
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'İstatistikler alınırken hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Varsayılan temel verileri ekle
     */
    private function addDefaultData()
    {
        $defaultWords = [
            ['word' => 'merhaba', 'sentence' => 'Merhaba, size nasıl yardımcı olabilirim?', 'category' => 'greeting', 'frequency' => 10],
            ['word' => 'selam', 'sentence' => 'Selam, nasıl yardımcı olabilirim?', 'category' => 'greeting', 'frequency' => 8],
            ['word' => 'nasılsın', 'sentence' => 'İyiyim, teşekkür ederim. Siz nasılsınız?', 'category' => 'greeting', 'frequency' => 7],
            ['word' => 'teşekkür', 'sentence' => 'Rica ederim, başka bir konuda yardımcı olabilir miyim?', 'category' => 'greeting', 'frequency' => 6],
            ['word' => 'yardım', 'sentence' => 'Size nasıl yardımcı olabilirim?', 'category' => 'help', 'frequency' => 5],
            ['word' => 'güzel', 'sentence' => 'Güzel bir kelimedir.', 'category' => 'adjective', 'frequency' => 4],
            ['word' => 'çirkin', 'sentence' => 'Çirkin, güzelin zıt anlamlısıdır.', 'category' => 'adjective', 'frequency' => 3],
            ['word' => 'öğrenme', 'sentence' => 'Öğrenme, yeni bilgiler edinme sürecidir.', 'category' => 'education', 'frequency' => 4],
            ['word' => 'yapay', 'sentence' => 'Yapay zeka, bilgisayarların insan zekasını taklit etme yeteneğidir.', 'category' => 'technology', 'frequency' => 5],
            ['word' => 'zeka', 'sentence' => 'Zeka, öğrenme, akıl yürütme ve problem çözme yeteneğidir.', 'category' => 'intelligence', 'frequency' => 5]
        ];
        
        foreach ($defaultWords as $wordData) {
            AIData::updateOrCreate(
                ['word' => $wordData['word']],
                [
                    'sentence' => $wordData['sentence'],
                    'category' => $wordData['category'],
                    'language' => 'tr',
                    'frequency' => $wordData['frequency'],
                    'context' => 'Varsayılan veri'
                ]
            );
        }
        
        // WordRelations kaydı oluştur
        try {
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Eş anlamlılar
            $wordRelations->learnSynonym('merhaba', 'selam', 0.9);
            $wordRelations->learnSynonym('güzel', 'hoş', 0.8);
            
            // Zıt anlamlılar
            $wordRelations->learnAntonym('güzel', 'çirkin', 0.9);
            
            // İlişkili kelimeler
            $wordRelations->learnAssociation('yapay', 'zeka', 'technology', 0.95);
            $wordRelations->learnAssociation('öğrenme', 'zeka', 'education', 0.7);
            
            // Tanımlar
            $wordRelations->learnDefinition('yapay zeka', 'Bilgisayarların insan zekasını taklit etme yeteneğidir.');
            $wordRelations->learnDefinition('öğrenme', 'Yeni bilgiler edinme sürecidir.');
            
        } catch (\Exception $e) {
            \Log::error('Varsayılan kelime ilişkileri oluşturma hatası: ' . $e->getMessage());
        }
    }
    
    /**
     * Veritabanı bakımı çalıştır
     */
    public function runDatabaseMaintenance(Request $request)
    {
        try {
            $mode = $request->input('mode', 'clean');
            
            // Bakım komutunu çalıştır
            \Artisan::call('ai:db-maintenance', [
                '--mode' => $mode
            ]);
            
            $output = \Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Veritabanı bakımı başarıyla çalıştırıldı',
                'output' => $output,
                'mode' => $mode
            ]);
        } catch (\Exception $e) {
            \Log::error('Veritabanı bakımı hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Veritabanı bakımı sırasında bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
} 