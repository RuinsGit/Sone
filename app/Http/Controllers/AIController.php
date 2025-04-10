<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\AI\Core\Brain;
use App\Models\AIData;
use App\Models\Chat;
use App\Models\ChatMessage;

class AIController extends Controller
{
    /**
     * Yapay zeka ile konuşma
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'message' => 'required|string|max:1000',
                'chat_id' => 'nullable|integer'
            ]);
            
            // Brain nesnesini oluştur
            $brain = app(Brain::class);
            
            // Öğrenme sistemini al
            $learningSystem = $brain->getLearningSystem();
            
            // WordRelations sınıfını yükle
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Kullanıcı mesajı
            $message = $request->input('message');
            
            // Sohbet kaydı
            $chatId = $request->input('chat_id');
            $chat = null;
            
            if ($chatId) {
                $chat = Chat::find($chatId);
            }
            
            if (!$chat) {
                // Yeni sohbet oluştur
                $chat = Chat::create([
                    'user_id' => auth()->check() ? auth()->id() : null,
                    'title' => substr($message, 0, 50),
                    'status' => 'active'
                ]);
            }
            
            // Kullanıcı mesajını kaydet
            ChatMessage::create([
                'chat_id' => $chat->id,
                'content' => $message,
                'sender' => 'user'
            ]);
            
            // Mesajdaki yeni kelimeleri öğren
            if (strlen($message) > 10) {
                // Mesajı kelimelere ayır
                $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message));
                
                foreach ($words as $word) {
                    if (strlen($word) >= 3 && !in_array(strtolower($word), ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden'])) {
                        // Kelime veritabanında var mı kontrol et
                        $exists = \App\Models\AIData::where('word', $word)->exists();
                        
                        // Eğer kelime veritabanında yoksa ve geçerli bir kelimeyse öğren
                        if (!$exists && $wordRelations->isValidWord($word)) {
                            try {
                                Log::info("API üzerinden yeni kelime öğreniliyor: " . $word);
                                $learningSystem->learnWord($word);
                            } catch (\Exception $e) {
                                Log::error("Kelime öğrenme hatası: " . $e->getMessage(), ['word' => $word]);
                            }
                        }
                    }
                }
            }
            
            // Yapay zeka yanıtını al
            $response = $brain->processInput($message);
            
            // Yanıtı kelime ilişkileriyle zenginleştir
            $enhancedResponse = $this->enhanceResponseWithWordRelations($response);
            
            // AI mesajını kaydet
            ChatMessage::create([
                'chat_id' => $chat->id,
                'content' => $enhancedResponse,
                'sender' => 'ai'
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'chat_id' => $chat->id,
                    'response' => $enhancedResponse
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('AI yanıt hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'AI yanıt hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Yanıtı kelime ilişkileriyle zenginleştir
     * 
     * @param string $response Orijinal yanıt
     * @return string Zenginleştirilmiş yanıt
     */
    private function enhanceResponseWithWordRelations($response)
    {
        try {
            // Kelime ilişkileri sınıfını yükle
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Yanıt zaten yeterince uzunsa veya %30 ihtimalle ek yapmıyoruz
            if (strlen($response) > 150 || mt_rand(1, 100) <= 30) {
                return $response;
            }
            
            // Yanıttaki önemli kelimeleri bul
            $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $response));
            $importantWords = [];
            
            foreach ($words as $word) {
                if (strlen($word) >= 3 && !in_array(strtolower($word), ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden'])) {
                    $importantWords[] = $word;
                }
            }
            
            // Önemli kelime yoksa orijinal yanıtı döndür
            if (empty($importantWords)) {
                return $response;
            }
            
            // Rasgele bir kelime seç
            $selectedWord = $importantWords[array_rand($importantWords)];
            
            // 50% ihtimalle eş anlamlı, 25% ihtimalle zıt anlamlı, 25% ihtimalle akıllı cümle
            $random = mt_rand(1, 100);
            
            if ($random <= 50) {
                // Eş anlamlılarla ilgili bilgi ekle
                $synonyms = $wordRelations->getSynonyms($selectedWord);
                
                if (!empty($synonyms)) {
                    $synonym = array_rand($synonyms);
                    $additions = [
                        "Bu arada, '$selectedWord' kelimesinin eş anlamlısı '$synonym' kelimesidir.",
                        "'$selectedWord' ve '$synonym' benzer anlamlara sahiptir.",
                        "$selectedWord yerine $synonym da kullanılabilir."
                    ];
                    
                    $selectedAddition = $additions[array_rand($additions)];
                    
                    // Doğruluk kontrolü
                    $accuracy = $wordRelations->calculateSentenceAccuracy($selectedAddition, $selectedWord);
                    
                    if ($accuracy >= 0.6) {
                        Log::info("Eş anlamlı bilgi eklendi: $selectedAddition (Doğruluk: $accuracy)");
                        return $response . " " . $selectedAddition;
                    } else {
                        Log::info("Eş anlamlı bilgi doğruluk kontrolünden geçemedi: $selectedAddition (Doğruluk: $accuracy)");
                    }
                }
            } elseif ($random <= 75) {
                // Zıt anlamlılarla ilgili bilgi ekle
                $antonyms = $wordRelations->getAntonyms($selectedWord);
                
                if (!empty($antonyms)) {
                    $antonym = array_rand($antonyms);
                    $additions = [
                        "Bu arada, '$selectedWord' kelimesinin zıt anlamlısı '$antonym' kelimesidir.",
                        "'$selectedWord' ve '$antonym' zıt anlamlara sahiptir.",
                        "$selectedWord kelimesinin tam tersi $antonym olarak ifade edilir."
                    ];
                    
                    $selectedAddition = $additions[array_rand($additions)];
                    
                    // Doğruluk kontrolü
                    $accuracy = $wordRelations->calculateSentenceAccuracy($selectedAddition, $selectedWord);
                    
                    if ($accuracy >= 0.6) {
                        Log::info("Zıt anlamlı bilgi eklendi: $selectedAddition (Doğruluk: $accuracy)");
                        return $response . " " . $selectedAddition;
                    } else {
                        Log::info("Zıt anlamlı bilgi doğruluk kontrolünden geçemedi: $selectedAddition (Doğruluk: $accuracy)");
                    }
                }
            } else {
                // Akıllı cümle üret - doğruluk kontrolü bu metod içinde yapılıyor
                try {
                    // Minimum doğruluk değeri 0.6 ile cümle üret
                    $sentences = $wordRelations->generateSmartSentences($selectedWord, true, 1, 0.6);
                    
                    if (!empty($sentences)) {
                        Log::info("Akıllı cümle eklendi: " . $sentences[0]);
                        return $response . " " . $sentences[0];
                    }
                } catch (\Exception $e) {
                    Log::error("Akıllı cümle üretme hatası: " . $e->getMessage());
                }
            }
            
            // Hiçbir ekleme yapılamadıysa orijinal yanıtı döndür
            return $response;
            
        } catch (\Exception $e) {
            Log::error("Yanıt zenginleştirme hatası: " . $e->getMessage());
            return $response; // Hata durumunda orijinal yanıtı döndür
        }
    }
    
    /**
     * Bir kelime hakkında bilgi al
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWordInfo(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'word' => 'required|string|min:2|max:100'
            ]);
            
            // Brain nesnesini oluştur
            $brain = app(Brain::class);
            
            // Kelime bilgisini al
            $wordInfo = $brain->getWordRelations($request->input('word'));
            
            return response()->json([
                'success' => true,
                'data' => $wordInfo
            ]);
        } catch (\Exception $e) {
            Log::error('Kelime bilgisi alma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Kelime bilgisi alma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * URL parametresiyle kelime bilgisi getir
     * 
     * @param string $word
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWordInfoByParam($word)
    {
        try {
            if (empty($word) || strlen($word) < 2 || strlen($word) > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geçersiz kelime parametresi'
                ], 400);
            }
            
            // Brain nesnesini oluştur
            $brain = app(Brain::class);
            
            // WordRelations sınıfını da doğrudan kullan
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Brain üzerinden temel kelime ilişkilerini al
            $wordInfo = $brain->getWordRelations($word);
            
            // Kelimenin AI verilerini getir
            $aiData = \App\Models\AIData::where('word', $word)->first();
            
            // Daha fazla veri ekle
            if ($aiData) {
                $wordInfo['frequency'] = $aiData->frequency;
                $wordInfo['confidence'] = $aiData->confidence;
                $wordInfo['category'] = $aiData->category;
                $wordInfo['related_words'] = json_decode($aiData->related_words) ?: [];
                $wordInfo['examples'] = json_decode($aiData->usage_examples) ?: [];
                $wordInfo['metadata'] = json_decode($aiData->metadata) ?: [];
                $wordInfo['emotional_context'] = json_decode($aiData->emotional_context) ?: [];
                $wordInfo['created_at'] = $aiData->created_at->format('Y-m-d H:i:s');
                $wordInfo['updated_at'] = $aiData->updated_at->format('Y-m-d H:i:s');
            }
            
            // Tanımları getir
            $definitions = $wordRelations->getDefinitions($word);
            $wordInfo['definitions'] = $definitions ?: [];
            
            // Örnekleri ayrı bir getDefinitions metodu üzerinden al
            $examples = $wordRelations->getExamples($word);
            $wordInfo['examples'] = $examples ?: [];
            
            // İlişkili kelimelerin düz listesini oluştur
            $relatedWordsFlat = [];
            if (!empty($wordInfo['related_words']) && is_array($wordInfo['related_words'])) {
                foreach ($wordInfo['related_words'] as $item) {
                    if (is_array($item) && isset($item['word'])) {
                        $relatedWordsFlat[] = $item['word'];
                    } elseif (is_string($item)) {
                        $relatedWordsFlat[] = $item;
                    }
                }
            }
            $wordInfo['related'] = $relatedWordsFlat;
            
            return response()->json([
                'success' => true,
                'data' => $wordInfo
            ]);
        } catch (\Exception $e) {
            Log::error('Kelime bilgisi alma hatası: ' . $e->getMessage(), [
                'word' => $word,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Kelime bilgisi alma hatası: ' . $e->getMessage()
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
            // Brain nesnesini oluştur
            $brain = app(Brain::class);
            
            // Öğrenme sistemi var mı kontrol et
            $learningSystem = $brain->getLearningSystem();
            
            if (!$learningSystem) {
                Log::warning('Öğrenme sistemi bulunamadı');
                return response()->json([
                    'success' => false,
                    'message' => 'Öğrenme sistemi başlatılmadı'
                ]);
            }
            
            try {
                // Öğrenme durumunu al
                $status = $brain->getLearningStatus();
                
                return response()->json([
                    'success' => true,
                    'data' => $status
                ]);
            } catch (\Exception $e) {
                Log::error('Öğrenme durumu alma hatası: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Öğrenme durumu alma hatası: ' . $e->getMessage()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Brain oluşturma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Sistem hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Yapay zeka hakkında genel durum bilgisi
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAIStatus()
    {
        try {
            // Brain nesnesini oluştur
            $brain = app(Brain::class);
            
            // Durum bilgilerini al
            $status = [
                'memory' => $brain->getMemoryStatus(),
                'emotion' => $brain->getEmotionalState(),
                'learning' => $brain->getLearningStatus(),
                'consciousness' => $brain->getConsciousnessState(),
                'words_learned' => AIData::count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('AI durum bilgisi alma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'AI durum bilgisi alma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Kelime araması yap
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchWords(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'query' => 'required|string|min:2|max:100',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);
            
            // Arama parametreleri
            $query = $request->input('query');
            $limit = $request->input('limit', 20);
            
            // Kelime araması yap
            $words = AIData::where('word', 'like', "%$query%")
                ->orWhere('sentence', 'like', "%$query%")
                ->orWhere('category', 'like', "%$query%")
                ->orderBy('frequency', 'desc')
                ->limit($limit)
                ->get(['word', 'category', 'frequency', 'confidence']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'count' => $words->count(),
                    'words' => $words
                ]
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
     * Sohbet geçmişini getir
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChatHistory(Request $request)
    {
        try {
            // Parametreleri doğrula
            $request->validate([
                'chat_id' => 'required|integer'
            ]);
            
            // Sohbet kaydını al
            $chat = Chat::with(['messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])->find($request->input('chat_id'));
            
            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sohbet bulunamadı'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $chat
            ]);
        } catch (\Exception $e) {
            Log::error('Sohbet geçmişi alma hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Sohbet geçmişi alma hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Kullanıcı sohbetlerini listele
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserChats()
    {
        try {
            // Kullanıcı ID'sine göre sohbetleri getir
            $userId = auth()->check() ? auth()->id() : null;
            
            $chats = Chat::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->get(['id', 'title', 'status', 'created_at', 'updated_at']);
            
            return response()->json([
                'success' => true,
                'data' => $chats
            ]);
        } catch (\Exception $e) {
            Log::error('Kullanıcı sohbetleri listeleme hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Kullanıcı sohbetleri listeleme hatası: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Yapay zeka durumunu getir (getAIStatus'a yönlendir)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus()
    {
        return $this->getAIStatus();
    }
}
