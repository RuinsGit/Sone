<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\AI\Learn\LearningSystem;
use App\AI\Core\CategoryManager;
use App\AI\Core\WordRelations;
use App\Models\AIData;

class ManageController extends Controller
{
    /**
     * Yönetim paneli ana sayfasını göster
     * 
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('ai.manage');
    }
    
    /**
     * Öğrenme işlemini başlat
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function startLearningProcess(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'word_limit' => 'nullable|integer|min:1|max:1000',
                'manual_words' => 'nullable|array'
            ]);
            
            // Öğrenme sistemini yükle
            $learningSystem = $this->loadLearningSystem();
            
            // Kelime limiti
            $wordLimit = $request->input('word_limit', 50);
            
            // Manuel kelimeler varsa ekle
            $manualWords = $request->input('manual_words', []);
            if (!empty($manualWords)) {
                $learningSystem->addManualWords($manualWords);
            }
            
            // Öğrenme işlemini başlat
            $result = $learningSystem->startLearning($wordLimit);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'learned_count' => $result['learned'],
                        'total_words' => $result['total'],
                        'duration' => $result['duration'],
                        'errors' => $result['errors']
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Öğrenme başlatma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Öğrenme başlatma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Öğrenme durumunu getir
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLearningStatus()
    {
        try {
            // Öğrenme sistemini yükle
            $learningSystem = $this->loadLearningSystem();
            
            // Durumu al
            $status = $learningSystem->getLearningStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('Öğrenme durumu alma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Öğrenme durumu alma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Öğrenme sistemi istatistiklerini getir
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLearningSystemStats()
    {
        try {
            // İstatistik verileri
            $stats = [
                'word_count' => AIData::count(),
                'categories' => app(CategoryManager::class)->getStats(),
                'relations' => app(WordRelations::class)->getStats(),
                'recent_words' => AIData::orderBy('created_at', 'desc')
                    ->take(10)
                    ->get(['word', 'category', 'created_at'])
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('İstatistik alma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'İstatistik alma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Belirli bir kelimeyi öğren
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function learnWord(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'word' => 'required|string|min:2|max:100'
            ]);
            
            // Öğrenme sistemini yükle
            $learningSystem = $this->loadLearningSystem();
            
            // Kelimeyi öğren
            $result = $learningSystem->learnWord($request->input('word'));
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'word' => $request->input('word'),
                        'metadata' => $result['metadata']
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Kelime öğrenme hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Kelime öğrenme hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Öğrenme sistemini temizle
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearLearningSystem(Request $request)
    {
        try {
            // Güvenlik kontrolü
            if ($request->input('confirm') !== 'yes') {
                return response()->json([
                    'success' => false,
                    'message' => 'İşlemi onaylamanız gerekiyor.'
                ]);
            }
            
            // Tabloları temizle
            AIData::truncate();
            
            return response()->json([
                'success' => true,
                'message' => 'Öğrenme sistemi veritabanı temizlendi.'
            ]);
        } catch (\Exception $e) {
            Log::error('Veritabanı temizleme hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Veritabanı temizleme hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Belirli bir kelime için akıllı cümleler oluştur
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateSmartSentences(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'word' => 'required|string|min:2|max:50',
                'count' => 'nullable|integer|min:1|max:10',
                'save' => 'nullable|boolean'
            ]);
            
            $word = trim($request->input('word'));
            $count = $request->input('count', 3);
            $save = $request->input('save', true);
            
            // WordRelations sınıfını başlat
            $wordRelations = app(WordRelations::class);
            
            // Kelime öğrenilmiş mi kontrol et
            $wordExists = AIData::where('word', $word)->exists();
            if (!$wordExists) {
                // Kelime öğrenilmemişse, sisteme öğret
                $learningSystem = $this->loadLearningSystem();
                if (!$learningSystem) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Öğrenme sistemi başlatılamadı'
                    ], 500);
                }
                
                $result = $learningSystem->learnWord($word);
                if (!$result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kelime öğrenilemedi: ' . $result['message']
                    ], 400);
                }
                
                Log::info("$word kelimesi öğrenildi, şimdi akıllı cümleler oluşturulacak");
            }
            
            // Akıllı cümleler oluştur
            $sentences = $wordRelations->generateSmartSentences($word, $save, $count);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'word' => $word,
                    'sentences' => $sentences,
                    'count' => count($sentences)
                ],
                'message' => count($sentences) > 0 
                    ? $word . ' kelimesi için ' . count($sentences) . ' cümle oluşturuldu' 
                    : $word . ' kelimesi için cümle oluşturulamadı'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Akıllı cümle oluşturma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Akıllı cümle oluşturma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Öğrenme sistemini yükle
     * 
     * @return LearningSystem
     */
    private function loadLearningSystem()
    {
        try {
            // IoC container'dan sistemleri yükle
            $categoryManager = app(CategoryManager::class);
            $wordRelations = app(WordRelations::class);
            
            // LearningSystem nesnesini oluştur
            $learningSystem = app(LearningSystem::class);
            
            return $learningSystem;
        } catch (\Exception $e) {
            Log::error('Öğrenme sistemi yükleme hatası: ' . $e->getMessage());
            throw new \Exception('Öğrenme sistemi yüklenemedi: ' . $e->getMessage());
        }
    }
    
    /**
     * Kelimeleri ara
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchWord(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'query' => 'required|string|min:2|max:100'
            ]);
            
            // Arama yap
            $query = $request->input('query');
            $results = AIData::where('word', 'like', "%$query%")
                ->orWhere('sentence', 'like', "%$query%")
                ->orWhere('category', 'like', "%$query%")
                ->limit(20)
                ->get(['word', 'category', 'sentence as definition', 'related_words', 'created_at']);
            
            // Sonuçları formatla
            $formattedResults = $results->map(function($item) {
                $data = $item->toArray();
                if (isset($data['related_words']) && !empty($data['related_words'])) {
                    $data['relations'] = json_decode($data['related_words']);
                    unset($data['related_words']);
                }
                return $data;
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedResults
            ]);
        } catch (\Exception $e) {
            Log::error('Kelime arama hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Kelime arama hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Öğrenme ilerlemesini getir
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLearningProgress()
    {
        try {
            // Öğrenme sistemini yükle
            $learningSystem = $this->loadLearningSystem();
            
            // İlerleme bilgisini al
            $progress = $learningSystem->getProgress();
            
            return response()->json([
                'success' => true,
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            Log::error('Öğrenme ilerleme bilgisi alma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Öğrenme ilerleme bilgisi alma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Öğrenme işlemini durdur
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stopLearningProcess()
    {
        try {
            $learningSystem = $this->loadLearningSystem();
            
            $result = $learningSystem->stopLearning();
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Öğrenme işlemi durdurulurken hata: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Öğrenme işlemi durdurulurken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Otomatik olarak kelimeler seçip akıllı cümleler oluştur
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateAutoSentences(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'count' => 'nullable|integer|min:1|max:50',
                'save' => 'nullable|string'
            ]);
            
            $count = $request->input('count', 10);
            $save = $request->input('save') === "1";
            
            // Debug için log
            Log::info("Otomatik cümle oluşturma başladı: count=$count, save=" . ($save ? 'true' : 'false'));
            
            // LearningSystem'ı yükle
            $learningSystem = $this->loadLearningSystem();
            if (!$learningSystem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Öğrenme sistemi başlatılamadı'
                ], 500);
            }
            
            // WordRelations sınıfını başlat
            $wordRelations = app(WordRelations::class);
            
            // Önce öğrenilmiş kelimeleri kontrol et
            $learnedWords = AIData::pluck('word')->toArray();
            
            if (count($learnedWords) > 0) {
                Log::info("Veritabanında " . count($learnedWords) . " öğrenilmiş kelime bulundu");
                
                // Önce mevcut kelimelerden bazılarını kullan
                $randomLearnedWords = array_slice($learnedWords, 0, min(intval($count/2), 5));
                
                // Kalan sayıda yeni kelime öğren
                $remainingCount = $count - count($randomLearnedWords);
                $newWords = $learningSystem->getAutomaticWordsToLearn($remainingCount);
                
                $words = array_merge($randomLearnedWords, $newWords);
            } else {
                // Hiç öğrenilmiş kelime yoksa tamamen yeni kelimeler seç
                $words = $learningSystem->getAutomaticWordsToLearn($count);
            }
            
            if (empty($words)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Öğrenilecek kelime bulunamadı'
                ]);
            }
            
            Log::info("Otomatik seçilen kelimeler: " . implode(", ", $words));
            
            $allSentences = [];
            $learnedWords = [];
            
            // Her kelime için cümle oluştur
            foreach ($words as $word) {
                try {
                    // Önce kelimeyi öğren (eğer öğrenilmemişse)
                    if (!AIData::where('word', $word)->exists()) {
                        $result = $learningSystem->learnWord($word);
                        if ($result['success']) {
                            $learnedWords[] = $word;
                            Log::info("$word kelimesi başarıyla öğrenildi");
                        } else {
                            Log::warning("Kelime öğrenme hatası: " . $result['message']);
                            continue;
                        }
                    }
                    
                    // Akıllı cümleler oluştur
                    $sentences = $wordRelations->generateSmartSentences($word, $save, 3);
                    
                    Log::info("$word kelimesi için " . count($sentences) . " cümle oluşturuldu");
                    
                    if (!empty($sentences)) {
                        $allSentences[$word] = $sentences;
                    }
                } catch (\Exception $e) {
                    Log::error("$word kelimesi işlenirken hata: " . $e->getMessage());
                }
            }
            
            if (empty($allSentences)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hiç cümle oluşturulamadı. Sistem kelime öğreniyor olabilir, lütfen biraz bekleyin ve tekrar deneyin.'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => count($allSentences) . ' kelime için toplam ' . array_sum(array_map('count', $allSentences)) . ' cümle oluşturuldu',
                'data' => [
                    'words' => array_keys($allSentences),
                    'sentences' => $allSentences,
                    'newly_learned' => $learnedWords
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Otomatik cümle oluşturma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Otomatik cümle oluşturma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Tüm öğrenilen kelimeleri getir
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function getAllWords(Request $request)
    {
        try {
            // Filtreleme parametreleri
            $search = $request->input('search', '');
            $category = $request->input('category', '');
            $sort = $request->input('sort', 'word');
            $order = $request->input('order', 'asc');
            
            // Kelime sorgusunu oluştur
            $query = AIData::query();
            
            // Arama filtresi
            if (!empty($search)) {
                $query->where('word', 'like', "%{$search}%")
                    ->orWhere('sentence', 'like', "%{$search}%");
            }
            
            // Kategori filtresi
            if (!empty($category)) {
                $query->where('category', $category);
            }
            
            // Sıralama
            $query->orderBy($sort, $order);
            
            // Sayfalandırılmış sonuçları al
            $words = $query->paginate(20);
            
            // Benzersiz kategorileri al
            $categories = AIData::distinct()->pluck('category');
            
            return view('ai.words', [
                'words' => $words,
                'categories' => $categories,
                'search' => $search,
                'category' => $category,
                'sort' => $sort,
                'order' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Kelime listesi alma hatası: ' . $e->getMessage());
            
            return view('ai.words', [
                'error' => 'Kelime listesi alınırken bir hata oluştu: ' . $e->getMessage(),
                'words' => collect(),
                'categories' => collect()
            ]);
        }
    }
}
