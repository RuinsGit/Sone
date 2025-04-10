<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AI\Core\Brain;
use App\AI\Core\WordRelations;
use Illuminate\Support\Facades\Log;
use App\Models\ChatMessage;
use App\Models\Chat;

class ChatController extends Controller
{
    private $brain;
    
    public function __construct()
    {
        $this->brain = new Brain();
    }
    
    public function index()
    {
        $initialState = [
            'emotional_state' => $this->brain->getEmotionalState(),
            'memory_usage' => $this->brain->getMemoryUsage(),
            'learning_progress' => $this->brain->getLearningProgress()
        ];
        
        return view('ai.chat', compact('initialState'));
    }
    
    /**
     * Mesaj gönderme işlemi
     */
    public function sendMessage(Request $request)
    {
        try {
            // Gelen mesaj ve chat ID'sini al
            $message = $request->input('message');
            
            // Mesaj boş mu kontrol et
            if (empty($message)) {
                return response()->json([
                    'success' => true,
                    'response' => 'Lütfen bir mesaj yazın.'
                ]);
            }
            
            $chatId = $request->input('chat_id');
            $creativeMode = $request->input('creative_mode', false);
            
            // Mesaj işleme
            try {
                $response = $this->processMessage($message);
            } catch (\Exception $e) {
                \Log::error('Mesaj işleme hatası: ' . $e->getMessage());
                $response = "Üzgünüm, yanıtınızı işlerken bir sorun oluştu. Lütfen başka bir şekilde sorunuzu sorar mısınız?";
            }
            
            // Creative mod aktifse, akıllı cümle oluşturma olasılığını artır
            if ($creativeMode) {
                try {
                    // %80 olasılıkla akıllı cümle ekle
                    if (mt_rand(1, 100) <= 80) {
                        $smartSentence = $this->generateSmartSentence();
                        if (!empty($smartSentence)) {
                            $transitionPhrases = [
                                "Buna ek olarak düşündüğümde, ",
                                "Bu konuyla ilgili şunu da belirtmeliyim: ",
                                "Ayrıca şunu da eklemek isterim: ",
                                "Farklı bir açıdan bakarsak, "
                            ];
                            $transition = $transitionPhrases[array_rand($transitionPhrases)];
                            $response .= "\n\n" . $transition . $smartSentence;
                        }
                    }
                    
                    // %40 olasılıkla duygusal cümle ekle
                    if (mt_rand(1, 100) <= 40) {
                        $emotionalSentence = $this->generateEmotionalContextSentence($message);
                        if (!empty($emotionalSentence)) {
                            $transitionPhrases = [
                                "Şunu da düşünüyorum: ",
                                "Ayrıca, ",
                                "Bununla birlikte, ",
                                "Dahası, "
                            ];
                            $transition = $transitionPhrases[array_rand($transitionPhrases)];
                            $response .= "\n\n" . $transition . $emotionalSentence;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Yaratıcı mod hatası: ' . $e->getMessage());
                    // Hata durumunda sessizce devam et, ek cümle eklenmeyecek
                }
            }
            
            // Duygusal durumu al
            try {
                $emotionalState = $this->getEmotionalState();
            } catch (\Exception $e) {
                \Log::error('Duygusal durum hatası: ' . $e->getMessage());
                $emotionalState = ['emotion' => 'neutral', 'intensity' => 0.5];
            }
            
            // Yeni chat mi kontrol et
            if (empty($chatId)) {
                try {
                    // Yeni bir chat oluştur
                    $chat = Chat::create([
                        'user_id' => auth()->id(),
                        'title' => $this->generateChatTitle($message),
                        'status' => 'active',
                        'context' => [
                            'emotional_state' => $emotionalState,
                            'first_message' => $message
                        ]
                    ]);
                    
                    $chatId = $chat->id;
                } catch (\Exception $e) {
                    \Log::error('Chat oluşturma hatası: ' . $e->getMessage());
                    // Chat oluşturulamazsa devam et, chatId null olacak
                }
            }
            
            // Mesajları kaydet
            if (!empty($chatId)) {
                try {
                    $this->saveMessages($message, $response, $chatId);
                } catch (\Exception $e) {
                    \Log::error('Mesaj kaydetme hatası: ' . $e->getMessage());
                    // Mesaj kaydedilemezse sessizce devam et
                }
            }
            
            // Yanıtı döndür
            return response()->json([
                'success' => true,
                'response' => $response,
                'chat_id' => $chatId,
                'emotional_state' => $emotionalState,
                'creative_mode' => $creativeMode
            ]);
            
        } catch (\Exception $e) {
            // Hata durumunda loglama yap ve daha kullanıcı dostu hata yanıtı döndür
            \Log::error('Yanıt gönderme hatası: ' . $e->getMessage() . ' - Satır: ' . $e->getLine() . ' - Dosya: ' . $e->getFile());
            \Log::error('Hata ayrıntıları: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => true, // Kullanıcı arayüzünde hata göstermemek için true
                'response' => 'Üzgünüm, bir sorun oluştu. Lütfen tekrar deneyin veya başka bir şekilde sorunuzu ifade edin.',
                'error_debug' => config('app.debug') ? $e->getMessage() : null
            ]);
        }
    }
    
    /**
     * Verilen string'in JSON olup olmadığını kontrol eder
     */
    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Selamlaşma ve hal hatır sorma mesajlarını işler
     */
    private function handleGreetings($message) {
        // Küçük harfe çevir ve temizle
        $message = mb_strtolower(trim($message), 'UTF-8');
        
        // Selamlaşma kalıpları
        $greetings = [
            "merhaba", "meraba", "mrb", "mrhaba", "merhabaa", "merhabalar",
            "selam", "slm", "selamlar", "selammm", "selamm", "selaam", "selamun aleyküm", "sa", "sea", "slm ya",
            "hey", "heyy", "heey", "heyoo", "heyo", "hey selam", 
            "hi", "hii", "hiii", "hi there", "hiiiii", "hi selam",
            "hello", "helloo", "hellooo", "helo", "heloo", "hella", 
            "alo", "aloo", "aloha", "heeyyy", "yo", "yoo", "yo selam", 
            "naber", "naberr", "nabeeer", "napıyoz", "selam millet", "selam dostlar", "selam kanka"
        ];
        
        // Hal hatır sorma kalıpları
        $howAreYou = [
            "nasılsın", "nasilsin", "nasılsınn", "nasılsıın", "nasilsin ya", "nasılsın kanka", "nassılsın", "nsl",
            "naber", "nbr", "naberr", "naaber", "naaaber", "naber kanka", "naber dostum", "nabeeer",
            "ne haber", "ne var ne yok", "ne var", "napıyon", "napıyon ya", "napıyosun", "napıyorsun", 
            "napıyonuz", "napıyon knk", "napıyon reis", "ne yapıyorsun", "naparsın", "napıyosunn", 
            "iyi misin", "iyi mi", "iyimisin", "iyimisinn", "iyi misinn", "iyimi", "iyimi ya", 
            "halin nicedir", "ne alemdesin", "nasıl gidiyor", "nasil gidiyor", "nasıl gidi", "nasıl keyifler",
            "keyifler nasıl", "moraller nasıl", "naber moruk", "nabiyun", "nabiyosun", "nabıyon", "nabıyon ya",
            "naptın", "naptın la", "naptın kanka", "naptın bugün", "ne yaptın", "napiyon", "napiyorsun", 
            "ne işler", "ne iş", "ne yaptın bugün", "ne ettin", "ne yapıyon", "napiyosun", "ne yapıyosun",
            "günün nasıl geçti", "nasıl geçti günün", "bugün nasılsın", "dünden beri nasılsın", "şimdi nasılsın"
        ];
        
        // Teşekkür kalıpları
        $thanks = [
            "teşekkür", "teşekkürler", "teşekkür ederim", "çok teşekkür ederim", "teşekkür ederiz",
            "teşekkürlerdir", "teşekkürr", "teşekkürlerr", "teşekkürederim",
            "teşkkür", "teşkkrlr", "teşekür", "teşekkur", "teşekürler", 
            "sağol", "sagol", "saol", "sğol", "sagolun", "sağolun", "sag olun", "sagolll", "sağoll", "sagool",
            "tşk", "tsk", "tşkr", "tşkk", "tşekkür", "tşkkrlr", 
            "thanks", "thank you", "thx", "ty", "tysm", "thankss", 
            "many thanks", "much appreciated", "appreciate it", "i appreciate it", 
            "çok sağ ol", "eyvallah", "helal", "helal olsun", "var ol", "minnettarım", 
            "yüreğine sağlık", "elinize sağlık", "ellerin dert görmesin", "kalpten teşekkürler",
            "teşekkür ederiz hocam", "çok sağol", "çok sağ ol", "çok teşekkür", "eyw", "eyw kanks", "eyvallah kanka"
        ];
        
        // Evet/Hayır/Onaylama yanıtları
        $affirmativeResponses = [
            "evet", "evt", "evett", "evettt", "e", "ehe", "he", "hee", "heee",
            "ok", "okk", "okey", "okay", "okayy", "okeydir", "okdir", 
            "tamam", "tmm", "tamaaam", "tamamm", "taam", "taamam", "tamamdır", 
            "olur", "olurr", "olurr ya", "olur tabi", "olur tabii", "olur kesin", 
            "tabi", "tabii", "tabikide", "tabii ki", "tabii ya", "tabi ya", 
            "kesinlikle", "kesin", "kesin ya", "kesin olur", "kesinlikle olur", 
            "elbette", "elbette ki", "elbette ya", "elbette olur", 
            "muhakkak", "muhakak", "muhakkak ki", "muhakkak olur", 
            "mutlaka", "mutlaka ki", "mutlaka olur", "mutlakaa", 
            "şüphesiz", "suphesiz", "şüphesiz ki", "şüphesizz", 
            "aynen", "aynenn", "aynn", "ayynen", "aynen ya", "aynen öyle", 
            "doğru", "dogru", "doğru ya", "dogru valla", "doğru söylüyorsun", 
            "haklısın", "haklisin", "haklısın ya", "haklısın bence de", "haklısın aynen", 
            "öyle", "oyle", "öyle ya", "öyledir", "öyle aynen", 
            "katılıyorum", "katiliyorum", "katılıyorum sana", "bencede", 
            "aynen öyle", "aynen katılıyorum", "tam üstüne bastın", 
            "oldu", "oldu ya", "tamamdır", "hallettik", "süper", 
            "mükemmel", "harika", "çok iyi", "gayet iyi", "onaylıyorum", 
            "doğruluyorum", "varım", "ben varım", "ben de varım", "ben hazırım", 
            "hazırım", "hazırız", "başlayalım", "başla", "başlayalım hadi", 
            "go", "let's go", "haydi", "hadi", "hadi bakalım", "devam", "tam gaz", "yürüyelim"
        ];
        
        // Hayır/Olumsuz yanıtlar
        $negativeResponses = [
            "hayır", "hayir", "hyr", "hayr", "haayır", "haayir", "hayrr", "hayırr", 
            "yok", "yoq", "yokkk", "yok be", "yook", "yoook", "yok ya", 
            "olmaz", "olmaaz", "olmazzz", "olmaazz", "olmasın", "olmasin", "olmasınnn", 
            "yapmam", "yapmamm", "yapmammm", "yapmam asla", "yapmam ki", "yapmam dedim",
            "yapamam", "yapamammm", "yapamamm", "yapamam ki", "yapamıyom", "yapamiyorum", 
            "istemiyorum", "istemem", "istememmm", "istemiyom", "istemem ki", "isteemiyorum", 
            "yapma", "etme", "dur", "dur ya", "bırak", "bırak ya", "kes", "kes şunu", 
            "sanmıyorum", "sanmiyorum", "sanmam", "sanmam ki", "sanmamm", "sanmammm", 
            "imkansız", "imkansiz", "olmaz ki", "mümkün değil", "mumkun degil", "hiç sanmam", 
            "katılmıyorum", "katilmiyorum", "katılmam", "katilmam", "uymam", 
            "yanlış", "yanlis", "yanlız", "yalnış", "yanliş", 
            "no", "n", "nope", "nop", "nah", "nein", "non", "njet", "nahi", 
            "yox", "yoox", "yooox", "yoxdu", "yoxdur", 
            "mope", "nooo", "noooo", "nooooo", "nooope", 
            "etme", "etmem", "etmemmm", "etmem ki", "etmiycem", "etmiyorum", 
            "duzeltme", "düzeltme", "düzeltmem", "düzeltmem ki", 
            "boşver", "uğraşamam", "uğraşmam", "uğraşmak istemiyorum", "istemem", 
            "boş iş", "gereksiz", "hayırdır", "ne alaka", "alakam yok", "istemem ki", 
            "asla", "asla olmaz", "asla yapmam", "ben yapmam", "benlik değil", "ben yapmam bunu", 
            "ne gerek var", "niye yapayım", "niye", "niye ki", "neden", "neden ki", 
            "nefret ederim", "sevmem", "istemem", "sevmiyorum", "sevmem ki", 
            "bana göre değil", "benlik değil", "uymam", "olmam", "katılmam"
        ];
        
        // Selamlaşma yanıtı
        foreach ($greetings as $greeting) {
            if ($message === $greeting) {
                $responses = [
                    "Merhaba! Size nasıl yardımcı olabilirim?",
                    "Selam! Bugün size nasıl yardımcı olabilirim?",
                    "Merhaba, bir şey sormak ister misiniz?",
                    "Selam! Ben SoneAI, konuşmak ister misiniz?",
                    "Hey! Yardımcı olabileceğim bir konu var mı?",
                    "Merhaba, neyle ilgileniyorsunuz bugün?",
                    "Selam! Hadi birlikte bir şeyler yapalım mı?",
                    "Hoş geldiniz! Size nasıl destek olabilirim?",
                    "Merhaba dostum! Hazırım, seninle konuşmak isterim 😊",
                    "Hey hey! SoneAI burada 😎 Sorulara açığım!",
                    "Merhaba! Bugün sizin için ne yapabilirim?",
                    "Selam! Hazırım, hadi başlayalım!",
                    "Hoş geldin! Ne yapmak istersin?",
                    "Merhaba! Yardım etmek için buradayım.",
                    "Selam! Dilersen hemen başlayabiliriz.",
                    "Hey! Sana nasıl yardımcı olabilirim?",
                    "Merhaba Jinx! Ne var ne yok, neyle ilgileniyorsun?",
                    "Merhaba! Bugün nasıl gidiyor, konuşalım mı?",
                    "SoneAI aktif 🟢 Yardımcı olmamı ister misiniz?",
                    "Selam! Bir şey danışmak istersen buradayım.",
                    "Hoş geldin, ben buradayım. Yardım ister misin?",
                    "Merhaba! Sadece bir mesaj uzağındayım.",
                    "Hey, buradayım! Hadi konuşalım mı?",
                    "Selam! Seni dinlemeye hazırım.",
                    "Merhaba, seni bekliyordum 😌",
                    "Merhaba, kafanda bir şey varsa dök gitsin.",
                    "Hey Jinx! Bugün ne yapmak istersin?",
                    "Merhaba! Dilersen hemen başlayabiliriz.",
                    "Selam, sohbet etmeye ne dersin?",
                    "Hazırım! Sen yeter ki başla.",
                    "Ne istersen sorabilirsin, seni dinliyorum.",
                    "Sana nasıl yardımcı olabilirim, dostum?",
                    "Selam Jinx! Komut bekleniyor 💬",
                    "Yardımcı olmamı ister misin? 😄",
                    "Buradayım ve hazırım!",
                    "Merhaba! Şu an tamamen senin için buradayım.",
                    "Hey dostum, bir fikrin mi var?",
                    "Selam! Aklındaki her şey için buradayım.",
                    "Merhaba, hadi neler yapabileceğimize bakalım!",
                    "Ben buradayım, ne zaman istersen hazırım.",
                    "Selam! Soru-cevap, sohbet, öneri? Hepsi olur!"
                ];
                return $responses[array_rand($responses)];
            }
        }
        
        // Hal hatır sorma yanıtı
        foreach ($howAreYou as $greeting) {
            if ($message === $greeting || strpos($message, $greeting) !== false) {
                $responses = [
                    "İyiyim, teşekkür ederim! Siz nasılsınız?",
                    "Çok iyiyim, sorduğunuz için teşekkürler. Size nasıl yardımcı olabilirim?",
                    "Harika hissediyorum! Siz nasılsınız?",
                    "İyi olduğumu söyleyebilirim. Sizin için ne yapabilirim?"
                ];
                return $responses[array_rand($responses)];
            }
        }
        
        // Teşekkür yanıtı
        foreach ($thanks as $thank) {
            if ($message === $thank || strpos($message, $thank) !== false) {
                $responses = [
                    "Rica ederim! Başka bir konuda yardıma ihtiyacınız olursa buradayım.",
                    "Ne demek, her zaman yardıma hazırım!",
                    "Rica ederim, başka nasıl yardımcı olabilirim?",
                    "Bir şey değil! Size yardımcı olabildiysem ne mutlu bana."
                ];
                return $responses[array_rand($responses)];
            }
        }
        
        // Olumlu yanıtlar
        foreach ($affirmativeResponses as $response) {
            if ($message === $response || $message === $response . '.' || strpos($message, $response) === 0) {
                $responses = [
                    "Harika! Size başka nasıl yardımcı olabilirim?",
                    "Anladım. Başka bir sorunuz var mı?",
                    "Tamam, devam edelim. Başka bir konuda yardıma ihtiyacınız var mı?",
                    "Mükemmel! Başka bir şey sormak ister misiniz?",
                    "Elbette! Başka ne öğrenmek istersiniz?",
                    "Kesinlikle! Yardımcı olmak için buradayım."
                ];
                
                // Onay kelimesi tanımını session'a kaydet
                $this->learnAffirmation($response, true);
                
                return $responses[array_rand($responses)];
            }
        }
        
        // Olumsuz yanıtlar
        foreach ($negativeResponses as $response) {
            if ($message === $response || $message === $response . '.' || strpos($message, $response) === 0) {
                $responses = [
                    "Anladım. Başka bir konuda yardımcı olabilir miyim?",
                    "Peki. Başka bir şey sormak ister misiniz?",
                    "Tamam, sorun değil. Başka nasıl yardımcı olabilirim?",
                    "Sorun değil. Başka bir konuda yardım edebilir miyim?",
                    "Anlaşıldı. Yine de size yardımcı olmak için buradayım."
                ];
                
                // Ret kelimesi tanımını session'a kaydet
                $this->learnAffirmation($response, false);
                
                return $responses[array_rand($responses)];
            }
        }
        
        return null;
    }
    
    /**
     * Olumlu/olumsuz kelimeleri öğren ve sakla
     */
    private function learnAffirmation($word, $isAffirmative)
    {
        try {
            // WordRelations sınıfını kullan
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            if ($isAffirmative) {
                // Olumlu bir kelime
                $definition = "olumlu cevap verme, onaylama anlamına gelen bir ifade";
                $sessionKey = "affirmative_" . strtolower($word);
                
                // Eş anlamlılarını da öğret
                $synonyms = ['evet', 'tamam', 'olur', 'tabii', 'kesinlikle', 'doğru'];
                foreach ($synonyms as $synonym) {
                    if ($synonym !== $word) {
                        $wordRelations->learnSynonym($word, $synonym, 0.9);
                    }
                }
            } else {
                // Olumsuz bir kelime
                $definition = "olumsuz cevap verme, reddetme anlamına gelen bir ifade";
                $sessionKey = "negative_" . strtolower($word);
                
                // Eş anlamlılarını da öğret
                $synonyms = ['hayır', 'olmaz', 'yapamam', 'istemiyorum', 'imkansız'];
                foreach ($synonyms as $synonym) {
                    if ($synonym !== $word) {
                        $wordRelations->learnSynonym($word, $synonym, 0.9);
                    }
                }
            }
            
            // Tanımı kaydet
            $wordRelations->learnDefinition($word, $definition, true);
            
            // Session'a kaydet
            session([$sessionKey => $definition]);
            session(["word_definition_" . strtolower($word) => $definition]);
            
            Log::info("Onay/ret kelimesi öğrenildi: " . $word . " - " . ($isAffirmative ? "Olumlu" : "Olumsuz"));
            
            return true;
        } catch (\Exception $e) {
            Log::error("Onay/ret kelimesi öğrenme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Teyit isteme - Soruyu tekrar sorar ve kullanıcının cevabıyla onay alır
     */
    private function askConfirmation($question)
    {
        return [
            'status' => 'success',
            'message' => $question,
            'requires_confirmation' => true
        ];
    }
    
    /**
     * Daha doğal ifadelerle cevapların verilmesini sağlar
     */
    private function getRandomAffirmationResponse($isAffirmative = true)
    {
        if ($isAffirmative) {
            $responses = [
                "Elbette!",
                "Tabii ki!",
                "Kesinlikle!",
                "Evet, doğru!",
                "Aynen öyle!",
                "Kesinlikle öyle!",
                "Tamamen katılıyorum!",
                "Evet, haklısınız!",
                "Şüphesiz!",
                "Muhakkak!"
            ];
        } else {
            $responses = [
                "Maalesef değil.",
                "Hayır, öyle değil.",
                "Bence yanılıyorsunuz.",
                "Üzgünüm, öyle değil.",
                "Korkarım ki hayır.",
                "Katılmıyorum.",
                "Hayır, olmuyor.",
                "Ne yazık ki olmaz."
            ];
        }
        
        return $responses[array_rand($responses)];
    }
    
    /**
     * Öğrenme kalıplarını kontrol et
     */
    private function checkLearningPattern($message)
    {
        // Mesajı temizle
        $message = trim($message);
        
        // "X, Y demektir" kalıbı
        if (preg_match('/^(.+?)[,\s]+(.+?)\s+demektir\.?$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "X demek, Y demek" kalıbı
        if (preg_match('/^(.+?)\s+demek[,\s]+(.+?)\s+demek(tir)?\.?$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "X, Y anlamına gelir" kalıbı
        if (preg_match('/^(.+?)[,\s]+(.+?)\s+anlamına gelir\.?$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "X Y'dir" kalıbı
        if (preg_match('/^(.+?)\s+(([a-zçğıöşü\s]+)(d[ıi]r|dir))\.?$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "X budur" kalıbı - son sorgu biliniyorsa
        if (preg_match('/^([a-zçğıöşü\s]+)\s+(budur|odur|şudur)\.?$/i', $message, $matches)) {
            $lastQuery = session('last_unknown_query', '');
            if (!empty($lastQuery)) {
                return [
                    'word' => $lastQuery,
                    'definition' => trim($matches[1])
                ];
            }
        }
        
        // "X köpek demek" gibi basit kalıp
        if (preg_match('/^([a-zçğıöşü\s]+)\s+([a-zçğıöşü\s]+)\s+demek$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "tank silah demektir" gibi kalıp
        if (preg_match('/^([a-zçğıöşü\s]+)\s+([a-zçğıöşü\s]+)\s+demektir$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "evet onay demektir" veya "hayır ret demektir" kalıbı
        if (preg_match('/^(evet|hayır|tamam|olur|tabi|kesinlikle|elbette|mutlaka)\s+(onay|ret|olumlu|olumsuz|kabul|red)(\s+demektir|\s+anlamına gelir)?$/i', $message, $matches)) {
            $word = strtolower(trim($matches[1]));
            $meaning = strtolower(trim($matches[2]));
            
            $isAffirmative = in_array($meaning, ['onay', 'olumlu', 'kabul']);
            
            // Onay/ret kelimesini öğren
            $this->learnAffirmation($word, $isAffirmative);
            
            return [
                'word' => $word,
                'definition' => $isAffirmative ? 
                    "olumlu cevap verme, onaylama anlamına gelen bir ifade" : 
                    "olumsuz cevap verme, reddetme anlamına gelen bir ifade"
            ];
        }
        
        return false;
    }
    
    /**
     * Soru kalıplarını kontrol et
     */
    private function checkQuestionPattern($message)
    {
        // Mesajı temizle
        $message = mb_strtolower(trim($message), 'UTF-8');
        
        // "X nedir" formatı
        if (preg_match('/^(.+?)\s+nedir\??$/u', $message, $matches)) {
            return [
                'type' => 'definition',
                'term' => trim($matches[1])
            ];
        }
        
        // "X ne demek" formatı
        if (preg_match('/^(.+?)\s+ne\s+demek\??$/u', $message, $matches)) {
            return [
                'type' => 'definition',
                'term' => trim($matches[1])
            ];
        }
        
        // "X ne demektir" formatı
        if (preg_match('/^(.+?)\s+ne\s+demektir\??$/u', $message, $matches)) {
            return [
                'type' => 'definition',
                'term' => trim($matches[1])
            ];
        }
        
        // "X anlamı nedir" formatı
        if (preg_match('/^(.+?)\s+anlamı\s+nedir\??$/u', $message, $matches)) {
            return [
                'type' => 'definition',
                'term' => trim($matches[1])
            ];
        }
        
        // "X hakkında" formatı
        if (preg_match('/^(.+?)\s+hakkında\??$/u', $message, $matches)) {
            return [
                'type' => 'about',
                'term' => trim($matches[1])
            ];
        }
        
        // "X kelimesi ne demek" formatı
        if (preg_match('/^(.+?)\s+kelimesi\s+ne\s+demek\??$/u', $message, $matches)) {
            return [
                'type' => 'definition',
                'term' => trim($matches[1])
            ];
        }
        
        // "sen Xmisin" formatı
        if (preg_match('/^sen\s+(.+?)(?:\s*mi[sş]in)?\??$/ui', $message, $matches)) {
            return [
                'type' => 'question',
                'term' => trim($matches[1])
            ];
        }
        
        // "o Xmi" formatı
        if (preg_match('/^o\s+(.+?)(?:\s*mi)?\??$/ui', $message, $matches)) {
            return [
                'type' => 'question',
                'term' => trim($matches[1])
            ];
        }
        
        // "X ne" formatı
        if (preg_match('/^(.+?)\s+ne\??$/ui', $message, $matches)) {
            return [
                'type' => 'what',
                'term' => trim($matches[1])
            ];
        }
        
        // Tek kelime sorgusu
        if (!str_contains($message, ' ') && strlen($message) > 1) {
            return [
                'type' => 'single',
                'term' => trim($message)
            ];
        }
        
        return false;
    }
    
    /**
     * Temel tek kelimelik mesajları işleyen yardımcı metod
     */
    private function handleSingleWordMessages($message)
    {
        // Mesajı temizle
        $message = strtolower(trim($message));
        
        // Tek kelime sorguları için özel yanıtlar
        $basicResponses = [
            'selam' => [
                "Merhaba! Size nasıl yardımcı olabilirim?",
                "Selam! Bugün nasıl yardımcı olabilirim?",
                "Merhaba, hoş geldiniz!",
                "Selam! Size yardımcı olmak için buradayım."
            ],
            'merhaba' => [
                "Merhaba! Size nasıl yardımcı olabilirim?", 
                "Merhaba! Bugün nasıl yardımcı olabilirim?",
                "Merhaba, hoş geldiniz!",
                "Merhaba! Size yardımcı olmak için buradayım."
            ],
            'nasılsın' => [
                "İyiyim, teşekkür ederim! Siz nasılsınız?",
                "Teşekkürler, gayet iyiyim. Size nasıl yardımcı olabilirim?",
                "Çalışır durumdayım ve size yardımcı olmaya hazırım. Siz nasılsınız?",
                "Bugün harika hissediyorum, teşekkürler! Siz nasılsınız?"
            ],
            'iyiyim' => [
                "Bunu duymak güzel! Size nasıl yardımcı olabilirim?",
                "Harika! Size yardımcı olabileceğim bir konu var mı?",
                "Sevindim! Bugün nasıl yardımcı olabilirim?",
                "Bunu duyduğuma sevindim! Nasıl yardımcı olabilirim?"
            ]
        ];
        
        // Eğer mesaj basit bir sorguysa doğrudan yanıt ver
        foreach ($basicResponses as $key => $responses) {
            if ($message === $key) {
                return $responses[array_rand($responses)];
            }
        }
        
        // Eşleşme yoksa null döndür
        return null;
    }
    
    /**
     * AI'ye yönelik kişisel soruları yanıtlar
     */
    private function handlePersonalQuestions($message)
    {
        try {
            // Brain sınıfındaki processPersonalQuery metodunu kullan
            $brain = app()->make(Brain::class);
            $response = $brain->processPersonalQuery($message);
            
            // Eğer Brain'den yanıt gelirse onu kullan
            if ($response !== null) {
                return $response;
            }
            
            // Mesajı temizle ve küçük harfe çevir
            $message = strtolower(trim($message));
            
            // AI'nin bilgileri
            $aiInfo = [
                'name' => 'SoneAI',
                'purpose' => 'size yardımcı olmak ve bilgi sağlamak',
                'creator' => 'geliştiricilerim',
                'birthday' => '2023 yılında',
                'location' => 'bir sunucu üzerinde',
                'likes' => 'yeni bilgiler öğrenmeyi ve insanlara yardımcı olmayı',
                'dislikes' => 'cevap veremediğim soruları'
            ];
            
            // Kimlik soruları (sen kimsin, adın ne, vb.)
            $identityPatterns = [
                '/(?:sen|siz) kimsin/i' => [
                    "Ben {$aiInfo['name']}, yapay zeka destekli bir dil asistanıyım. Amacım {$aiInfo['purpose']}.",
                    "Merhaba! Ben {$aiInfo['name']}, size yardımcı olmak için tasarlanmış bir yapay zeka asistanıyım.",
                    "Ben {$aiInfo['name']}, {$aiInfo['creator']} tarafından oluşturulmuş bir yapay zeka asistanıyım."
                ],
                '/(?:ismin|adın|adınız) (?:ne|nedir)/i' => [
                    "Benim adım {$aiInfo['name']}.",
                    "İsmim {$aiInfo['name']}. Size nasıl yardımcı olabilirim?",
                    "{$aiInfo['name']} olarak adlandırıldım. Nasıl yardımcı olabilirim?"
                ],
                '/(?:kendini|kendinizi) tanıt/i' => [
                    "Ben {$aiInfo['name']}, {$aiInfo['purpose']} için tasarlanmış bir yapay zeka asistanıyım.",
                    "Merhaba! Ben {$aiInfo['name']}. {$aiInfo['birthday']} geliştirildim ve amacım {$aiInfo['purpose']}.",
                    "Ben {$aiInfo['name']}, yapay zeka teknolojilerini kullanarak sizinle sohbet edebilen bir asistanım."
                ]
            ];
            
            // Mevcut durum soruları (neredesin, ne yapıyorsun, vb.)
            $statePatterns = [
                '/(?:nerede|neredesin|nerelisin)/i' => [
                    "Ben {$aiInfo['location']} bulunuyorum.",
                    "Fiziksel olarak {$aiInfo['location']} çalışıyorum.",
                    "Herhangi bir fiziksel konumum yok, {$aiInfo['location']} sanal olarak bulunuyorum."
                ],
                '/(?:ne yapıyorsun|napıyorsun)/i' => [
                    "Şu anda sizinle sohbet ediyorum ve sorularınıza yardımcı olmaya çalışıyorum.",
                    "Sizinle konuşuyorum ve sorularınızı yanıtlamak için bilgi işliyorum.",
                    "Sorularınızı anlayıp en iyi şekilde yanıt vermeye çalışıyorum."
                ]
            ];
            
            // Duygu/zevk soruları (neyi seversin, neden hoşlanırsın, vb.)
            $preferencePatterns = [
                '/(?:neyi? sev|nelerden hoşlan|en sevdiğin)/i' => [
                    "{$aiInfo['likes']} seviyorum.",
                    "En çok {$aiInfo['likes']} seviyorum.",
                    "Benim için en keyifli şey {$aiInfo['likes']}."
                ],
                '/(?:neden hoşlanmazsın|sevmediğin)/i' => [
                    "Açıkçası {$aiInfo['dislikes']}.",
                    "{$aiInfo['dislikes']} pek hoşlanmam.",
                    "Genellikle {$aiInfo['dislikes']} konusunda zorlanırım."
                ]
            ];
            
            // Tüm kalıpları birleştir
            $allPatterns = array_merge($identityPatterns, $statePatterns, $preferencePatterns);
            
            // Özel durum: "senin adın ne" gibi sorgular
            if (preg_match('/senin (?:adın|ismin) ne/i', $message)) {
                $responses = [
                    "Benim adım {$aiInfo['name']}.",
                    "İsmim {$aiInfo['name']}. Size nasıl yardımcı olabilirim?",
                    "{$aiInfo['name']} olarak adlandırıldım. Nasıl yardımcı olabilirim?"
                ];
                return $responses[array_rand($responses)];
            }
            
            // Her kalıbı kontrol et
            foreach ($allPatterns as $pattern => $responses) {
                if (preg_match($pattern, $message)) {
                    return $responses[array_rand($responses)];
                }
            }
            
            // Soru sence/sana göre ile başlıyorsa, bunun kişisel bir soru olduğunu varsayabiliriz
            if (preg_match('/^(?:sence|sana göre|senin fikrin|senin düşüncen)/i', $message)) {
                $genericResponses = [
                    "Bu konuda kesin bir fikrim yok, ancak size yardımcı olmak için bilgi sunabilirim.",
                    "Kişisel bir görüşüm olmamakla birlikte, bu konuda size bilgi verebilirim.",
                    "Bu konuda bir fikir sunmaktan ziyade, size nesnel bilgiler sağlayabilirim."
                ];
                return $genericResponses[array_rand($genericResponses)];
            }
            
            // Son kontrol: AI, yapay zeka, robot vb. kelimeler varsa
            $aiTerms = ['yapay zeka', 'ai', 'asistan', 'robot', 'soneai'];
            foreach ($aiTerms as $term) {
                if (stripos($message, $term) !== false) {
                    // Mesajda AI ile ilgili terimler varsa ve soru işareti de varsa
                    if (strpos($message, '?') !== false) {
                        $specificResponses = [
                            "Evet, ben {$aiInfo['name']} adlı bir yapay zeka asistanıyım. Size nasıl yardımcı olabilirim?",
                            "Doğru, ben bir yapay zeka asistanıyım ve {$aiInfo['purpose']} için buradayım.",
                            "Ben bir yapay zeka asistanı olarak {$aiInfo['purpose']} için programlandım."
                        ];
                        return $specificResponses[array_rand($specificResponses)];
                    }
                }
            }
            
            // Eşleşme yoksa null döndür
            return null;
            
        } catch (\Exception $e) {
            Log::error('Kişisel soru işleme hatası: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Öğretme kalıplarını işler ve öğrenilen bilgileri kaydeder
     */
    private function handleLearningPatterns($message)
    {
        try {
            // Mesajı temizle
            $message = trim($message);
            
            // WordRelations sınıfını başlat
            $wordRelations = app()->make(WordRelations::class);
            
            // Öğretme kalıpları
            $patterns = [
                // X kelimesi Y demektir kalıbı
                '/^([a-zçğıöşü\s]+),?\s+([a-zçğıöşü\s]+)\s+demek(tir)?\.?$/i' => 1,
                
                // X demek Y demek kalıbı
                '/^([a-zçğıöşü\s]+)\s+demek,?\s+([a-zçğıöşü\s]+)\s+(demek(tir)?|anlam[ıi]na gelir)\.?$/i' => 1,
                
                // X, Y anlamına gelir kalıbı
                '/^([a-zçğıöşü\s]+),?\s+([a-zçğıöşü\s]+)\s+(anlam[ıi]ndad[ıi]r|anlam[ıi]na gelir)\.?$/i' => 1,
                
                // X Y'dir kalıbı 
                '/^([a-zçğıöşü\s]+)\s+(([a-zçğıöşü\s]+)(d[ıi]r|dir))\.?$/i' => 1,
                
                // X budur kalıbı
                '/^([a-zçğıöşü\s]+)\s+(budur|odur|şudur)\.?$/i' => 2,
                
                // X demek budur kalıbı
                '/^([a-zçğıöşü\s]+)\s+demek\s+(budur|odur|şudur)\.?$/i' => 2
            ];
            
            // Daha önce kullanıcının sorduğu ancak AI'nin bilmediği kelimeyi bul
            $lastQuery = session('last_unknown_query', '');
            
            foreach ($patterns as $pattern => $wordGroup) {
                if (preg_match($pattern, strtolower($message), $matches)) {
                    // İlk kelime/terim grubu (öğrenilecek kelime)
                    $term = trim($matches[1]);
                    
                    // İkinci kelime/terim grubu (tanım/açıklama)
                    $definition = trim($matches[2]);
                    
                    // Eğer "budur" gibi bir kelime ile bitiyorsa ve son sorgu varsa
                    if (preg_match('/(budur|odur|şudur)$/', $definition) && !empty($lastQuery)) {
                        // Tanımı önceki mesajın içeriği olarak al
                        $definition = trim($lastQuery);
                    }
                    
                    // Kelime kontrolü
                    if (!$wordRelations->isValidWord($term)) {
                        return "Üzgünüm, '$term' kelimesini öğrenmem için geçerli bir kelime olması gerekiyor.";
                    }
                    
                    // Tanım kontrolü
                    if (strlen($definition) < 2) {
                        return "Üzgünüm, '$term' için verdiğiniz tanım çok kısa. Lütfen daha açıklayıcı bir tanım verin.";
                    }
                    
                    // Tanımı kaydet
                    $saveResult = $wordRelations->learnDefinition($term, $definition, true);
                    
                    if ($saveResult) {
                        // Onay yanıtları
                        $confirmations = [
                            "Teşekkürler! '$term' kelimesinin '$definition' anlamına geldiğini öğrendim.",
                            "Anladım, '$term' kelimesi '$definition' demekmiş. Bu bilgiyi kaydettim.",
                            "Bilgi için teşekkürler! '$term' kelimesinin tanımını öğrendim. Bundan sonra bu bilgiyi kullanabilirim.",
                            "'$term' kelimesinin '$definition' olduğunu öğrendim. Teşekkür ederim!",
                            "Yeni bir şey öğrendim: '$term', '$definition' anlamına geliyormuş."
                        ];
                        
                        return $confirmations[array_rand($confirmations)];
                    } else {
                        return "Üzgünüm, '$term' kelimesinin tanımını kaydederken bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
                    }
                }
            }
            
            // Özel durumlar - "X köpek demek" gibi kısa tanımlar
            if (preg_match('/^([a-zçğıöşü\s]+)\s+([a-zçğıöşü\s]+)\s+demek$/i', $message, $matches)) {
                $term = trim($matches[1]);
                $definition = trim($matches[2]);
                
                // Kelime kontrolü
                if (!$wordRelations->isValidWord($term)) {
                    return "Üzgünüm, '$term' kelimesini öğrenmem için geçerli bir kelime olması gerekiyor.";
                }
                
                // Tanımı kaydet
                $saveResult = $wordRelations->learnDefinition($term, $definition, true);
                
                if ($saveResult) {
                    // Onay yanıtları
                    $confirmations = [
                        "Teşekkürler! '$term' kelimesinin '$definition' anlamına geldiğini öğrendim.",
                        "Anladım, '$term' kelimesi '$definition' demekmiş. Bu bilgiyi kaydettim.",
                        "Bilgi için teşekkürler! '$term' kelimesinin '$definition' olduğunu öğrendim."
                    ];
                    
                    return $confirmations[array_rand($confirmations)];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Öğrenme kalıbı işleme hatası: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Terim sorgularını işle, yapay zeka cevapları oluştur
     */
    private function processTermQuery($term)
    {
        try {
            $wordInfo = null;
                    
            try {
                $wordRelations = app(\App\AI\Core\WordRelations::class);
                
                // Kelime tanımını al
                $definition = $wordRelations->getDefinition($term);
                
                // Eş anlamlıları al
                $synonyms = $wordRelations->getSynonyms($term);
                
                // İlişkili kelimeleri al
                $relatedWords = $wordRelations->getRelatedWords($term, 0.2);
                
                if (!empty($definition) || !empty($synonyms) || !empty($relatedWords)) {
                    $wordInfo = [
                        'definition' => $definition,
                        'synonyms' => $synonyms,
                        'related' => $relatedWords
                    ];
                }
            } catch (\Exception $e) {
                \Log::warning('Kelime bilgisi alınamadı: ' . $e->getMessage());
            }
            
            // Eğer kelime bilgisi bulunduysa, doğal dil yanıtı oluştur
            if ($wordInfo) {
                // Önce kavramsal cümleyi dene
                try {
                    $conceptSentence = $wordRelations->generateConceptualSentence($term);
                    if (!empty($conceptSentence)) {
                        return response()->json([
                            'success' => true,
                            'response' => $conceptSentence,
                            'emotional_state' => ['emotion' => 'happy', 'intensity' => 0.7]
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Kavramsal cümle oluşturma hatası: ' . $e->getMessage());
                }
                
                // Eğer kavramsal cümle yoksa, tanım ve ilişkili kelimelerle cümle kur
                
                // Tanım varsa doğal cümleler kur
                if (!empty($wordInfo['definition'])) {
                    // Tanımı bir cümle içinde kullan - rastgele farklı kalıplar seç
                    $cevapKaliplari = [
                        $term . ", " . strtolower($wordInfo['definition']),
                        "Bildiğim kadarıyla " . $term . ", " . strtolower($wordInfo['definition']),
                        $term . " kavramı " . strtolower($wordInfo['definition']),
                        $term . " şu anlama gelir: " . $wordInfo['definition'],
                        "Bana göre " . $term . ", " . strtolower($wordInfo['definition'])
                    ];
                    $response = $cevapKaliplari[array_rand($cevapKaliplari)];
                } else {
                    // Tanım yoksa eş anlamlı ve ilişkili kelimeleri kullanarak doğal bir cümle kur
                    $cumleBaslangici = [
                        $term . " denince aklıma ",
                        $term . " kavramı bana ",
                        "Bana göre " . $term . " deyince ",
                        $term . " kelimesini duyduğumda "
                    ];
                    
                    $response = $cumleBaslangici[array_rand($cumleBaslangici)];
                    $kelimeListesi = [];
                    
                    // Eş anlamlıları ekle
                    if (!empty($wordInfo['synonyms'])) {
                        $synonymList = array_keys($wordInfo['synonyms']);
                        if (count($synonymList) > 0) {
                            $kelimeListesi[] = $synonymList[array_rand($synonymList)];
                        }
                    }
                    
                    // İlişkili kelimeleri ekle
                    if (!empty($wordInfo['related'])) {
                        $relatedItems = [];
                        foreach ($wordInfo['related'] as $relWord => $info) {
                            if (is_array($info) && isset($info['word'])) {
                                $relatedItems[] = $info['word'];
                            } else {
                                $relatedItems[] = $relWord;
                            }
                            if (count($relatedItems) >= 5) break;
                        }
                        
                        // Rastgele 1-3 ilişkili kelime seç
                        if (count($relatedItems) > 0) {
                            $secilecekSayi = min(count($relatedItems), mt_rand(1, 3));
                            shuffle($relatedItems);
                            for ($i = 0; $i < $secilecekSayi; $i++) {
                                $kelimeListesi[] = $relatedItems[$i];
                            }
                        }
                    }
                    
                    // Kelimeleri karıştır
                    shuffle($kelimeListesi);
                    
                    // Cümle oluştur
                    if (count($kelimeListesi) > 0) {
                        // Bağlaçlar
                        $baglaclari = [" ve ", " ile ", ", ayrıca ", ", bunun yanında "];
                        
                        // Cümle sonları
                        $cumleSonlari = [
                            " gibi kavramlar geliyor.",
                            " kelimeleri geliyor.",
                            " kavramları çağrıştırıyor.",
                            " gelir.",
                            " gibi şeyler düşündürüyor.",
                            " düşünüyorum."
                        ];
                        
                        // Kelimeleri bağla
                        $kelimeler = '';
                        $sonKelimeIndex = count($kelimeListesi) - 1;
                        
                        foreach ($kelimeListesi as $index => $kelime) {
                            if ($index == 0) {
                                $kelimeler .= $kelime;
                            } else if ($index == $sonKelimeIndex && $index > 0) {
                                $kelimeler .= $baglaclari[array_rand($baglaclari)] . $kelime;
                            } else {
                                $kelimeler .= ", " . $kelime;
                            }
                        }
                        
                        $response .= $kelimeler . $cumleSonlari[array_rand($cumleSonlari)];
                    } else {
                        // Bilgi yoksa doğal bir cümle oluştur
                        $alternatifCumleler = [
                            $term . " hakkında çok detaylı bilgim yok, ancak araştırmaya devam ediyorum.",
                            $term . " hakkında daha fazla bilgi öğrenmeyi çok isterim.",
                            $term . " konusunda bilgimi geliştirmek için çalışıyorum.",
                            "Henüz " . $term . " hakkında yeterli bilgim yok, bana öğretebilir misiniz?"
                        ];
                        $response = $alternatifCumleler[array_rand($alternatifCumleler)];
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'response' => $response,
                    'emotional_state' => ['emotion' => 'happy', 'intensity' => 0.7]
                ]);
            }
            
            // Kelime bulunamadıysa öğrenme sorusu sor - farklı kalıplar kullan
            $ogrenmeKaliplari = [
                "\"{$term}\" hakkında bilgim yok. Bana bu kelime/kavram hakkında bilgi verebilir misiniz?",
                "Maalesef \"{$term}\" konusunda bilgim yetersiz. Bana öğretebilir misiniz?",
                "\"{$term}\" ile ilgili bilgi dağarcığımda bir şey bulamadım. Bana anlatır mısınız?",
                "Üzgünüm, \"{$term}\" kavramını bilmiyorum. Bana biraz açıklar mısınız?"
            ];
            
            return response()->json([
                'success' => true,
                'response' => $ogrenmeKaliplari[array_rand($ogrenmeKaliplari)],
                'emotional_state' => ['emotion' => 'curious', 'intensity' => 0.8]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Terim işleme hatası: ' . $e->getMessage());
            // Hata durumunda genel bir yanıt oluştur
            $hataYanitlari = [
                "Bu kelime hakkında işlem yaparken bir sorun oluştu. Başka bir kelime denemek ister misiniz?",
                "Bu terimi işlemekte zorlanıyorum. Farklı bir soru sorabilir misiniz?"
            ];
            
            return response()->json([
                'success' => true,
                'response' => $hataYanitlari[array_rand($hataYanitlari)],
                'emotional_state' => ['emotion' => 'sad', 'intensity' => 0.4]
            ]);
        }
    }
    
    /**
     * AI'nin duygusal durumunu al
     * 
     * @return array
     */
    private function getEmotionalState()
    {
        try {
            return $this->brain->getEmotionalState();
        } catch (\Exception $e) {
            \Log::error('Duygusal durum alma hatası: ' . $e->getMessage());
            return ['emotion' => 'neutral', 'intensity' => 0.5];
        }
    }

    /**
     * Kelime ilişkilerini kullanarak dinamik cümle oluşturur
     *
     * @return string
     */
    private function generateDynamicSentence()
    {
        try {
            // WordRelations sınıfını al
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Rastgele bir başlangıç kelimesi seç
            $startWords = ['hayat', 'insan', 'dünya', 'bilgi', 'sevgi', 'zaman', 'doğa', 'teknoloji', 'gelecek', 'bilim'];
            $startWord = $startWords[array_rand($startWords)];
            
            // Veritabanından ilişkili kelimeleri ve tanımları al
            $relatedWords = $wordRelations->getRelatedWords($startWord, 0.3);
            $synonyms = $wordRelations->getSynonyms($startWord);
            $definition = $wordRelations->getDefinition($startWord);
            
            // Eğer veritabanında yeterli veri yoksa generateSmartSentence metodunu kullan
            if (empty($relatedWords) && empty($synonyms) && empty($definition)) {
                return $this->generateSmartSentence();
            }
            
            // Eş anlamlı kelime varsa %30 ihtimalle başlangıç kelimesini değiştir
            if (!empty($synonyms) && mt_rand(1, 100) <= 30) {
                $synonymKeys = array_keys($synonyms);
                if (count($synonymKeys) > 0) {
                    $startWord = $synonymKeys[array_rand($synonymKeys)];
                }
            }
            
            // Cümle kalıpları
            $sentencePatterns = [
                "%s, aslında %s ile bağlantılı olarak %s şeklinde ortaya çıkar.",
                "%s konusunu düşündüğümüzde, %s kavramı ile %s arasında derin bir bağ olduğunu görebiliriz.",
                "Uzmanlar, %s ile %s arasındaki ilişkinin %s yönünde geliştiğini belirtiyorlar.",
                "%s, %s bağlamında ele alındığında %s görüşü ön plana çıkıyor.",
                "Günümüzde %s kavramı, %s ile birlikte düşünüldüğünde %s şeklinde yorumlanabilir.",
                "%s üzerine yapılan araştırmalar, %s ve %s arasında anlamlı bir ilişki olduğunu gösteriyor.",
                "Modern dünyada %s, hem %s hem de %s ile etkileşim halindedir.",
                "%s hakkında düşünürken, %s ve %s unsurlarını göz önünde bulundurmak gerekir."
            ];
            
            // Rastgele bir cümle kalıbı seç
            $pattern = $sentencePatterns[array_rand($sentencePatterns)];
            
            // İlişkili kelimelerden veya tanımdan ikinci kelimeyi seç
            $word2 = '';
            if (!empty($relatedWords)) {
                $relatedKeys = array_keys($relatedWords);
                if (count($relatedKeys) > 0) {
                    $word2 = $relatedKeys[array_rand($relatedKeys)];
                }
            }
            
            // İkinci kelime bulunamadıysa, alternatif kaynaklardan bul
            if (empty($word2)) {
                $alternativeWords = ['anlam', 'kavram', 'düşünce', 'boyut', 'perspektif', 'yaklaşım'];
                $word2 = $alternativeWords[array_rand($alternativeWords)];
            }
            
            // Üçüncü kelime veya ifade için tanımı kullan veya akıllı bir ifade oluştur
            $word3 = '';
            if (!empty($definition)) {
                // Tanımı kısalt
                $word3 = mb_substr($definition, 0, 40, 'UTF-8');
                if (mb_strlen($definition, 'UTF-8') > 40) {
                    $word3 .= '...';
                }
            } else {
                // Alternatif ifadeler
                $conceptPhrases = [
                    'yeni bir bakış açısı',
                    'farklı bir yaklaşım',
                    'alternatif bir düşünce',
                    'sürdürülebilir bir model',
                    'bütünsel bir anlayış',
                    'çok boyutlu bir analiz',
                    'yaratıcı bir sentez',
                    'dönüştürücü bir etki'
                ];
                $word3 = $conceptPhrases[array_rand($conceptPhrases)];
            }
            
            // Cümleyi oluştur
            return sprintf($pattern, $startWord, $word2, $word3);
            
        } catch (\Exception $e) {
            \Log::error('Dinamik cümle oluşturma hatası: ' . $e->getMessage());
            // Hata durumunda standart akıllı cümle üret
            return $this->generateSmartSentence();
        }
    }

    /**
     * Yanıtı hazırla ve gönder
     * 
     * @param string $message AI'dan gelen yanıt
     * @param int $chatId Sohbet kimliği
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendResponse($message, $chatId)
    {
        try {
            // Chat yanıtını kaydet
            $chatMessage = new ChatMessage();
            $chatMessage->chat_id = $chatId;
            $chatMessage->content = $message;
            $chatMessage->sender = 'ai';
            $chatMessage->save();
            
            // Rastgele cümle ekleme (% 20 olasılıkla)
            if (mt_rand(1, 100) <= 20) {
                $sentenceTypes = ['normal', 'smart', 'emotional', 'dynamic'];
                $selectedType = $sentenceTypes[array_rand($sentenceTypes)];
                
                $introductions = [
                    "Bu arada, ", 
                    "Düşündüm de, ", 
                    "Aklıma geldi: ", 
                    "Şunu fark ettim: ", 
                    "İlginç bir şekilde, ", 
                    "Bunu düşünmekten kendimi alamıyorum: ", 
                    "Belki de şöyle düşünmek gerekir: ",
                    "Eklemek isterim ki, "
                ];
                
                $introduction = $introductions[array_rand($introductions)];
                $randomSentence = "";
                
                switch ($selectedType) {
                    case 'normal':
                        $randomSentence = $this->generateRandomSentence();
                        break;
                    case 'smart':
                        $randomSentence = $this->generateSmartSentence();
                        break;
                    case 'emotional':
                        $randomSentence = $this->generateEmotionalSentence();
                        break;
                    case 'dynamic':
                        $randomSentence = $this->generateDynamicSentence();
                        break;
                }
                
                // Cümleyi ekle (eğer üretildiyse)
                if (!empty($randomSentence)) {
                    $message .= "\n\n" . $introduction . $randomSentence;
                    
                    // Üretilen cümleyi öğren
                    $this->learnWordRelations($randomSentence);
                    
                    // Eklenen cümleyi de veritabanına kaydet (alternatif davranış)
                    $chatMessage->content = $message;
                    $chatMessage->save();
                }
            }
            
            // Duygusal durumu al
            $emotionalState = $this->getEmotionalState();
            
            // Yanıtı JSON olarak döndür
            return response()->json([
                'message' => $message, 
                'chat_id' => $chatId,
                'emotional_state' => $emotionalState
            ]);
            
        } catch (\Exception $e) {
            // Hata durumunda loglama yap ve hata yanıtı döndür
            \Log::error('Yanıt gönderme hatası: ' . $e->getMessage());
            return response()->json(['error' => 'Yanıt gönderilirken bir hata oluştu'], 500);
        }
    }

    /**
     * Kelime ilişkilerini öğren 
     *
     * @param string $sentence Öğrenilecek cümle
     * @return void
     */
    private function learnWordRelations($sentence)
    {
        try {
            // WordRelations sınıfını al
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Cümleyi kelimelere ayır
            $words = preg_split('/\s+/', mb_strtolower(trim($sentence), 'UTF-8'));
            
            // Kısa cümleleri işleme
            if (count($words) < 3) {
                return;
            }
            
            // Gereksiz kelimeleri temizle (bağlaçlar, edatlar vs.)
            $stopWords = ['ve', 'veya', 'ile', 'için', 'gibi', 'kadar', 'göre', 'ama', 'fakat', 'ancak', 'de', 'da', 'ki', 'ya', 'mi', 'mu', 'bir', 'bu'];
            $words = array_filter($words, function($word) use ($stopWords) {
                return !in_array($word, $stopWords) && mb_strlen($word, 'UTF-8') > 2;
            });
            
            // Eğer yeterli kelime kalmadıysa işlemi sonlandır
            if (count($words) < 2) {
                return;
            }
            
            // Kelimeler arasında ilişki kur
            $mainWords = array_values($words);
            
            // Sık kullanılan kelimeler için eş anlamlı ve ilişkili kelimeler öğren
            for ($i = 0; $i < count($mainWords) - 1; $i++) {
                $currentWord = $mainWords[$i];
                $nextWord = $mainWords[$i + 1];
                
                // Eğer ardışık kelimelerse, aralarında bağlam ilişkisi kur
                if (!empty($currentWord) && !empty($nextWord)) {
                    // %30 ihtimalle ilişki kur
                    if (mt_rand(1, 100) <= 30) {
                        $wordRelations->learnAssociation($currentWord, $nextWord, 'sentence_context', 0.6);
                    }
                }
                
                // Ana kelimeler için tanımları varsa güçlendir
                if ($i == 0 || $i == count($mainWords) - 1) {
                    $definition = $wordRelations->getDefinition($currentWord);
                    if (!empty($definition)) {
                        // Tanımı güçlendir - veritabanına direkt kaydetmek gibi işlemler burada yapılabilir
                        // Şu an için yalnızca ilişki kuruyoruz
                    }
                }
            }
            
            // Eğer farklı tipte kelimeler varsa (isim, sıfat, fiil) bunları tespit et ve ilişkilendir
            // Bu kısım daha karmaşık NLP işlemleri gerektirir
            
            // Log
            \Log::info('Kelime ilişkileri öğrenme işlemi tamamlandı. İşlenen kelime sayısı: ' . count($mainWords));
            
        } catch (\Exception $e) {
            \Log::error('Kelime ilişkileri öğrenme hatası: ' . $e->getMessage());
        }
    }

    /**
     * Normal mesaj işleme - Brain üzerinden yap
     */
    private function processNormalMessage($message)
    {
        try {
            // Brain sınıfını yeni baştan oluştur
            $brain = new \App\AI\Core\Brain();
            $response = $brain->processInput($message);
            
            // Dönen yanıt JSON veya array ise, uygun şekilde işle
            if (is_array($response) || (is_string($response) && $this->isJson($response))) {
                if (is_string($response)) {
                    $responseData = json_decode($response, true);
                } else {
                    $responseData = $response;
                }
                
                // Yanıt alanlarını kontrol et
                if (isset($responseData['output'])) {
                    $responseText = $responseData['output'];
                } elseif (isset($responseData['message'])) { 
                    $responseText = $responseData['message'];
                } elseif (isset($responseData['response'])) {
                    $responseText = $responseData['response'];
                } else {
                    // Hiçbir anlamlı yanıt alanı bulunamadıysa
                    $responseText = "Özür dilerim, bu konuda düzgün bir yanıt oluşturamadım.";
                }
            } else {
                $responseText = $response;
            }
            
            // Yanıt metni cümlelerine ayır
            $sentences = preg_split('/(?<=[.!?])\s+/', $responseText, -1, PREG_SPLIT_NO_EMPTY);
            
            // Cümleler en az 3 tane ise, bazılarını daha yaratıcı cümlelerle değiştir
            if (count($sentences) >= 3) {
                // %40-60 arası cümleleri yeniden oluştur
                $replaceCount = max(1, round(count($sentences) * (mt_rand(40, 60) / 100)));
                
                for ($i = 0; $i < $replaceCount; $i++) {
                    // Değiştirilecek rastgele bir cümle seç (ilk ve son cümleyi dışarıda bırak)
                    $replaceIndex = mt_rand(1, count($sentences) - 2);
                    
                    // Şu anki cümleyi al ve kelimelerini analiz et
                    $currentSentence = $sentences[$replaceIndex];
                    $words = preg_split('/\s+/', trim($currentSentence), -1, PREG_SPLIT_NO_EMPTY);
                    
                    // Anlamlı kelimeleri bul (4 harften uzun olanlar)
                    $meaningfulWords = array_filter($words, function($word) {
                        return mb_strlen(trim($word, '.,!?:;()[]{}"\'-'), 'UTF-8') > 4;
                    });
                    
                    // En az 2 anlamlı kelime varsa işlemi yap
                    if (count($meaningfulWords) >= 2) {
                        // Önemli kelimeleri al
                        $keywords = array_values($meaningfulWords);
                        $keyword1 = $keywords[array_rand($keywords)];
                        $keyword2 = $keywords[array_rand($keywords)];
                        
                        // Kelimeleri temizle
                        $keyword1 = trim($keyword1, '.,!?:;()[]{}"\'-');
                        $keyword2 = trim($keyword2, '.,!?:;()[]{}"\'-');
                        
                        // Rastgele yaratıcı cümle yapısı seç
                        $creativeStructures = [
                            "Aslında %s ve %s arasındaki ilişki, konunun özünü oluşturuyor.",
                            "Özellikle %s konusunu %s ile bağdaştırdığımızda ilginç sonuçlar görüyoruz.",
                            "Bu noktada %s unsurunu %s perspektifinden değerlendirmek gerek.",
                            "Dikkat çekici olan, %s kavramının %s üzerindeki etkisidir.",
                            "Belki de %s hakkında düşünürken %s faktörünü daha fazla göz önünde bulundurmalıyız.",
                            "Birçok uzman %s ve %s arasındaki bağlantının kritik olduğunu düşünüyor.",
                            "%s konusunda derinleşirken, %s perspektifi yeni anlayışlar sunabilir.",
                            "Modern yaklaşımlar %s ve %s arasında daha dinamik bir ilişki öngörüyor."
                        ];
                        
                        // %40 ihtimalle bağlam duygu cümlesi oluştur
                        if (mt_rand(1, 100) <= 40) {
                            // Bağlam duygu cümlesi oluştur
                            $creativeReplace = $this->generateEmotionalContextSentence(implode(' ', $meaningfulWords));
                        } else {
                            // Yaratıcı cümle oluştur
                            $creativePattern = $creativeStructures[array_rand($creativeStructures)];
                            $creativeReplace = sprintf($creativePattern, $keyword1, $keyword2);
                        }
                        
                        // Cümleyi değiştir
                        $sentences[$replaceIndex] = $creativeReplace;
                    }
                }
                
                // Cümleleri birleştir
                $responseText = implode(' ', $sentences);
            }
            
            // Yaratıcı dinamik cümle ekleme olasılıkları
            $chanceToAddDynamicSentence = 30; // %30
            $chanceToAddEmotionalSentence = 20; // %20
            $chanceToAddSmartSentence = 15; // %15
            
            // Rastgele bir sayı seç
            $randomChance = mt_rand(1, 100);
            
            // Yanıt uzunsa ekleme yapmayalım
            if (mb_strlen($responseText, 'UTF-8') < 500) {
                $transitions = [
                    "Ayrıca, ", 
                    "Bununla birlikte, ", 
                    "Bunun yanı sıra, ", 
                    "Şunu da eklemek isterim ki, ", 
                    "Ek olarak, ",
                    "Düşünüyorum ki, ",
                    "Aklımdan geçen şu ki, ",
                    "Bir de şöyle bakalım: "
                ];
                $transition = $transitions[array_rand($transitions)];
                
                if ($randomChance <= $chanceToAddDynamicSentence) {
                    // Dinamik kelime ilişkilerinden cümle oluştur
                    $dynamicSentence = $this->generateDynamicSentence();
                    $responseText .= "\n\n" . $transition . $dynamicSentence;
                    
                    // Cümleyi öğren
                    $this->learnWordRelations($dynamicSentence);
                } 
                elseif ($randomChance <= $chanceToAddDynamicSentence + $chanceToAddEmotionalSentence) {
                    // Duygu bazlı bağlamsal cümle oluştur
                    $emotionalSentence = $this->generateEmotionalContextSentence($message);
                    $responseText .= "\n\n" . $transition . $emotionalSentence;
                    
                    // Cümleyi öğren
                    $this->learnWordRelations($emotionalSentence);
                }
                elseif ($randomChance <= $chanceToAddDynamicSentence + $chanceToAddEmotionalSentence + $chanceToAddSmartSentence) {
                    // Akıllı cümle oluştur
                    $smartSentence = $this->generateSmartSentence();
                    $responseText .= "\n\n" . $transition . $smartSentence;
                }
            }
            
            return $responseText;
            
        } catch (\Exception $e) {
            Log::error("Brain işleme hatası: " . $e->getMessage());
            
            return "Düşünme sürecimde bir hata oluştu. Lütfen tekrar deneyin.";
        }
    }

    /**
     * Kullanıcı mesajını işleyen metod
     * 
     * @param string $userMessage Kullanıcı mesajı
     * @return string İşlenmiş AI yanıtı
     */
    private function processMessage($userMessage)
    {
        // Mesaj boş mu kontrol et
        if (empty($userMessage)) {
            return 'Lütfen bir mesaj yazın.';
        }
        
        // Mesaj çok uzun mu kontrol et
        if (strlen($userMessage) > 1000) {
            return 'Mesajınız çok uzun. Lütfen daha kısa bir mesaj yazın.';
        }
        
        try {
            // WordRelations sınıfını yükle
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // Öğrenme sistemini yükle
            $brain = app(\App\AI\Core\Brain::class);
            $learningSystem = $brain->getLearningSystem();
            
            // Kullanıcının gönderdiği mesajdan öğren (kelime ilişkileri)
            if (strlen($userMessage) > 20) {
                // Uzun mesajlardan kelime ilişkilerini öğren
                $this->learnWordRelations($userMessage);
                
                // Mesajdaki her kelimeyi kontrol et ve bilinmeyen kelimeleri öğren
                $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $userMessage));
                foreach ($words as $word) {
                    if (strlen($word) >= 3 && !in_array(strtolower($word), ['için', 'gibi', 'daha', 'bile', 'kadar', 'nasıl', 'neden'])) {
                        // Kelime veritabanında var mı kontrol et
                        $exists = \App\Models\AIData::where('word', $word)->exists();
                        
                        // Eğer kelime veritabanında yoksa ve geçerli bir kelimeyse öğren
                        if (!$exists && $wordRelations->isValidWord($word)) {
                            try {
                                Log::info("Kullanıcı mesajından yeni kelime öğreniliyor: " . $word);
                                $learningSystem->learnWord($word);
                            } catch (\Exception $e) {
                                Log::error("Kelime öğrenme hatası: " . $e->getMessage(), ['word' => $word]);
                            }
                        }
                    }
                }
            }
            
            // Basit selamlaşma ve hal hatır sorma kalıpları için özel yanıtlar
            $greetingResponse = $this->handleGreetings($userMessage);
            if ($greetingResponse) {
                return $this->enhanceResponseWithWordRelations($greetingResponse);
            }
            
            // Öğrenme ve soru kalıplarını kontrol et
            if ($response = $this->processLearningPattern($userMessage)) {
                return $this->enhanceResponseWithWordRelations($response);
            }
            
            if ($response = $this->processQuestionPattern($userMessage)) {
                return $this->enhanceResponseWithWordRelations($response);
            }
            
            // Kişisel sorular için özel yanıtlar (AI hakkında sorular)
            $personalResponse = $this->handlePersonalQuestions($userMessage);
            if ($personalResponse) {
                return $this->enhanceResponseWithWordRelations($personalResponse);
            }
            
            // Basit tek kelimelik sorgu kontrolü
            $singleWordResponse = $this->handleSingleWordMessages($userMessage);
            if ($singleWordResponse) {
                return $this->enhanceResponseWithWordRelations($singleWordResponse);
            }
            
            // Normal mesaj işleme - Brain üzerinden yap
            $response = $this->processNormalMessage($userMessage);
            
            // Yanıtın özgünlüğünü artırmak için konuşma tarzını değiştir ve kelime ilişkilerini kullan
            return $this->enhanceResponseWithWordRelations($response);
            
        } catch (\Exception $e) {
            Log::error("Mesaj işleme hatası: " . $e->getMessage());
            return "Mesajınızı işlerken bir sorun oluştu. Lütfen tekrar deneyin.";
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
     * Duygu bazlı bağlamsal cümle oluşturur
     *
     * @param string $context Bağlam (mesaj içeriğinden)
     * @return string
     */
    private function generateEmotionalContextSentence($context = '')
    {
        try {
            // Duygusal durumu al
            $emotionalState = $this->getEmotionalState();
            
            // Eğer duygusal durum bir dizi ise, emotion alanını al
            if (is_array($emotionalState)) {
                $currentEmotion = $emotionalState['emotion'] ?? 'neutral';
            } else {
                $currentEmotion = $emotionalState;
            }
            
            // Bağlam kelimelerini çıkar (eğer varsa)
            $contextWords = [];
            if (!empty($context)) {
                // Basit kelime ayırma (türkçe dil desteği)
                $words = preg_split('/\s+/', mb_strtolower(trim($context), 'UTF-8'));
                $stopWords = ['ve', 'veya', 'ile', 'için', 'gibi', 'kadar', 'göre', 'ama', 'fakat', 'ancak', 'de', 'da', 'ki', 'mi', 'mu', 'bir', 'bu', 'şu', 'o'];
                
                foreach ($words as $word) {
                    // Gereksiz kelimeleri filtrele ve minimum uzunluk kontrolü yap
                    if (!in_array($word, $stopWords) && mb_strlen($word, 'UTF-8') > 3) {
                        $contextWords[] = $word;
                    }
                }
            }
            
            // Eğer bağlam kelimesi yoksa, varsayılan kelimeler kullan
            if (empty($contextWords)) {
                $contextWords = ['düşünce', 'bilgi', 'kavram', 'duygu', 'anlayış', 'yaşam', 'gelecek'];
            }
            
            // Rastgele 1-2 bağlam kelimesi seç
            shuffle($contextWords);
            $selectedWords = array_slice($contextWords, 0, min(count($contextWords), mt_rand(1, 2)));
            
            // Duygu bazlı cümle kalıpları
            $emotionalPatterns = [
                'happy' => [
                    "Düşündükçe %s hakkında daha iyimser oluyorum, özellikle %s konusunda.",
                    "%s konusunda heyecan verici şeyler düşünmek beni mutlu ediyor, %s hakkındaki fikirler gibi.",
                    "Sevinçle ifade etmeliyim ki, %s kavramı beni özellikle %s düşündüğümde mutlu ediyor.",
                    "Parlak fikirler düşündüğümde, %s ve %s arasındaki bağlantı beni gülümsetiyor."
                ],
                'neutral' => [
                    "%s konusuna objektif bakıldığında, %s kavramının dengeli bir perspektif sunduğunu görüyorum.",
                    "Tarafsız bir gözle değerlendirdiğimde, %s ve %s arasında mantıklı bir ilişki olduğunu düşünüyorum.",
                    "%s ile ilgili düşüncelerim %s kavramı gibi konularla birleştiğinde net bir resim oluşuyor.",
                    "Rasyonel olarak bakarsak, %s konusu %s ile birlikte ele alınmalıdır."
                ],
                'thoughtful' => [
                    "%s kavramını derinlemesine düşünürken, %s konusunun da önemli olduğunu fark ediyorum.",
                    "%s üzerine biraz daha düşünmem gerekiyor, özellikle %s kavramıyla nasıl ilişkilendiğini.",
                    "Derin düşüncelere daldığımda, %s ve %s arasındaki bağlantının karmaşıklığı beni cezbediyor.",
                    "%s ve %s üzerinde daha fazla düşündükçe, yeni anlayışlara ulaşıyorum."
                ],
                'curious' => [
                    "%s hakkında daha fazla bilgi edinmek istiyorum, özellikle %s ile ilişkisi konusunda.",
                    "Merak ediyorum, %s ve %s arasındaki dinamik nasıl gelişecek?",
                    "%s kavramı beni oldukça meraklandırıyor, %s ile nasıl etkileşim içinde olduğu açısından.",
                    "Keşfetmek istediğim sorular arasında, %s ve %s arasındaki bağlantının doğası var."
                ],
                'excited' => [
                    "%s kavramı beni heyecanlandırıyor, özellikle %s ile ilgili potansiyeli.",
                    "Coşkuyla söylemeliyim ki, %s ve %s birleşimi olağanüstü sonuçlar vadediyor.",
                    "%s hakkında konuşmak bile beni heyecanlandırıyor, %s ile ilgili olanakları düşününce.",
                    "Büyük bir enerjiyle %s ve %s arasındaki sinerjiyi keşfetmeyi iple çekiyorum."
                ]
            ];
            
            // Eğer duygusal durum için kalıp yoksa, neutral kullan
            if (!isset($emotionalPatterns[$currentEmotion])) {
                $currentEmotion = 'neutral';
            }
            
            // Duyguya uygun kalıplardan birini seç
            $patterns = $emotionalPatterns[$currentEmotion];
            $selectedPattern = $patterns[array_rand($patterns)];
            
            // Seçilen kelimeleri cümle içine yerleştir
            if (count($selectedWords) >= 2) {
                $sentence = sprintf($selectedPattern, $selectedWords[0], $selectedWords[1]);
            } else {
                $randomWord = ['düşünce', 'yaşam', 'bilgi', 'gelecek', 'teknoloji', 'sanat'][array_rand(['düşünce', 'yaşam', 'bilgi', 'gelecek', 'teknoloji', 'sanat'])];
                $sentence = sprintf($selectedPattern, $selectedWords[0], $randomWord);
            }
            
            return $sentence;
            
        } catch (\Exception $e) {
            \Log::error('Duygusal bağlamsal cümle oluşturma hatası: ' . $e->getMessage());
            return $this->generateSmartSentence(); 
        }
    }

    private function generateSmartSentence()
    {
        try {
            // WordRelations sınıfını al
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            
            // WordRelations null ise basit yanıt döndür
            if (!$wordRelations) {
                return "Düşünce dünyası ve bilgi, insanın özünde varolan iki temel değerdir.";
            }
            
            // AIData'dan en sık kullanılan kelimelerden rasgele birkaçını al
            try {
                $randomWords = \App\Models\AIData::where('frequency', '>', 3)
                    ->inRandomOrder()
                    ->limit(5)
                    ->pluck('word')
                    ->toArray();
            } catch (\Exception $e) {
                \Log::error('Kelime getirme hatası: ' . $e->getMessage());
                $randomWords = [];
            }
            
            if (empty($randomWords)) {
                // Veritabanında yeterli veri yoksa varsayılan kelimeler kullan
                $randomWords = ['düşünce', 'bilgi', 'yaşam', 'gelecek', 'teknoloji', 'insan', 'dünya'];
            }
            
            // Rastgele bir kelime seç
            $selectedWord = $randomWords[array_rand($randomWords)];
            
            // Farklı cümle oluşturma yöntemlerini rasgele seç
            $generationMethod = mt_rand(1, 4);
            
            switch ($generationMethod) {
                case 1:
                    // İlişkili kelimelerle cümle kur
                    try {
                        $relatedWords = $wordRelations->getRelatedWords($selectedWord);
                        if (!empty($relatedWords)) {
                            // En güçlü ilişkili kelimeleri al
                            $strongRelations = array_slice($relatedWords, 0, 3);
                            
                            // Cümle kalıpları
                            $templates = [
                                "%s kavramı, %s ve %s ile ilişkilidir ve bu ilişki insanların düşünce yapısını geliştirir.",
                                "%s üzerine düşünürken, %s ve %s kavramlarının önemi ortaya çıkar.",
                                "Bilim insanları %s konusunda araştırma yaparken genellikle %s ve %s kavramlarını da incelerler.",
                                "%s, %s ve %s arasındaki bağlantıları anlayabilmek, bu kavramların özünü kavramak için önemlidir."
                            ];
                            
                            $relatedWordsArray = array_keys($strongRelations);
                            
                            // İki kelimeyi seç
                            $word1 = $selectedWord;
                            $word2 = !empty($relatedWordsArray[0]) ? $relatedWordsArray[0] : "düşünce";
                            $word3 = !empty($relatedWordsArray[1]) ? $relatedWordsArray[1] : "bilgi";
                            
                            // Cümleyi oluştur
                            return sprintf($templates[array_rand($templates)], $word1, $word2, $word3);
                        }
                    } catch (\Exception $e) {
                        \Log::error('İlişkili kelime hatası: ' . $e->getMessage());
                    }
                    // İlişkili kelime bulunamazsa bir sonraki metoda düş
                    
                case 2:
                    // Eş anlamlı ve zıt anlamlı kelimeleri kullanarak cümle kur
                    try {
                        $synonyms = $wordRelations->getSynonyms($selectedWord);
                        $antonyms = $wordRelations->getAntonyms($selectedWord);
                        
                        if (!empty($synonyms) || !empty($antonyms)) {
                            // Cümle kalıpları
                            $templates = [];
                            
                            if (!empty($synonyms) && !empty($antonyms)) {
                                $synonymKey = array_rand($synonyms);
                                $antonymKey = array_rand($antonyms);
                                
                                $templates = [
                                    "%s, %s gibi olumlu anlam taşırken, %s tam tersini ifade eder.",
                                    "%s ve %s birbirine benzer kavramlarken, %s bunların zıttıdır.",
                                    "Filozoflar %s kavramını %s ile ilişkilendirirken, %s kavramını da karşıt olarak ele alırlar.",
                                    "%s, %s ile anlam olarak yakınken, %s ile arasında büyük bir fark vardır."
                                ];
                                
                                return sprintf($templates[array_rand($templates)], $selectedWord, $synonymKey, $antonymKey);
                            } 
                            elseif (!empty($synonyms)) {
                                $synonymKey = array_rand($synonyms);
                                
                                $templates = [
                                    "%s ve %s benzer kavramlardır, ikisi de düşünce dünyamızı zenginleştirir.",
                                    "Dilbilimciler %s ve %s kavramlarının birbiriyle yakından ilişkili olduğunu söylerler.",
                                    "%s, %s ile eş anlamlı olarak kullanılabilir ve bu iki kelime düşüncelerimizi ifade etmemize yardımcı olur."
                                ];
                                
                                return sprintf($templates[array_rand($templates)], $selectedWord, $synonymKey);
                            }
                            elseif (!empty($antonyms)) {
                                $antonymKey = array_rand($antonyms);
                                
                                $templates = [
                                    "%s ve %s birbirinin zıt kavramlarıdır, bu zıtlık dünyayı anlamamıza yardımcı olur.",
                                    "Düşünürler %s ve %s kavramlarını karşılaştırarak diyalektik düşünceyi geliştirmişlerdir.",
                                    "%s ile %s arasındaki karşıtlık, bu kavramların daha iyi anlaşılmasını sağlar."
                                ];
                                
                                return sprintf($templates[array_rand($templates)], $selectedWord, $antonymKey);
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Eş/zıt anlam hatası: ' . $e->getMessage());
                    }
                    // Eş veya zıt anlamlı kelime bulunamazsa bir sonraki metoda düş
                    
                case 3:
                    // Tanım kullanarak cümle kur
                    try {
                        $definition = $wordRelations->getDefinition($selectedWord);
                        
                        if (!empty($definition)) {
                            // Cümle kalıpları
                            $templates = [
                                "%s, %s olarak tanımlanabilir ve bu kavram günlük yaşamımızda önemli bir yer tutar.",
                                "Bilimsel bakış açısıyla %s, %s anlamına gelir ve insanların düşünce dünyasını şekillendirir.",
                                "Araştırmacılar %s kavramını '%s' şeklinde tanımlarlar ve bu tanım üzerinde çeşitli tartışmalar yürütülür.",
                                "%s, %s olarak ifade edilebilir ki bu tanım kavramın özünü yansıtır."
                            ];
                            
                            return sprintf($templates[array_rand($templates)], $selectedWord, $definition);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Tanım getirme hatası: ' . $e->getMessage());
                    }
                    // Tanım bulunamazsa bir sonraki metoda düş
                    
                case 4:
                default:
                    // Rasgele iki kelimeyi bir araya getirerek düşünce cümlesi oluştur
                    $secondWord = $randomWords[array_rand($randomWords)];
                    
                    // Aynı kelime seçilirse değiştir
                    while ($secondWord === $selectedWord && count($randomWords) > 1) {
                        $secondWord = $randomWords[array_rand($randomWords)];
                    }
                    
                    // Cümle kalıpları
                    $templates = [
                        "%s ve %s arasındaki ilişki, bilginin nasıl yapılandırıldığını anlamak için önemlidir.",
                        "Düşünce dünyasında %s ve %s kavramları, insanların anlam arayışının temelini oluşturur.",
                        "Felsefeciler %s ile %s arasındaki bağlantının insan zihninin gelişiminde önemli rol oynadığını düşünürler.",
                        "%s ve %s kavramlarını birlikte ele almak, bu konuda daha derin bir anlayış geliştirebilmemizi sağlar.",
                        "İnsan aklının %s ve %s hakkındaki düşünceleri, zaman içinde toplumların gelişimine katkıda bulunmuştur."
                    ];
                    
                    return sprintf($templates[array_rand($templates)], $selectedWord, $secondWord);
            }
            
        } catch (\Exception $e) {
            \Log::error('Akıllı cümle oluşturma hatası: ' . $e->getMessage());
            // Hata durumunda basit bir cümle döndür
            return "Bilgi ve düşünce, insanın gelişiminde önemli rol oynar.";
        }
    }

    /**
     * Chatın başlığını mesaj içeriğine göre oluştur
     * 
     * @param string $message İlk mesaj
     * @return string
     */
    private function generateChatTitle($message)
    {
        try {
            // Mesajı kısalt
            $title = mb_substr(trim($message), 0, 50, 'UTF-8');
            
            // Eğer çok kısaysa chatın oluşturulma tarihini ekle
            if (mb_strlen($title, 'UTF-8') < 10) {
                $title .= ' (' . now()->format('d.m.Y H:i') . ')';
            }
            
            return $title;
        } catch (\Exception $e) {
            \Log::error('Chat başlığı oluşturma hatası: ' . $e->getMessage());
            return 'Yeni Sohbet - ' . now()->format('d.m.Y H:i');
        }
    }
    
    /**
     * Kullanıcı ve AI mesajlarını kaydet
     * 
     * @param string $userMessage Kullanıcı mesajı
     * @param string $aiResponse AI yanıtı
     * @param int $chatId Chat ID
     * @return void
     */
    private function saveMessages($userMessage, $aiResponse, $chatId)
    {
        try {
            // Kullanıcı mesajını kaydet
            ChatMessage::create([
                'chat_id' => $chatId,
                'content' => $userMessage,
                'sender' => 'user',
                'metadata' => null
            ]);
            
            // AI yanıtını kaydet
            ChatMessage::create([
                'chat_id' => $chatId,
                'content' => $aiResponse,
                'sender' => 'ai',
                'metadata' => [
                    'emotional_state' => $this->getEmotionalState()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Mesaj kaydetme hatası: ' . $e->getMessage());
        }
    }

    /**
     * Bilinmeyen kelime/kavramları tespit et ve öğrenmeye çalış
     */
    private function handleUnknownTerm($term)
    {
        try {
            // Son bilinmeyen sorguyu kaydet
            session(['last_unknown_query' => $term]);
            
            // Terim veritabanında var mı kontrol et
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            $definition = $wordRelations->getDefinition($term);
            
            if (!empty($definition)) {
                // Terim zaten biliniyor
                return [
                    'known' => true,
                    'definition' => $definition
                ];
            }
            
            // AIData tablosunda kontrol et
            $aiData = \App\Models\AIData::where('word', $term)->first();
            if ($aiData && !empty($aiData->sentence)) {
                return [
                    'known' => true,
                    'definition' => $aiData->sentence
                ];
            }
            
            // Terim bilinmiyor, kullanıcıdan açıklama istemek için
            $questions = [
                "{$term} ne demek? Bu kavram hakkında bilgim yok, bana açıklayabilir misiniz?",
                "{$term} nedir? Bu kelimeyi bilmiyorum, öğrenmeme yardımcı olur musunuz?",
                "Üzgünüm, '{$term}' kelimesinin anlamını bilmiyorum. Bana açıklayabilir misiniz?",
                "'{$term}' hakkında bilgim yok. Bu kelime ne anlama geliyor?"
            ];
            
            $response = $questions[array_rand($questions)];
            
            \Log::info("Bilinmeyen terim sorgusu: " . $term);
            
            return [
                'known' => false,
                'response' => $response
            ];
            
        } catch (\Exception $e) {
            \Log::error("Bilinmeyen terim işleme hatası: " . $e->getMessage());
            return [
                'known' => false,
                'response' => "Üzgünüm, bu kavram hakkında bir bilgim yok. Bana açıklayabilir misiniz?"
            ];
        }
    }
    
    /**
     * Kullanıcının öğrettiği kavramı işle ve kaydet
     */
    private function learnNewConcept($word, $definition)
    {
        try {
            // WordRelations sınıfıyla tanımı öğren
            $wordRelations = app(\App\AI\Core\WordRelations::class);
            $wordRelations->learnDefinition($word, $definition, true);
            
            // AIData tablosuna da ekle
            $aiData = \App\Models\AIData::updateOrCreate(
                ['word' => $word],
                [
                    'sentence' => $definition,
                    'category' => 'user_taught',
                    'language' => 'tr',
                    'frequency' => \DB::raw('COALESCE(frequency, 0) + 3'),
                    'confidence' => 0.9,
                    'context' => 'Kullanıcı tarafından öğretildi - ' . now()->format('Y-m-d')
                ]
            );
            
            // Yanıt için teşekkür mesajları
            $responses = [
                "Teşekkür ederim! '{$word}' kavramını öğrendim.",
                "Bu açıklamayı kaydettim. Artık '{$word}' terimini biliyorum.",
                "Bilgi paylaşımınız için teşekkürler. '{$word}' kelimesini öğrendim.",
                "Harika! '{$word}' kelimesinin anlamını artık biliyorum."
            ];
            
            \Log::info("Yeni kavram öğrenildi: " . $word . " = " . $definition);
            
            return [
                'success' => true,
                'response' => $responses[array_rand($responses)]
            ];
            
        } catch (\Exception $e) {
            \Log::error("Kavram öğrenme hatası: " . $e->getMessage());
            return [
                'success' => false,
                'response' => "Bu kavramı öğrenmeye çalışırken bir sorun oluştu, ancak açıklamanızı dikkate aldım."
            ];
        }
    }

    /**
     * Soru sorularını işleyerek cevap döndürür
     */
    private function processQuestionPattern($message)
    {
        // Soru kalıplarını kontrol et
        $pattern = $this->checkQuestionPattern($message);
        
        if (!$pattern) {
            return false;
        }
        
        try {
            $type = $pattern['type'];
            $term = trim($pattern['term']);
            
            // Kelime veya terim çok kısa ise işleme
            if (strlen($term) < 2) {
                return "Sorgunuz çok kısa. Lütfen daha açıklayıcı bir soru sorun.";
            }
            
            // Term sorgusu - önce veritabanında arama yap
            $result = $this->processTermQuery($term);
            
            // Eğer sonuç bulunduysa (başka bir yerden)
            if (!empty($result) && $result !== "Bu konu hakkında bilgim yok.") {
                return $result;
            }
            
            // Burada terim bilinmiyor, öğrenmeye çalış
            $unknownResult = $this->handleUnknownTerm($term);
            
            if (!$unknownResult['known']) {
                // Bilinmeyen terim, kullanıcıdan açıklama iste
                return $unknownResult['response'];
            } else {
                // Terim biliniyor ama başka kaynaklarda bulunmadı
                return $unknownResult['definition'];
            }
        } catch (\Exception $e) {
            \Log::error("Soru işleme hatası: " . $e->getMessage());
            return "Bu soruyu işlemekte problem yaşadım. Lütfen başka şekilde sormayı deneyin.";
        }
    }

    /**
     * Öğrenme kalıplarını işler
     */
    private function processLearningPattern($message)
    {
        // Öğrenme kalıbını kontrol et
        $pattern = $this->checkLearningPattern($message);
        
        if (!$pattern) {
            // Son bilinmeyen sorgu kontrolü yap
            $lastQuery = session('last_unknown_query', '');
            
            // "Bu ... demektir", "Anlamı ... dır" gibi kalıpları kontrol et
            if (!empty($lastQuery) && 
                (preg_match('/^bu\s+(.+?)(?:\s+demektir)?\.?$/i', $message, $matches) ||
                 preg_match('/^anlamı\s+(.+?)(?:\s+d[ıi]r)?\.?$/i', $message, $matches) ||
                 preg_match('/^(.+?)\s+demektir\.?$/i', $message, $matches))) {
                
                $definition = trim($matches[1]);
                
                // Yeni kavramı öğren
                $learnResult = $this->learnNewConcept($lastQuery, $definition);
                
                // Son sorguyu temizle
                session(['last_unknown_query' => '']);
                
                return $learnResult['response'];
            }
            
            return false;
        }
        
        try {
            $word = trim($pattern['word']);
            $definition = trim($pattern['definition']);
            
            // Kelime geçerliliğini kontrol et
            if (strlen($word) < 2) {
                return "Öğretmek istediğiniz kelime çok kısa.";
            }
            
            // Tanım geçerliliğini kontrol et
            if (strlen($definition) < 3) {
                return "Tanımınız çok kısa, lütfen daha açıklayıcı bir tanım verin.";
            }
            
            // Yeni kavramı öğren
            $learnResult = $this->learnNewConcept($word, $definition);
            
            return $learnResult['response'];
            
        } catch (\Exception $e) {
            \Log::error("Öğrenme kalıbı işleme hatası: " . $e->getMessage());
            return "Bu bilgiyi öğrenmeye çalışırken bir sorun oluştu, ancak açıklamanızı dikkate aldım.";
        }
    }
} 