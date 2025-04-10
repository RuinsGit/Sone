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
}
