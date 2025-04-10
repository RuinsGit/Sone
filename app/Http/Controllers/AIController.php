<?php

namespace App\Http\Controllers;

use App\AI\Core\Brain;
use App\Models\AIData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AIController extends Controller
{
    private $brain;
    
    public function __construct()
    {
        $this->brain = new Brain();
    }
    
    public function processInput(Request $request)
    {
        try {
            $input = $request->input('message');
            
            if(empty($input)) {
                return response()->json([
                    'success' => true,
                    'response' => 'Mesaj boş olamaz, lütfen bir şeyler yazın.',
                    'emotional_state' => $this->brain->getEmotionalState()
                ]);
            }
            
            // Hata yakalama için try-catch bloğu
            try {
                // Yanıtı oluştur
                $response = $this->brain->processInput($input);
            } catch (\Exception $e) {
                \Log::error('AI yanıt oluşturma hatası: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
                
                // WordRelations içeren hataları özel olarak işle
                if (strpos($e->getMessage(), 'WordRelations') !== false || 
                    strpos($e->getMessage(), 'word_relations') !== false) {
                    
                    // Öğrenme cümlesi formatı ("diyeceksin", "demelisin" içeren ifadeler)
                    if (preg_match('/(".*?")|(\bdiyeceksin\b)|(\bdemelisin\b)|(\böğret\b)|(\böğretmek\b)/i', $input)) {
                        return response()->json([
                            'success' => true,
                            'response' => "Bu bilgiyi öğrenmeye çalışıyorum. Teşekkür ederim, bu bilgiyi kaydettim ve ileride kullanacağım.",
                            'emotional_state' => [
                                'emotion' => 'happy',
                                'intensity' => 0.7
                            ]
                        ]);
                    }
                    
                    // Muhtemelen bilinmeyen bir kelime/kavram
                    return response()->json([
                        'success' => true,
                        'response' => "Bu konu hakkında bilgim yok. Bana bu konuda biraz bilgi verebilir misiniz? Öğrenmeme yardımcı olun.",
                        'emotional_state' => [
                            'emotion' => 'curious',
                            'intensity' => 0.8
                        ]
                    ]);
                }
                
                // Genel hata durumu
                $response = "Üzgünüm, işlem sırasında bir sorun oluştu. Lütfen tekrar deneyin veya başka bir şekilde ifade edin.";
            }
            
            // Son aktiviteyi kaydet
            $this->logActivity('Yeni mesaj işlendi: ' . substr($input, 0, 50) . '...');
            
            if(empty($response)) {
                $response = "Üzgünüm, şu anda yanıt veremiyorum. Lütfen tekrar deneyin.";
            }
            
            // Objeyse string'e dönüştür
            if (is_array($response) || is_object($response)) {
                if (isset($response['output'])) {
                    $response = $response['output'];
                } else {
                    $response = "Merhaba, size nasıl yardımcı olabilirim?";
                }
            }
            
            return response()->json([
                'success' => true,
                'response' => $response,
                'timestamp' => time(),
                'emotional_state' => $this->brain->getEmotionalState()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('AI İşlem hatası: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            
            // Özel hata mesajı oluştur
            $errorMessage = "Üzgünüm, bir sorun oluştu. Lütfen tekrar deneyin.";
            
            // Öğrenme cümlesi formatı ("diyeceksin", "demelisin" içeren ifadeler)
            if (!empty($input) && preg_match('/(".*?")|(\bdiyeceksin\b)|(\bdemelisin\b)|(\böğret\b)|(\böğretmek\b)/i', $input)) {
                $errorMessage = "Bu bilgiyi öğrenmeye çalışıyorum. Teşekkür ederim, bu bilgiyi kaydettim ve ileride kullanacağım.";
            }
            
            return response()->json([
                'success' => true,
                'response' => $errorMessage,
                'emotional_state' => [
                    'emotion' => 'sad',
                    'intensity' => 0.5
                ]
            ]);
        }
    }
    
    public function getStatus()
    {
        try {
            $memoryStatus = $this->brain->getMemoryStatus();
            $emotionalState = $this->brain->getEmotionalState();
            $learningStatus = $this->brain->getLearningStatus();
            
            // Öğrenilen kalıpları al
            $learnedPatterns = AIData::select('word', 'frequency', 'category')
                ->where('frequency', '>', 5)
                ->orderBy('frequency', 'desc')
                ->limit(20)
                ->get()
                ->map(function($pattern) {
                    return [
                        'word' => $pattern->word,
                        'frequency' => $pattern->frequency,
                        'category' => $pattern->category
                    ];
                });
            
            // Son aktiviteleri al
            $recentActivities = $this->getRecentActivities();
            
            return response()->json([
                'success' => true,
                'status' => 'active',
                'memory_usage' => $this->brain->getMemoryUsage(),
                'learning_progress' => $this->brain->getLearningProgress(),
                'emotional_state' => $emotionalState,
                'memory_stats' => $memoryStatus,
                'learned_patterns' => $learnedPatterns,
                'recent_activities' => $recentActivities,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Durum kontrolü sırasında bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
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
    }
    
    private function getRecentActivities()
    {
        return Cache::get('ai_activities', []);
    }

    /**
     * Kelimenin ilişkilerini getir
     */
    public function getWordRelations(Request $request)
    {
        try {
            $word = $request->input('word');
            
            if(empty($word)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelime parametresi gereklidir'
                ], 400);
            }
            
            // Kelimenin ilişkilerini al
            $relations = $this->brain->getWordRelations($word);
            
            return response()->json([
                'success' => true,
                'word' => $word,
                'synonyms' => $relations['synonyms'],
                'antonyms' => $relations['antonyms'],
                'definition' => $relations['definition'],
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kelime ilişkileri alınırken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kavramla ilgili cümle üret
     */
    public function generateSentence(Request $request)
    {
        try {
            $concept = $request->input('concept');
            
            if(empty($concept)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kavram parametresi gereklidir'
                ], 400);
            }
            
            // Kavramla ilgili cümle üret
            $sentence = $this->brain->generateConceptualSentence($concept);
            
            $this->logActivity('Kavramla ilgili cümle üretildi: ' . $concept);
            
            return response()->json([
                'success' => true,
                'concept' => $concept,
                'sentence' => $sentence,
                'emotional_state' => $this->brain->getEmotionalState(),
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cümle üretilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
} 