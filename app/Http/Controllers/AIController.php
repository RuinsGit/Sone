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
            
            // Yapay zeka yanıtını al
            $response = $brain->processInput($message);
            
            // AI mesajını kaydet
            ChatMessage::create([
                'chat_id' => $chat->id,
                'content' => $response,
                'sender' => 'ai'
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'chat_id' => $chat->id,
                    'response' => $response
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
