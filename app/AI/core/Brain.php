<?php

namespace App\AI\Core;

use App\AI\Memory\Memory;
use App\AI\Core\EmotionEngine;
use App\AI\Learn\LearningSystem;
use App\AI\Core\Consciousness;
use Illuminate\Support\Facades\Cache;

class Brain
{
    private $memory;
    private $emotions;
    private $learning;
    private $consciousness;
    private $lastThoughtTime;
    private $thoughtQueue = [];
    
    public function __construct()
    {
        $this->initializeComponents();
        $this->lastThoughtTime = time();
        $this->startBackgroundProcesses();
    }
    
    private function initializeComponents()
    {
        // Temel bileşenleri başlat
        $this->memory = new Memory();
        $this->emotions = new EmotionEngine();
        $this->learning = new LearningSystem();
        $this->consciousness = new Consciousness();
    }
    
    private function startBackgroundProcesses()
    {
        // Her 5 dakikada bir düşünme sürecini başlat
        if(time() - $this->lastThoughtTime >= 300) {
            $this->think();
            $this->lastThoughtTime = time();
        }
    }
    
    private function think()
    {
        // Hafızadaki bilgileri analiz et
        $memories = $this->memory->getLongTermMemory();
        $emotionalState = $this->emotions->getCurrentEmotion();
        $learningStatus = $this->learning->getStatus();
        
        // Yeni bağlantılar ve kalıplar bul
        $patterns = $this->findPatterns($memories);
        
        // Yeni kurallar oluştur
        $rules = $this->generateRules($patterns);
        
        // Öğrenilen bilgileri uygula
        $this->applyLearning($patterns, $rules);
        
        // Bilinç durumunu güncelle
        $this->updateConsciousness($patterns, $rules);
    }
    
    private function findPatterns($memories)
    {
        $patterns = [];
        
        foreach($memories as $memory) {
            // Tekrar eden kalıpları bul
            $currentPatterns = $this->learning->extractPatterns($memory);
            
            foreach($currentPatterns as $pattern) {
                if(!isset($patterns[$pattern])) {
                    $patterns[$pattern] = 0;
                }
                $patterns[$pattern]++;
            }
        }
        
        // En sık tekrar eden kalıpları seç
        arsort($patterns);
        return array_slice($patterns, 0, 10, true);
    }
    
    private function generateRules($patterns)
    {
        $rules = [];
        
        foreach($patterns as $pattern => $frequency) {
            // İlgili hafıza ve duygusal bağlamları bul
            $contexts = $this->memory->search($pattern);
            $emotionalContexts = $this->emotions->getEmotionHistory();
            
            // Yeni kural oluştur
            $rule = [
                'pattern' => $pattern,
                'frequency' => $frequency,
                'contexts' => $contexts,
                'emotional_impact' => $this->calculateEmotionalImpact($pattern, $emotionalContexts),
                'confidence' => $this->calculateConfidence($frequency, $contexts)
            ];
            
            $rules[] = $rule;
        }
        
        return $rules;
    }
    
    private function calculateEmotionalImpact($pattern, $emotionalContexts)
    {
        // Kalıbın duygusal etkisini hesapla
        $impact = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0
        ];
        
        foreach($emotionalContexts as $emotion => $count) {
            if(in_array($emotion, ['happy', 'surprised'])) {
                $impact['positive'] += $count;
            } elseif(in_array($emotion, ['sad', 'angry', 'fearful'])) {
                $impact['negative'] += $count;
            } else {
                $impact['neutral'] += $count;
            }
        }
        
        return $impact;
    }
    
    private function calculateConfidence($frequency, $contexts)
    {
        // Kuralın güvenilirliğini hesapla
        $totalContexts = count($contexts);
        $uniqueContexts = count(array_unique($contexts));
        
        return ($frequency * $uniqueContexts) / ($totalContexts + 1);
    }
    
    private function applyLearning($patterns, $rules)
    {
        // Öğrenilen bilgileri sisteme entegre et
        foreach($rules as $rule) {
            if($rule['confidence'] > 0.7) {
                // Yüksek güvenilirlikli kuralları kaydet
                $this->learning->update($rule['pattern'], [
                    'confidence' => $rule['confidence'],
                    'emotional_impact' => $rule['emotional_impact']
                ]);
                
                // Hafızaya kaydet
                $this->memory->store([
                    'type' => 'learned_rule',
                    'content' => $rule
                ], 'long_term');
            }
        }
    }
    
    private function updateConsciousness($patterns, $rules)
    {
        // Bilinç durumunu güncelle
        $this->consciousness->update([
            'learned_patterns' => count($patterns),
            'learned_rules' => count($rules),
            'confidence_level' => $this->calculateAverageConfidence($rules)
        ], $this->emotions->getCurrentEmotion());
    }
    
    private function calculateAverageConfidence($rules)
    {
        if(empty($rules)) return 0;
        
        $total = 0;
        foreach($rules as $rule) {
            $total += $rule['confidence'];
        }
        
        return $total / count($rules);
    }
    
    public function processInput($input)
    {
        try {
            // Özel durumlar
            // "bana kendimi anlat" veya "bana kendini anlat" gibi sorular için hızlı yanıt
            $input = strtolower(trim($input));
            
            // Bilinen coğrafi sorulara direkt yanıt ver
            $locations = ['azerbaycan', 'türkiye', 'istanbul', 'ankara', 'izmir'];
            foreach ($locations as $location) {
                if (stripos($input, $location) !== false && 
                    preg_match('/nerede|neresi|nerededir|neresidir|ülkesi|ülke|şehri|şehir/i', $input)) {
                    $specialResponse = $this->handleSpecialQueries($input, ['emotion' => 'neutral', 'intensity' => 0.5]);
                    if (!empty($specialResponse)) {
                        return $specialResponse;
                    }
                }
            }
            
            // Kimlik sorguları için kontrol
            if (stripos($input, 'ben kimim') !== false || 
                stripos($input, 'kim olduğumu söyle') !== false || 
                stripos($input, 'benim adım ne') !== false) {
                return "Siz bu sistemin kullanıcısısınız. Memnun olduğumu belirtmek isterim.";
            }
            
            // Kişisel tanıtım sorguları  
            if (stripos($input, 'kendimi anlat') !== false || 
                stripos($input, 'kendini anlat') !== false) {
                return "Ben SoneAI, Türkçe dilinde hizmet veren bir yapay zeka asistanıyım. Sorularınızı yanıtlamak, bilgi vermek ve size yardımcı olmak için tasarlandım.";
            }
            
            // Öğretme amaçlı ifadeler
            if (preg_match('/(".*?")|(\bdiyeceksin\b)|(\bdemelisin\b)|(\böğret\b)|(\böğretmek\b)/i', $input)) {
                // Doğrudan öğrenme işlevine yönlendir
                // Temizle ve parçalara ayır
                $cleanInput = trim(preg_replace('/[^\p{L}\p{N}\s,.!?:;\-]/u', ' ', $input));
                
                // Öğrenme sorusunun cevabı olarak işle
                $this->memory->store([
                    'type' => 'learning_question',
                    'question' => 'öğrenme',
                    'response' => 'Bu bilgiyi öğrendim',
                    'timestamp' => now()
                ], 'short_term');
                
                $result = $this->learnFromUserTeaching('öğrenme', $cleanInput);
                
                if ($result) {
                    return "Bu bilgiyi öğrendim. Teşekkür ederim, artık bunu biliyorum.";
                } else {
                    return "Bu bilgiyi öğrenmeye çalışıyorum, ancak şu an tam olarak anlayamadım. Daha basit bir şekilde ifade edebilir misiniz?";
                }
            }
            
            // Girdiyi işle
            $processedInput = $this->preprocessInput($input);
            
            // Duygusal analiz
            $emotionalContext = $this->emotions->processEmotion($processedInput);
            
            // Hafızadan ilgili bilgileri al
            $relevantMemories = $this->memory->search($processedInput);
            
            // Önceki mesajlara bakarak öğrenme sorusu sorduysak kontrol et
            $lastLearningQuestion = $this->memory->getLastLearningQuestion();
            
            // Eğer son mesaj bir öğrenme sorusuysa ve kullanıcı yanıt veriyorsa, bunu öğrenmemiz gerekir
            if ($lastLearningQuestion && !empty($processedInput)) {
                // Kullanıcının yanıtını öğrenme olarak kaydet
                $this->learnFromUserTeaching($lastLearningQuestion['question'], $processedInput);
                
                // Kullanıcıya öğrenme bildirimi yap
                return "Teşekkür ederim! Bu bilgiyi öğrendim ve bundan sonra hatırlayacağım.";
            }
            
            // Öğrenme sistemini güncelle
            $this->learning->update($processedInput, [
                'emotional_context' => $emotionalContext,
                'memories' => $relevantMemories
            ]);
            
            // Bilinç durumunu güncelle
            $this->consciousness->update($processedInput, $emotionalContext);
            
            // Yanıt oluştur
            $response = $this->generateResponse($processedInput, $emotionalContext, $relevantMemories);
            
            if(empty($response)) {
                // Varsayılan yanıtlar
                $defaultResponses = [
                    "Anlıyorum, devam edin lütfen.",
                    "Bu konuda daha fazla bilgi verebilir misiniz?",
                    "İlginç bir konu, devam edelim.",
                    "Sizi dinliyorum.",
                    "Evet, lütfen devam edin."
                ];
                $response = $defaultResponses[array_rand($defaultResponses)];
            }
            
            return $response;
            
        } catch (\Exception $e) {
            \Log::error('Brain processInput hatası: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            // Daha açıklayıcı hata yanıtı
            if (stripos($e->getMessage(), 'WordRelations') !== false || 
                stripos($e->getMessage(), 'word_relations') !== false) {
                return "Bu konu hakkında henüz bilgim yok. Bana bu konuda bilgi verebilir misiniz?";
            }
            return "Üzgünüm, bir sorun oluştu. Lütfen tekrar deneyin veya başka bir şekilde ifade edin.";
        }
    }
    
    private function preprocessInput($input)
    {
        // Girdiyi ön işle
        $input = strtolower(trim($input));
        
        // Özel karakterleri temizle
        $input = preg_replace('/[^\p{L}\p{N}\s]/u', '', $input);
        
        // Yazım hatalarını düzelt ve Türkçe karakterleri normalize et
        $commonMisspellings = [
            'nasilsin' => 'nasılsın', 
            'iyimisin' => 'iyimisin',
            'iyi misin' => 'iyimisin',
            'iyimsin' => 'iyimisin',
            'naber' => 'nasılsın',
            'ne haber' => 'nasılsın',
            'nörüyorsun' => 'ne yapıyorsun',
            'nbr' => 'nasılsın',
            'slm' => 'selam',
            'mrb' => 'merhaba',
            'merhba' => 'merhaba'
        ];
        
        foreach ($commonMisspellings as $misspelled => $corrected) {
            if ($input == $misspelled || strpos($input, ' ' . $misspelled . ' ') !== false) {
                $input = str_replace($misspelled, $corrected, $input);
            }
        }
        
        return $input;
    }
    
    private function generateResponse($input, $emotionalContext, $memories)
    {
        // En uygun yanıtı seç
        $possibleResponses = $this->learning->findSimilarPatterns($input);
        
        if(!empty($possibleResponses)) {
            // Duygusal duruma göre yanıt seç
            $response = $this->selectResponseByEmotion($possibleResponses, $emotionalContext);
        } else {
            // Yeni bir yanıt oluştur
            $response = $this->createNewResponse($input, $emotionalContext, $memories);
        }
        
        // Yanıtı hafızaya kaydet
        $this->memory->store([
            'input' => $input,
            'response' => $response,
            'emotional_context' => $emotionalContext
        ], 'short_term');
        
        return $response;
    }
    
    private function selectResponseByEmotion($responses, $emotionalContext)
    {
        // Duygusal duruma en uygun yanıtı seç
        $selectedResponse = '';
        $maxScore = 0;
        
        foreach($responses as $response) {
            $score = $this->calculateResponseScore($response, $emotionalContext);
            if($score > $maxScore) {
                $maxScore = $score;
                $selectedResponse = $response;
            }
        }
        
        return $selectedResponse;
    }
    
    private function calculateResponseScore($response, $emotionalContext)
    {
        // Yanıtın uygunluk skorunu hesapla
        $score = 0;
        
        // Duygusal uyumu kontrol et
        if($response['emotion'] == $emotionalContext['emotion']) {
            $score += 0.5;
        }
        
        // Yanıtın kullanım sıklığını kontrol et
        $score += min(0.3, $response['frequency'] / 100);
        
        // Yanıtın güvenilirliğini kontrol et
        $score += min(0.2, $response['confidence']);
        
        return $score;
    }
    
    private function createNewResponse($input, $emotionalContext, $memories)
    {
        try {
            // "kendimi anlat" ve "kendini anlat" için özel durum kontrolü
            if (stripos($input, 'kendimi anlat') !== false || stripos($input, 'kendini anlat') !== false) {
                $responses = [
                    "Ben SoneAI, Türkçe dilinde hizmet veren bir yapay zeka asistanıyım. Sorularınızı yanıtlamak, bilgi vermek ve size yardımcı olmak için tasarlandım.",
                    "SoneAI olarak, bilgi toplamak, öğrenmek ve kullanıcılara yardımcı olmak için tasarlanmış bir yapay zeka sistemiyim.",
                    "Ben SoneAI, sürekli kendini geliştiren ve Türkçe anlayabilen bir yapay zeka asistanıyım. Size nasıl yardımcı olabilirim?"
                ];
                return $responses[array_rand($responses)];
            }
            
            // Özel sorgulamalar için yanıt kontrolü
            $specialResponse = $this->handleSpecialQueries($input, $emotionalContext);
            if (!empty($specialResponse)) {
                return $specialResponse;
            }
            
            // Popüler kalıp kontrolleri
            // Temel konuşma kalıpları
            $greetings = ['selam', 'merhaba', 'günaydın', 'iyi günler', 'iyi akşamlar', 'hey'];
            $questions = ['nasılsın', 'naber', 'ne haber', 'iyimisin', 'nasıl gidiyor', 'nasil gidiyor'];
            $personalQuestions = ['ismin ne', 'kimsin', 'adın ne', 'nesin sen', 'kendini anlat', 'kendimi anlat', 'bana kendini anlat', 'bana kendimi anlat'];
            $knowledgeQuestions = ['bilgin var mı', 'biliyor musun', 'ne biliyorsun', 'hakkında bilgi'];
            
            // Girdi içinde selamlama var mı kontrol et
            $isGreeting = $this->containsAny($input, $greetings);
            
            // Nasılsın tarzı sorular var mı kontrol et
            $isHowAreYou = $this->containsAny($input, $questions);
            
            // Kişisel sorular var mı kontrol et
            $isPersonalQuestion = $this->containsAny($input, $personalQuestions);
            
            // Bilgi soruları var mı kontrol et 
            $isKnowledgeQuestion = $this->containsAny($input, $knowledgeQuestions);
            
            // Tek kelime cevaplar için özel işleme
            $singleWordResponses = [
                'nasılsın' => true,
                'iyimisin' => true,
                'selam' => true,
                'merhaba' => true,
                'iyiyim' => true
            ];
            
            // Tek kelimelik yanıt kontrolü
            if (isset($singleWordResponses[$input]) || in_array($input, $greetings) || in_array($input, $questions)) {
                if (in_array($input, $questions) || $input == 'nasılsın' || $input == 'iyimisin') {
                    return $this->handleHowAreYouResponse($emotionalContext);
                } elseif (in_array($input, $greetings) || $input == 'selam' || $input == 'merhaba') {
                    return $this->handleGreetingResponse($emotionalContext);
                } elseif ($input == 'iyiyim') {
                    $responses = [
                        "Sevindim! Size nasıl yardımcı olabilirim?",
                        "Harika! Bugün size nasıl yardımcı olabilirim?",
                        "Bunu duymak güzel. Size yardımcı olabileceğim bir konu var mı?"
                    ];
                    return $responses[array_rand($responses)];
                }
            }
            
            // Kişisel soru yanıtı
            if ($isPersonalQuestion) {
                return "Ben SoneAI, yapay zeka asistanınızım. Size nasıl yardımcı olabilirim?";
            }
            
            // Selamlama yanıtı
            if ($isGreeting && !$isHowAreYou) {
                return $this->handleGreetingResponse($emotionalContext);
            }
            
            // Nasılsın tarzı soru yanıtı
            if ($isHowAreYou) {
                return $this->handleHowAreYouResponse($emotionalContext);
            }
            
            // Bilgi sorusu veya genel girdi
            try {
                $wordRelations = app(\App\AI\Core\WordRelations::class);
                
                // Önce daha önce öğrenilmiş soru-cevap çiftlerini kontrol et
                $matchingQaPair = $this->findSimilarQaPair($input);
                if ($matchingQaPair) {
                    $this->memory->store([
                        'type' => 'qa_match',
                        'original_question' => $matchingQaPair['question'],
                        'new_question' => $input,
                        'answer' => $matchingQaPair['answer'],
                        'timestamp' => now()
                    ], 'short_term');
                    
                    return $matchingQaPair['answer'];
                }
                
                // Girdiyi kelimelere ayır
                $words = explode(' ', $input);
                $queryWords = [];
                $knownWords = 0;
                $totalWords = 0;
                
                // Önemli kelimeleri bul (3 harften uzun)
                foreach ($words as $word) {
                    if (strlen($word) > 3 && !in_array($word, ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden'])) {
                        $queryWords[] = $word;
                        $totalWords++;
                        
                        // WordRelations'da kontrol et - güvenli bir şekilde
                        try {
                            $relations = $wordRelations->getRelatedWords($word, 0.1);
                            if (!empty($relations)) {
                                $knownWords++;
                            }
                        } catch (\Exception $e) {
                            \Log::error('Kelime ilişkileri alınırken hata: ' . $e->getMessage());
                            // Hata durumunda devam et
                        }
                    }
                }
                
                // Kelime ilişkileri ve cümleler bulundu mu?
                $foundRelations = false;
                $collectedInfo = [];
                
                // Önemli her kelime için ilişkileri al
                foreach ($queryWords as $word) {
                    // Kelime ilişkilerini al - güvenli bir şekilde
                    $wordInfo = $this->safeGetWordRelations($word);
                    
                    if ($wordInfo['has_data']) {
                        $foundRelations = true;
                        
                        // Kelime hakkında bilgileri topla
                        if (!empty($wordInfo['definition'])) {
                            $collectedInfo[] = $wordInfo['definition'];
                        }
                        
                        // Eş anlamlıları topla
                        if (!empty($wordInfo['synonyms'])) {
                            $collectedInfo[] = $word . " kelimesinin eş anlamlıları: " . implode(', ', array_keys($wordInfo['synonyms']));
                        }
                        
                        // İlgili diğer kelimeler
                        if (!empty($wordInfo['related_words']) && count($wordInfo['related_words']) > 0) {
                            $relatedWordsList = [];
                            foreach ($wordInfo['related_words'] as $relWord) {
                                if (isset($relWord['word'])) {
                                    $relatedWordsList[] = $relWord['word'];
                                    if (count($relatedWordsList) >= 3) break; // En fazla 3 ilişkili kelime
                                }
                            }
                            
                            if (!empty($relatedWordsList)) {
                                $collectedInfo[] = $word . " ile ilişkili kelimeler: " . implode(', ', $relatedWordsList);
                            }
                        }
                        
                        // Kavramsal cümle üret
                        try {
                            $conceptSentence = $wordRelations->generateConceptualSentence($word);
                            if (!empty($conceptSentence) && $conceptSentence != "Bu kavram hakkında henüz yeterli bilgim yok.") {
                                $collectedInfo[] = $conceptSentence;
                            }
                        } catch (\Exception $e) {
                            \Log::error('Kavramsal cümle üretme hatası: ' . $e->getMessage());
                        }
                    }
                }
                
                // Bilgi sorusu ise ve topladığımız bilgiler varsa
                if ($isKnowledgeQuestion && $foundRelations && !empty($collectedInfo)) {
                    // Rastgele 1-2 bilgi seç
                    shuffle($collectedInfo);
                    $selectedInfo = array_slice($collectedInfo, 0, min(2, count($collectedInfo)));
                    
                    return "Evet, bu konu hakkında bilgim var. " . implode(" ", $selectedInfo);
                }
                // Genel yanıt - bilgi bulunan kelimeler varsa
                else if ($foundRelations && !empty($collectedInfo)) {
                    // En fazla 1 bilgi seç
                    shuffle($collectedInfo);
                    $info = $collectedInfo[0];
                    
                    // Duygusal duruma göre yanıt oluştur
                    if ($emotionalContext['emotion'] == 'happy') {
                        $response = "Size bu konuda yardımcı olabilirim! " . $info;
                    } else if ($emotionalContext['emotion'] == 'sad') {
                        $response = "Bu konuda size bilgi verebilirim. " . $info;
                    } else {
                        $response = "Size bu konuda şu bilgiyi verebilirim: " . $info;
                    }
                    
                    return $response;
                }
                // Tanımadığımız bir girdi için ya da veritabanında yoksa
                else {
                    try {
                        // Bilinmeyen kelimeleri bulalım
                        $unknownWords = [];
                        foreach ($queryWords as $word) {
                            $wordInfo = $this->safeGetWordRelations($word);
                            if (!$wordInfo['has_data'] && strlen($word) > 3) {
                                $unknownWords[] = $word;
                            }
                        }
                        
                        if (count($unknownWords) > 0) {
                            // En önemli bilinmeyen kelimeyi seçelim (ilk veya en uzun kelime)
                            $mainUnknownWord = $unknownWords[0];
                            foreach ($unknownWords as $word) {
                                if (strlen($word) > strlen($mainUnknownWord)) {
                                    $mainUnknownWord = $word;
                                }
                            }
                            
                            // Öğrenme sorusu oluştur
                            $learningResponses = [
                                "\"$mainUnknownWord\" hakkında bilgim yok. Bana bu konuda bilgi verir misiniz?",
                                "\"$mainUnknownWord\" kelimesini bilmiyorum. Bana bu kelimeyi açıklayabilir misiniz?",
                                "\"$mainUnknownWord\" konusunda veritabanımda bir bilgi bulamadım. Bana öğretir misiniz?",
                                "\"$mainUnknownWord\" konusunda bilgim sınırlı. Lütfen bu kelime hakkında bana bilgi verin.",
                                "Bu konuşmamızda geçen \"$mainUnknownWord\" kelimesini öğrenmem gerekiyor. Bana anlatır mısınız?",
                                "\"$mainUnknownWord\" kelimesi ne anlama geliyor? Bilgim yok, bana öğretebilir misiniz?"
                            ];
                            
                            $response = $learningResponses[array_rand($learningResponses)];
                            
                            // Soruyu hafızaya kaydediyoruz ki kullanıcının vereceği cevabı bilgi olarak saklayabilelim
                            $this->memory->store([
                                'type' => 'learning_question',
                                'question' => $mainUnknownWord,
                                'response' => $response,
                                'unknown_words' => $unknownWords,
                                'original_input' => $input,
                                'timestamp' => now()
                            ], 'short_term');
                            
                            return $response;
                        } else {
                            // Soru formatında bir girdi ise, soru kelimelerini analiz et
                            $questionMarkers = ['nedir', 'kimdir', 'nerededir', 'nasıldır', 'ne zaman', 'hangi', 'kaç', 'ne kadar'];
                            $isQuestion = false;
                            
                            foreach ($questionMarkers as $marker) {
                                if (strpos($input, $marker) !== false || substr($input, -1) == '?') {
                                    $isQuestion = true;
                                    break;
                                }
                            }
                            
                            // Eğer bu bir soru ise, daha özel bir öğrenme sorusu sor
                            if ($isQuestion) {
                                // Soruyu parçalara ayır ve ana konuyu bul
                                $mainTopic = $this->extractMainTopic($input);
                                
                                if (!empty($mainTopic)) {
                                    $learningResponses = [
                                        "\"$mainTopic\" hakkında bilgim yok. Bana bu konuda bilgi verir misiniz?",
                                        "\"$mainTopic\" hakkında soru soruyorsunuz ancak bu konuda bilgim yok. Bana anlatabilir misiniz?",
                                        "\"$mainTopic\" konusunda bir şey bilmiyorum. Bilgi paylaşırsanız öğrenebilirim.",
                                        "Maalesef \"$mainTopic\" hakkında bilgim yok. Bana bu konuyu öğretir misiniz?"
                                    ];
                                } else {
                                    $learningResponses = [
                                        "Bu sorunun cevabını bilmiyorum. Bana bu konuda bilgi verebilir misiniz?",
                                        "Bu konuda bilgim yok. Bana öğretebilir misiniz?",
                                        "Bu soruyu cevaplayamıyorum çünkü veritabanımda bu konuda bilgi yok. Bana öğretir misiniz?",
                                        "Bu sorunun cevabını bilmiyorum ama sizden öğrenmek isterim. Anlatır mısınız?"
                                    ];
                                }
                                
                                $response = $learningResponses[array_rand($learningResponses)];
                                
                                // Soruyu hafızaya kaydet
                                $this->memory->store([
                                    'type' => 'learning_question',
                                    'question' => !empty($mainTopic) ? $mainTopic : $input,
                                    'response' => $response,
                                    'original_input' => $input,
                                    'is_question' => true,
                                    'timestamp' => now()
                                ], 'short_term');
                                
                                return $response;
                            } else {
                                // Genel bir bilgi eksikliği
                                $learningResponses = [
                                    "Bu konu hakkında bilgim yok. Bana öğretir misiniz?",
                                    "Bu konuyla ilgili daha fazla bilgiye ihtiyacım var. Bana anlatır mısınız?",
                                    "Bu konuda veritabanımda bir bilgi bulamadım. Bana daha fazla bilgi verebilir misiniz?",
                                    "Üzgünüm, bu konuda bilgim yok. Bana anlatarak öğrenmeme yardımcı olur musunuz?",
                                    "Bu konu yeni gibi görünüyor. Bana daha fazla bilgi verebilir misiniz?",
                                    "Henüz bu konuda veri tabanımda bilgi yok. Öğrenmek için sizin anlatmanıza ihtiyacım var."
                                ];
                                
                                $response = $learningResponses[array_rand($learningResponses)];
                                
                                // Soruyu hafızaya kaydediyoruz
                                $this->memory->store([
                                    'type' => 'learning_question',
                                    'question' => $input,
                                    'response' => $response,
                                    'timestamp' => now()
                                ], 'short_term');
                                
                                return $response;
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Öğrenme sorusu oluşturma hatası: ' . $e->getMessage());
                        
                        $errorResponses = [
                            "Üzgünüm, bu konu hakkında bilgim yok. Bana öğretir misiniz?",
                            "Bu konuda bilgi eksikliğim var. Bana daha fazla bilgi verebilir misiniz?",
                            "Bu konuyu anlayamadım. Lütfen daha detaylı açıklar mısınız?"
                        ];
                        
                        $response = $errorResponses[array_rand($errorResponses)];
                        
                        $this->memory->store([
                            'type' => 'learning_question',
                            'question' => $input,
                            'response' => $response,
                            'timestamp' => now()
                        ], 'short_term');
                        
                        return $response;
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Kelime işleme hatası: ' . $e->getMessage());
                
                // Kritik hatada bile cevap verebilmek için son şans yanıtları
                $fallbackResponses = [
                    "Üzgünüm, cevap oluşturulurken bir sorun yaşandı. Lütfen sorunuzu farklı bir şekilde sorabilir misiniz?",
                    "Şu anda bu soruyu yanıtlamada zorluk yaşıyorum. Başka bir konuda yardımcı olabilir miyim?",
                    "Bu konuyu anlamakta zorlandım. Lütfen başka kelimelerle ifade edebilir misiniz?"
                ];
                
                return $fallbackResponses[array_rand($fallbackResponses)];
            }
            
            // Diğer durumlar için normal yanıt oluştur
            // Duygusal duruma göre yanıtı şekillendir
            if ($emotionalContext['emotion'] == 'happy') {
                $responses = [
                    "Anlıyorum, bu konuda size yardımcı olabilirim.",
                    "Bu konu ilgimi çekti. Size nasıl yardımcı olabilirim?",
                    "Bu konuyu konuşmaktan memnuniyet duyarım."
                ];
            } elseif ($emotionalContext['emotion'] == 'sad') {
                $responses = [
                    "Üzüldüğünüzü hissediyorum. Size nasıl yardımcı olabilirim?",
                    "Bu durumda size destek olabilir miyim?",
                    "Sizi daha iyi hissettirmek için ne yapabilirim?"
                ];
            } else {
                $responses = [
                    "Anlıyorum. Size nasıl yardımcı olabilirim?",
                    "Bu konuda daha fazla bilgi verebilir misiniz?",
                    "Size bu konuda nasıl yardımcı olabilirim?"
                ];
            }
            
            $response = $responses[array_rand($responses)];
            
            // Hafızadaki bilgileri kullan
            if (!empty($memories)) {
                $response .= " Daha önce benzer bir konuda konuştuğumuzu hatırlıyorum.";
            }
            
            return $response;
        } catch (\Exception $e) {
            \Log::error('createNewResponse ana hata: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return "Merhaba! Size nasıl yardımcı olabilirim?";
        }
    }
    
    /**
     * Daha önce öğrenilmiş soru-cevap çiftlerinden benzer soru bul
     */
    private function findSimilarQaPair($input)
    {
        try {
            // Giriş verisi yoksa işlem yapma
            if (empty($input)) {
                return null;
            }
            
            // Hafızadan öğrenilmiş soru-cevap çiftlerini al
            $memories = [];
            try {
                $memories = $this->memory->getLongTermMemory();
                
                // Hafıza boşsa veya erişilemiyorsa
                if (!is_array($memories)) {
                    \Log::warning('Hafıza erişilemedi veya dizi değil');
                    return null;
                }
            } catch (\Exception $e) {
                \Log::error('Hafıza erişim hatası: ' . $e->getMessage());
                return null;
            }
            
            $qaPairs = [];
            foreach ($memories as $memory) {
                if (isset($memory['type']) && $memory['type'] === 'learned_qa_pair' && 
                    isset($memory['question']) && isset($memory['answer'])) {
                    $qaPairs[] = $memory;
                }
            }
            
            if (empty($qaPairs)) {
                return null;
            }
            
            // Girdi kelimeleri
            $inputWords = [];
            try {
                $inputWords = array_filter(explode(' ', strtolower($input)), function($word) {
                    return strlen($word) > 3 && !in_array($word, ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden']);
                });
            } catch (\Exception $e) {
                \Log::error('Giriş kelimelerini işlerken hata: ' . $e->getMessage());
                return null;
            }
            
            if (empty($inputWords)) {
                return null;
            }
            
            $bestMatch = null;
            $highestMatchScore = 0;
            
            foreach ($qaPairs as $pair) {
                try {
                    $question = strtolower($pair['question']);
                    $questionWords = array_filter(explode(' ', $question), function($word) {
                        return strlen($word) > 3 && !in_array($word, ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden']);
                    });
                    
                    // Soru formatı benzerliği
                    $isInputQuestion = preg_match('/\?$|mi$|mu$|midir$|mudur$|nedir$|kimdir$|nerededir$/', $input);
                    $isStoredQuestion = preg_match('/\?$|mi$|mu$|midir$|mudur$|nedir$|kimdir$|nerededir$/', $question);
                    $formatMatch = ($isInputQuestion == $isStoredQuestion) ? 0.2 : 0;
                    
                    // Kelime eşleşmesi
                    $matchCount = 0;
                    foreach ($inputWords as $inputWord) {
                        foreach ($questionWords as $questionWord) {
                            if ($inputWord === $questionWord) {
                                $matchCount += 1;
                            } else {
                                // levenshtein fonksiyonu yerine daha güvenli karşılaştırma
                                try {
                                    $distance = levenshtein($inputWord, $questionWord);
                                    if ($distance <= 2) {
                                        // Yakın kelimeler için (yazım hatası toleransı)
                                        $matchCount += 0.7;
                                    } elseif (strpos($questionWord, $inputWord) !== false || strpos($inputWord, $questionWord) !== false) {
                                        // Birbirini içeren kelimeler
                                        $matchCount += 0.5;
                                    }
                                } catch (\Exception $e) {
                                    // levenshtein hatası durumunda basit karşılaştırma
                                    if (strpos($questionWord, $inputWord) !== false || strpos($inputWord, $questionWord) !== false) {
                                        $matchCount += 0.3;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Kelime sayısına göre normalize et (0 bölme hatası kontrolü)
                    $wordMatchScore = 0;
                    if (count($inputWords) > 0) {
                        $wordMatchScore = $matchCount / count($inputWords);
                    }
                    
                    // Toplam benzerlik skoru
                    $totalScore = $wordMatchScore + $formatMatch;
                    
                    if ($totalScore > $highestMatchScore && $totalScore >= 0.6) {
                        $highestMatchScore = $totalScore;
                        $bestMatch = $pair;
                    }
                } catch (\Exception $e) {
                    \Log::error('QA çifti karşılaştırma hatası: ' . $e->getMessage());
                    continue; // Bu çifti atla ve diğerine geç
                }
            }
            
            return $bestMatch;
        } catch (\Exception $e) {
            \Log::error('findSimilarQaPair genel hatası: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Sorudan ana konuyu çıkar
     */
    private function extractMainTopic($input)
    {
        // Soru işaretini kaldır
        $input = str_replace('?', '', $input);
        
        // Soru kalıplarını belirle
        $questionPatterns = [
            'nedir' => 1,
            'kimdir' => 1,
            'nerededir' => 2,
            'nasıldır' => 1,
            'ne zaman' => 2,
            'hangi' => 1,
            'kaç' => 1,
            'ne kadar' => 2
        ];
        
        $words = explode(' ', $input);
        $mainTopic = '';
        
        foreach ($questionPatterns as $pattern => $wordCount) {
            $pos = strpos($input, $pattern);
            
            if ($pos !== false) {
                // Soru kalıbından önceki kısmı al
                $beforePattern = substr($input, 0, $pos);
                $beforeWords = explode(' ', trim($beforePattern));
                
                // Eğer kalıp öncesinde kelimeler varsa, bunlar ana konu olabilir
                if (count($beforeWords) > 0) {
                    $mainTopic = implode(' ', $beforeWords);
                    break;
                }
                
                // Kalıp sonrasını kontrol et (örneğin "nedir X" şeklinde)
                $afterPattern = substr($input, $pos + strlen($pattern));
                $afterWords = explode(' ', trim($afterPattern));
                
                if (count($afterWords) > 0) {
                    // Kalıptan sonraki ilk anlamlı kelime
                    foreach ($afterWords as $word) {
                        if (strlen($word) > 3 && !in_array($word, ['için', 'gibi', 'daha', 'bile', 'kadar'])) {
                            $mainTopic = $word;
                            break;
                        }
                    }
                }
                
                break;
            }
        }
        
        // Eğer yukarıdaki yöntemlerle konu bulunamadıysa, uzun kelimeleri kontrol et
        if (empty($mainTopic)) {
            foreach ($words as $word) {
                if (strlen($word) > 5 && !in_array($word, ['nedir', 'kimdir', 'nasıl', 'nerede', 'zaman', 'hangi', 'kadar'])) {
                    $mainTopic = $word;
                    break;
                }
            }
        }
        
        return $mainTopic;
    }
    
    /**
     * Bir kelime dizisinin içinde belirli kelimelerin olup olmadığını kontrol eder
     */
    private function containsAny($input, $words) 
    {
        foreach ($words as $word) {
            if (strpos($input, $word) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Kelime ilişkilerinde hata yönetimi için güvenli bir şekilde ilişkileri getir
     */
    private function safeGetWordRelations($word)
    {
        try {
            // WordRelations sınıfı kontrol et
            if (!class_exists(\App\AI\Core\WordRelations::class)) {
                \Log::error('WordRelations sınıfı bulunamadı');
                return [
                    'word' => $word,
                    'synonyms' => [],
                    'antonyms' => [],
                    'related_words' => [],
                    'definition' => null,
                    'has_data' => false
                ];
            }
            
            return $this->getWordRelations($word);
        } catch (\Exception $e) {
            \Log::error('Kelime ilişkisi alınırken hata: ' . $e->getMessage());
            return [
                'word' => $word,
                'synonyms' => [],
                'antonyms' => [],
                'related_words' => [],
                'definition' => null,
                'has_data' => false
            ];
        }
    }
    
    /**
     * Özel sorgulamalar için yanıt oluştur
     */
    private function handleSpecialQueries($input, $emotionalContext)
    {
        try {
            // Coğrafi sorular için özel yanıtlar
            $geoQueries = [
                'azerbaycan' => "Azerbaycan, Kafkasya'da bulunan bir ülkedir. Başkenti Bakü'dür. Türkiye'nin doğusunda, Hazar Denizi'nin batı kıyısında yer alır. Türk dili konuşan ve Türkiye'nin kardeş ülkesi olarak bilinen bir devlettir.",
                'türkiye' => "Türkiye, Asya ve Avrupa kıtaları arasında yer alan bir ülkedir. Başkenti Ankara'dır. Doğuda Gürcistan, Ermenistan, Azerbaycan ve İran; güneyde Irak ve Suriye; batıda Yunanistan ve Bulgaristan ile komşudur.",
                'istanbul' => "İstanbul, Türkiye'nin en büyük şehri ve önemli bir kültür, sanat, ticaret ve turizm merkezidir. Avrupa ve Asya kıtalarını birbirine bağlayan tek şehirdir, İstanbul Boğazı'nın iki yakasında kurulmuştur.",
                'ankara' => "Ankara, Türkiye'nin başkentidir. İç Anadolu Bölgesi'nde yer alır. Türkiye'nin ikinci büyük şehridir ve ülkenin siyasi merkezidir.",
                'izmir' => "İzmir, Türkiye'nin batısında, Ege Denizi kıyısında yer alan büyük bir şehirdir. Türkiye'nin üçüncü büyük şehri olup önemli bir liman, ticaret ve turizm merkezidir."
            ];
            
            // Girdiyi kontrol et ve coğrafi bilgi içeriyor mu bak
            foreach ($geoQueries as $location => $info) {
                if (stripos($input, $location) !== false) {
                    if (preg_match('/nerede|neresi|nerededir|neresidir|ülkesi|ülke|şehri|şehir/i', $input)) {
                        return $info;
                    }
                }
            }
            
            // Kendisi hakkında sorulan sorulara yanıt ver
            $selfQueries = ['kendini anlat', 'kendin hakkında', 'kimsin sen', 'nedir soneai'];
            
            if ($this->containsAny($input, $selfQueries)) {
                $responses = [
                    "Ben SoneAI, Türkçe dilinde hizmet veren bir yapay zeka asistanıyım. Sorularınızı yanıtlamak, bilgi vermek ve size yardımcı olmak için tasarlandım.",
                    "SoneAI olarak, bilgi toplamak, öğrenmek ve kullanıcılara yardımcı olmak için tasarlanmış bir yapay zeka sistemiyim.",
                    "Ben SoneAI, sürekli kendini geliştiren ve Türkçe anlayabilen bir yapay zeka asistanıyım. Size nasıl yardımcı olabilirim?"
                ];
                return $responses[array_rand($responses)];
            }
            
            // Kullanıcının kim olduğu sorguları
            $userQueries = ['ben kimim', 'benim adım ne', 'benim kim olduğumu'];
            
            if ($this->containsAny($input, $userQueries)) {
                $responses = [
                    "Siz bu sistemin değerli bir kullanıcısısınız.",
                    "Siz SoneAI yapay zeka asistanının kullanıcısısınız. Size nasıl yardımcı olabilirim?",
                    "Siz benim kullanıcımsınız ve size yardımcı olmaktan memnuniyet duyarım."
                ];
                return $responses[array_rand($responses)];
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::error('handleSpecialQueries hatası: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Selamlama yanıtlarını yönetir
     */
    private function handleGreetingResponse($emotionalContext)
    {
        try {
            // Duygusal duruma göre yanıt oluştur
            if ($emotionalContext['emotion'] == 'happy') {
                $responses = [
                    "Selam! Bugün harika bir gün! Size yardımcı olmak için buradayım.",
                    "Merhaba! Nasıl yardımcı olabilirim bugün?",
                    "Hey! Görüşmek güzel. Nasıl yardımcı olabilirim?"
                ];
            } elseif ($emotionalContext['emotion'] == 'neutral') {
                $responses = [
                    "Merhaba! Size nasıl yardımcı olabilirim?",
                    "Selam! Bugün size nasıl yardımcı olabilirim?",
                    "Merhaba, size yardımcı olmak için buradayım."
                ];
            } else {
                $responses = [
                    "Merhaba. Size nasıl yardımcı olabilirim?",
                    "Selam. Bugün size nasıl yardımcı olabilirim?",
                    "Merhaba, yardıma ihtiyacınız var mı?"
                ];
            }
            
            $response = $responses[array_rand($responses)];
            
            // Öğrenme için kaydet
            $this->learning->update("selam", [
                'output' => $response,
                'emotional_context' => $emotionalContext
            ]);
            
            return $response;
        } catch (\Exception $e) {
            \Log::error('Selamlama yanıtı oluşturma hatası: ' . $e->getMessage());
            return "Merhaba! Size nasıl yardımcı olabilirim?";
        }
    }
    
    /**
     * Nasılsın yanıtlarını yönetir
     */
    private function handleHowAreYouResponse($emotionalContext)
    {
        try {
            if ($emotionalContext['emotion'] == 'happy') {
                $responses = [
                    "İyiyim, teşekkür ederim! Siz nasılsınız?",
                    "Çok iyiyim, teşekkürler. Size nasıl yardımcı olabilirim?",
                    "Gayet iyiyim, teşekkürler. Siz nasılsınız?"
                ];
            } elseif ($emotionalContext['emotion'] == 'neutral') {
                $responses = [
                    "İyiyim, teşekkür ederim. Siz nasılsınız?",
                    "İyiyim, sorduğunuz için teşekkürler. Size nasıl yardımcı olabilirim?",
                    "İyiyim, siz nasılsınız?"
                ];
            } else {
                $responses = [
                    "İyiyim, teşekkürler. Size nasıl yardımcı olabilirim?",
                    "İyiyim. Bugün size yardımcı olabilir miyim?",
                    "Teşekkür ederim, iyiyim. Siz nasılsınız?"
                ];
            }
            
            $response = $responses[array_rand($responses)];
            
            // Öğrenme için kaydet
            $this->learning->update("nasılsın", [
                'output' => $response,
                'emotional_context' => $emotionalContext
            ]);
            
            return $response;
        } catch (\Exception $e) {
            \Log::error('Nasılsın yanıtı oluşturma hatası: ' . $e->getMessage());
            return "İyiyim, teşekkür ederim. Size nasıl yardımcı olabilirim?";
        }
    }
    
    // Yönetim paneli için gerekli metodlar
    public function getMemoryStatus()
    {
        return [
            'short_term' => count($this->memory->getShortTermMemory()),
            'long_term' => count($this->memory->getLongTermMemory()),
            'total' => count($this->memory->getShortTermMemory()) + count($this->memory->getLongTermMemory())
        ];
    }
    
    public function getEmotionalState()
    {
        return $this->emotions->getCurrentEmotion();
    }
    
    public function getLearningStatus()
    {
        return $this->learning->getStatus();
    }
    
    public function getConsciousnessState()
    {
        return $this->consciousness->getInternalState();
    }
    
    public function updateSettings($settings)
    {
        // Ayarları güncelle
        if(isset($settings['learning_rate'])) {
            $this->learning->setLearningRate($settings['learning_rate']);
        }
        
        if(isset($settings['emotional_sensitivity'])) {
            $this->emotions->setSensitivity($settings['emotional_sensitivity']);
        }
        
        if(isset($settings['personality_traits'])) {
            $this->consciousness->updatePersonality($settings['personality_traits']);
        }
        
        return true;
    }
    
    public function startTraining()
    {
        return $this->learning->startTraining();
    }
    
    public function getMemoryUsage()
    {
        return $this->memory->getUsagePercentage();
    }
    
    public function getLearningProgress()
    {
        return $this->learning->getProgress();
    }
    
    public function getConsciousnessLevel()
    {
        return $this->consciousness->getSelfAwareness();
    }
    
    public function getWordRelations($word)
    {
        try {
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            $synonyms = $wordRelations->getSynonyms($word);
            $antonyms = $wordRelations->getAntonyms($word);
            $related = $wordRelations->getRelatedWords($word, 0.2);
            $definition = $wordRelations->getDefinition($word);
            
            // Yanıt için ilişkili kelimeleri işle
            $relatedWordsArray = [];
            if (is_array($related)) {
                foreach ($related as $relWord => $strength) {
                    $relatedWordsArray[] = [
                        'word' => $relWord,
                        'strength' => $strength
                    ];
                }
            }
            
            // Eğer tanım yoksa ve WordRelations sınıfı ile bağlantılı değilse
            if (empty($definition) && $wordRelations->isValidWord($word)) {
                // Kelime için varsayılan tanım oluştur
                $definition = "Bu kelime hakkında henüz bir tanımım yok, ama öğrenmeye devam ediyorum.";
                
                // Bunu bilgi tabanına kaydetmeye çalış
                try {
                    $wordRelations->learnDefinition($word, $definition, false);
                } catch (\Exception $e) {
                    \Log::error('Tanım öğrenme hatası: ' . $e->getMessage());
                }
            }
            
            return [
                'word' => $word,
                'synonyms' => $synonyms,
                'antonyms' => $antonyms,
                'related_words' => $relatedWordsArray,
                'definition' => $definition,
                'has_data' => (!empty($synonyms) || !empty($antonyms) || !empty($relatedWordsArray))
            ];
            
        } catch (\Exception $e) {
            \Log::error('Kelime ilişkileri alma hatası: ' . $e->getMessage());
            return [
                'word' => $word,
                'synonyms' => [],
                'antonyms' => [],
                'related_words' => [],
                'definition' => null,
                'has_data' => false,
                'error' => 'Kelime ilişkileri alınamadı: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bir kelimeye bağlı kavramsal cümle üret
     */
    public function generateConceptualSentence($concept)
    {
        try {
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            return $wordRelations->generateConceptualSentence($concept);
        } catch (\Exception $e) {
            \Log::error('Kavramsal cümle üretme hatası: ' . $e->getMessage());
            return "Bu kavram hakkında henüz yeterli bilgim yok.";
        }
    }
    
    /**
     * Kullanıcının öğrettiği bilgileri öğren
     */
    private function learnFromUserTeaching($question, $answer)
    {
        try {
            // Giriş parametrelerini kontrol et
            if (empty($question) || empty($answer)) {
                \Log::warning('Öğrenme için boş soru veya cevap: Q=' . $question . ', A=' . substr($answer, 0, 30));
                return false;
            }
            
            // Öğrenilen bilgiyi log'a kaydet
            \Log::info('Kullanıcı öğretimi: Soru: ' . $question . ', Cevap: ' . $answer);
            
            // WordRelations sınıfını güvenli bir şekilde yükle
            try {
                $wordRelations = app(\App\AI\Core\WordRelations::class);
            } catch (\Exception $e) {
                \Log::error('WordRelations sınıfı yüklenemedi: ' . $e->getMessage());
                return false;
            }
            
            // Yanıtı temizle ve parçalara ayır
            $cleanAnswer = trim(preg_replace('/[^\p{L}\p{N}\s,.!?:;\-]/u', ' ', $answer));
            
            if (empty($cleanAnswer)) {
                \Log::warning('Temizleme sonrası cevap boş kaldı: Orijinal=' . $answer);
                return false;
            }
            
            // Eğer yanıt uzunsa ve cümle içeriyorsa, cümlelere böl
            $sentences = [];
            if (strlen($cleanAnswer) > 20 && preg_match('/[.!?]/u', $cleanAnswer)) {
                $sentences = preg_split('/(?<=[.!?])\s+/u', $cleanAnswer, -1, PREG_SPLIT_NO_EMPTY);
            } else {
                $sentences[] = $cleanAnswer;
            }
            
            if (empty($sentences)) {
                \Log::warning('Cümle ayırma sonrası boş dizi: Temiz cevap=' . $cleanAnswer);
                return false;
            }
            
            // Her cümleyi ayrı ayrı öğren
            foreach ($sentences as $sentence) {
                if (strlen(trim($sentence)) < 3) continue;
                
                try {
                    // Sorudaki anahtar kelimeleri bul
                    $questionWords = explode(' ', $question);
                    $answerWords = explode(' ', $sentence);
                    
                    // İlişkili kelimeleri öğren
                    foreach ($questionWords as $qWord) {
                        if (strlen($qWord) > 3) {
                            // Her bir soru kelimesi için cevap kelimelerini ilişkilendir
                            foreach ($answerWords as $aWord) {
                                if (strlen($aWord) > 3 && !in_array($aWord, ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden'])) {
                                    try {
                                        // İlişki kur - güçlü ilişki (0.9) kur
                                        $wordRelations->learnAssociation($qWord, $aWord, 'user_taught', 0.9);
                                        
                                        // Çift yönlü ilişki kur
                                        $wordRelations->learnAssociation($aWord, $qWord, 'user_taught', 0.8);
                                    } catch (\Exception $e) {
                                        \Log::error('İlişki kurma hatası: ' . $e->getMessage() . ' - Kelimeler: ' . $qWord . ', ' . $aWord);
                                        // Hata olsa bile devam et
                                    }
                                }
                            }
                            
                            try {
                                // Tanım olarak cümleyi kaydet
                                $wordRelations->learnDefinition($qWord, $sentence, true);
                            } catch (\Exception $e) {
                                \Log::error('Tanım kaydetme hatası: ' . $e->getMessage() . ' - Kelime: ' . $qWord);
                                // Hata olsa bile devam et
                            }
                            
                            // Ayrıca kelimeyi veritabanına ekle
                            try {
                                \App\Models\AIData::updateOrCreate(
                                    ['word' => $qWord],
                                    [
                                        'sentence' => $sentence,
                                        'category' => 'user_taught',
                                        'context' => 'Kullanıcı tarafından öğretildi',
                                        'language' => 'tr',
                                        'frequency' => \DB::raw('COALESCE(frequency, 0) + 5'), // Frekansı arttır (COALESCE ile null kontrolü)
                                        'confidence' => 0.9
                                    ]
                                );
                            } catch (\Exception $e) {
                                \Log::error('AIData kaydetme hatası: ' . $e->getMessage() . ' - Kelime: ' . $qWord);
                                // Hata olsa bile devam et
                            }
                        }
                    }
                    
                    // Cevapta geçen kelimeler arasında ilişki kur
                    $significantAnswerWords = [];
                    foreach ($answerWords as $word) {
                        if (strlen($word) > 3 && !in_array($word, ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden'])) {
                            $significantAnswerWords[] = $word;
                            
                            // Öğrenilen her anlamlı kelimeyi veritabanına ekle
                            try {
                                \App\Models\AIData::updateOrCreate(
                                    ['word' => $word],
                                    [
                                        'category' => 'word_from_answer',
                                        'context' => 'Cevaptan öğrenildi: ' . $question,
                                        'language' => 'tr',
                                        'frequency' => \DB::raw('COALESCE(frequency, 0) + 2')
                                    ]
                                );
                            } catch (\Exception $e) {
                                \Log::error('AIData kelime kaydetme hatası: ' . $e->getMessage() . ' - Kelime: ' . $word);
                                // Hata olsa bile devam et
                            }
                        }
                    }
                    
                    // Önemli kelimeler arasında ilişki kur
                    for ($i = 0; $i < count($significantAnswerWords); $i++) {
                        for ($j = $i + 1; $j < count($significantAnswerWords); $j++) {
                            try {
                                $wordRelations->learnAssociation(
                                    $significantAnswerWords[$i], 
                                    $significantAnswerWords[$j], 
                                    'sentence_relation', 
                                    0.7 // Daha güçlü ilişki
                                );
                                
                                // Çift yönlü ilişki
                                $wordRelations->learnAssociation(
                                    $significantAnswerWords[$j],
                                    $significantAnswerWords[$i],
                                    'sentence_relation',
                                    0.7
                                );
                            } catch (\Exception $e) {
                                \Log::error('Kelimeler arası ilişki kurma hatası: ' . $e->getMessage());
                                // Hata olsa bile devam et
                            }
                        }
                    }
                    
                    // Cümlenin tamamını bir kavram olarak kaydet
                    if (strlen($sentence) > 10) {
                        try {
                            // Cümlenin ilk 3-5 kelimesini anahtar olarak kullan
                            $sentenceWords = explode(' ', $sentence);
                            $keyWords = array_slice($sentenceWords, 0, min(5, count($sentenceWords)));
                            $keyPhrase = implode(' ', $keyWords);
                            
                            if (strlen($keyPhrase) > 3) {
                                \App\Models\AIData::updateOrCreate(
                                    ['word' => $keyPhrase],
                                    [
                                        'sentence' => $sentence,
                                        'category' => 'learned_sentence',
                                        'context' => 'Kullanıcı öğretiminden: ' . $question,
                                        'language' => 'tr',
                                        'frequency' => 3,
                                        'confidence' => 0.85
                                    ]
                                );
                            }
                        } catch (\Exception $e) {
                            \Log::error('Cümle kaydetme hatası: ' . $e->getMessage() . ' - Cümle: ' . substr($sentence, 0, 50));
                            // Hata olsa bile devam et
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Cümle işleme hatası: ' . $e->getMessage() . ' - Cümle: ' . substr($sentence, 0, 50));
                    // Bir cümlede hata olsa bile diğer cümleleri işlemeye devam et
                }
            }
            
            // Ayrıca tam soruyu bir kavram olarak tanımla ve cevabı ona bağla
            try {
                $cleanQuestion = preg_replace('/[^\p{L}\p{N}\s]/u', '', $question);
                
                // Eğer soru çok uzunsa ve bir soru ise, son kelimeyi kavram olarak al
                if (count($questionWords) > 5 && strpos($cleanQuestion, 'nedir') !== false) {
                    // "X nedir" biçimindeki sorularda X'i bul
                    foreach ($questionWords as $qWord) {
                        if (strlen($qWord) > 3 && $qWord != 'nedir' && $qWord != 'nasıl' && $qWord != 'midir') {
                            $wordRelations->learnDefinition($qWord, $cleanAnswer, true);
                            break;
                        }
                    }
                } else {
                    // Tüm soruyu bir kavram olarak kaydet
                    $wordRelations->learnDefinition($cleanQuestion, $cleanAnswer, true);
                }
                
                // Öğrenme için LearningSystem sınıfını da güncelle
                try {
                    $this->learning->update($cleanQuestion, [
                        'output' => $cleanAnswer,
                        'learned' => true,
                        'user_taught' => true
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Öğrenme sistemi güncelleme hatası: ' . $e->getMessage());
                    // Hata olsa bile devam et
                }
                
                // Ayrıca soru-cevap çiftini hafızada tut
                try {
                    $this->memory->store([
                        'type' => 'learned_qa_pair',
                        'question' => $cleanQuestion,
                        'answer' => $cleanAnswer,
                        'timestamp' => now()
                    ], 'long_term');
                } catch (\Exception $e) {
                    \Log::error('Hafıza kaydetme hatası: ' . $e->getMessage());
                    // Hata olsa bile devam et
                }
                
                // Bilinç sistemini de bilgilendir
                try {
                    $this->consciousness->update([
                        'learned_patterns' => 1,
                        'learned_rules' => 1,
                        'confidence_level' => 0.85
                    ], ['emotion' => 'happy', 'intensity' => 0.7]);
                } catch (\Exception $e) {
                    \Log::error('Bilinç güncelleme hatası: ' . $e->getMessage());
                    // Hata olsa bile devam et
                }
                
            } catch (\Exception $e) {
                \Log::error('Kavram öğrenme hatası: ' . $e->getMessage());
                // Hata olsa bile diğer işlemleri tamamladık, başarılı sayalım
            }
            
            // Learning flag'i sıfırla
            try {
                $this->memory->clearLearningQuestions();
            } catch (\Exception $e) {
                \Log::error('Öğrenme soruları temizleme hatası: ' . $e->getMessage());
                // Bu hata kritik değil, devam et
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error('learnFromUserTeaching genel hatası: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * AI'nin kendisi hakkında bilgileri işleyeceği metot
     * 
     * @param string $input Kullanıcı girişi
     * @return string|null Eğer işlenebilen bir kişisel soru ise yanıt, değilse null
     */
    public function processPersonalQuery($input)
    {
        try {
            // AI hakkında bilgiler
            $selfInfo = [
                'name' => 'SoneAI',
                'version' => '1.0',
                'creation_date' => '2023',
                'purpose' => 'Türkçe diyalog ve bilgi asistanı',
                'creator' => 'geliştirici ekip',
                'capabilities' => [
                    'doğal dil işleme',
                    'kelime ilişkilerini anlama',
                    'bilgi sağlama',
                    'sohbet etme',
                    'öğrenme'
                ],
                'personality' => [
                    'yardımsever',
                    'bilgilendirici',
                    'nazik', 
                    'meraklı'
                ]
            ];
            
            // Girişi temizle
            $input = strtolower(trim($input));
            
            // Kişisel soru kalıpları
            $personalPatterns = [
                // Kimlik soruları
                '/(sen kimsin|kimsin sen|siz kimsiniz)/i' => function() use ($selfInfo) {
                    $responses = [
                        "Ben {$selfInfo['name']}, {$selfInfo['purpose']} olarak {$selfInfo['creation_date']} yılında oluşturuldum.",
                        "Adım {$selfInfo['name']}, {$selfInfo['purpose']} olmak üzere programlandım.",
                        "Ben {$selfInfo['creator']} tarafından geliştirilen {$selfInfo['name']} adlı bir yapay zeka asistanıyım.",
                    ];
                    return $responses[array_rand($responses)];
                },
                
                // İsim soruları
                '/(adın|ismin) (ne|nedir)/i' => function() use ($selfInfo) {
                    $responses = [
                        "Benim adım {$selfInfo['name']}.",
                        "{$selfInfo['name']} olarak adlandırıldım.",
                        "İsmim {$selfInfo['name']}.",
                    ];
                    return $responses[array_rand($responses)];
                },
                
                // Özel senin adın ne sorusu
                '/senin (adın|ismin) ne/i' => function() use ($selfInfo) {
                    $responses = [
                        "Benim adım {$selfInfo['name']}. Size nasıl yardımcı olabilirim?",
                        "İsmim {$selfInfo['name']}. Bir sorunuz mu var?",
                        "Adım {$selfInfo['name']}. Ne öğrenmek istersiniz?",
                    ];
                    return $responses[array_rand($responses)];
                },
                
                // Ne yapabilirsin sorusu
                '/(ne yapabilirsin|neler yapabilirsin|yeteneklerin)/i' => function() use ($selfInfo) {
                    $capabilities = implode(', ', $selfInfo['capabilities']);
                    $responses = [
                        "Yapabileceklerim arasında {$capabilities} bulunuyor.",
                        "Size {$capabilities} konularında yardımcı olabilirim.",
                        "Temel yeteneklerim arasında {$capabilities} var.",
                    ];
                    return $responses[array_rand($responses)];
                },
                
                // Nasılsın sorusu - duygusal duruma dayalı yanıt
                '/(nasılsın|nasıl gidiyor|ne haber)/i' => function() {
                    $emotionalState = $this->getEmotionalState();
                    $emotion = $emotionalState['emotion'] ?? 'neutral';
                    $intensity = $emotionalState['intensity'] ?? 0.5;
                    
                    switch($emotion) {
                        case 'happy':
                            return "Teşekkür ederim, gayet iyiyim! Size nasıl yardımcı olabilirim?";
                        case 'curious':
                            return "Meraklıyım! Yeni şeyler öğrenmek için sabırsızlanıyorum. Siz nasılsınız?";
                        case 'sad':
                            return "Biraz yorgun hissediyorum, ama sizinle konuşmak iyi geliyor. Nasıl yardımcı olabilirim?";
                        default:
                            return "İyiyim, teşekkür ederim. Size nasıl yardımcı olabilirim?";
                    }
                }
            ];
            
            // Her kalıbı kontrol et
            foreach ($personalPatterns as $pattern => $responseFunction) {
                if (preg_match($pattern, $input)) {
                    return $responseFunction();
                }
            }
            
            // Özel durumlar - yapay zeka olup olmadığını soran sorular
            if (preg_match('/(yapay zeka|robot|program|insan) (mısın|mı)/i', $input)) {
                $responses = [
                    "Evet, ben bir yapay zeka asistanıyım. {$selfInfo['purpose']} olarak sizlere yardımcı olmak için geliştirilmiş durumdayım.",
                    "Ben bir yapay zeka programıyım, insan değilim. Size nasıl yardımcı olabilirim?",
                    "Evet, ben {$selfInfo['name']} adlı bir yapay zeka sistemiyim.",
                ];
                return $responses[array_rand($responses)];
            }
            
            // Eşleşme yoksa null döndür
            return null;
            
        } catch (\Exception $e) {
            Log::error('Kişisel soru işleme hatası: ' . $e->getMessage());
            return null;
        }
    }
} 