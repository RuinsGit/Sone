<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AI\Core\Brain;
use App\AI\Core\WordRelations;
use Illuminate\Support\Facades\Log;

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
            // Gelen mesajı al
            $message = $request->input('message');
            
            // Gelen mesajı logla
            Log::info("Kullanıcı mesajı: " . $message);
            
            // Mesaj boş mu kontrol et
            if (empty($message)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Lütfen bir mesaj yazın.'
                ]);
            }
            
            // Mesaj çok uzun mu kontrol et (1000 karakterden fazla ise)
            if (strlen($message) > 1000) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Mesajınız çok uzun. Lütfen daha kısa bir mesaj yazın.'
                ]);
            }
            
            // Öğrenme desenini kontrol et - "X, Y demektir" gibi formatlar
            $learningPattern = $this->checkLearningPattern($message);
            if ($learningPattern !== false) {
                $word = $learningPattern['word'];
                $definition = $learningPattern['definition'];
                
                // WordRelations sınıfını doğrudan kullan
                $wordRelations = app(\App\AI\Core\WordRelations::class);
                
                // Kelimeyi doğrula
                if (!$wordRelations->isValidWord($word)) {
                    return response()->json([
                        'status' => 'success',
                        'message' => "Üzgünüm, '$word' kelimesini öğrenmem için geçerli bir kelime olması gerekiyor."
                    ]);
                }
                
                // Tanımı kaydet - hem veritabanına hem session'a
                $saveResult = $wordRelations->learnDefinition($word, $definition, true);
                
                // Session'a kaydet - bu kritik - her zaman session'ı güncelle
                session(["word_definition_" . strtolower($word) => $definition]);
                
                // İlişkili kelimeleri de ayarla
                $words = explode(' ', $definition);
                foreach ($words as $relatedWord) {
                    if ($wordRelations->isValidWord($relatedWord) && $relatedWord != $word) {
                        $wordRelations->learnAssociation($word, $relatedWord, 'user_defined', 0.9);
                    }
                }
                
                if ($saveResult) {
                    // Onay yanıtları
                    $confirmations = [
                        "Teşekkürler! '$word' kelimesinin '$definition' anlamına geldiğini öğrendim.",
                        "Anladım, '$word' kelimesi '$definition' demekmiş. Bu bilgiyi kaydettim.",
                        "Bilgi için teşekkürler! '$word' kelimesinin tanımını öğrendim. Bundan sonra bu bilgiyi kullanabilirim.",
                        "'$word' kelimesinin '$definition' olduğunu öğrendim. Teşekkür ederim!",
                        "Yeni bir şey öğrendim: '$word', '$definition' anlamına geliyormuş."
                    ];
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => $confirmations[array_rand($confirmations)]
                    ]);
                } else {
                    return response()->json([
                        'status' => 'success',
                        'message' => "Üzgünüm, '$word' kelimesinin tanımını kaydederken bir sorun oluştu. Yine de öğrenmeye çalışacağım."
                    ]);
                }
            }
            
            // Soru kalıpları kontrolü - "X nedir", "X ne demek" gibi formatlar
            $questionPattern = $this->checkQuestionPattern($message);
            if ($questionPattern !== false) {
                $term = $questionPattern['term'];
                
                // Session'da tanım var mı kontrol et - bu kritik!
                $sessionKey = "word_definition_" . strtolower($term);
                if (session()->has($sessionKey)) {
                    $definition = session($sessionKey);
                    
                    // Yanıt kalıpları
                    $responses = [
                        "'$term', $definition.",
                        "Bildiğim kadarıyla $term, $definition.",
                        "$term kelimesi $definition.",
                        "$term kavramı $definition."
                    ];
                    
                    Log::info("Session'dan kelime bulundu: $term = $definition");
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => $responses[array_rand($responses)]
                    ]);
                }
                
                // WordRelations'dan kelime bilgisi sorgula
                $wordRelations = app(\App\AI\Core\WordRelations::class);
                $definition = $wordRelations->getDefinition($term);
                
                if (!empty($definition)) {
                    // Tanım varsa session'a da kaydet
                    session([$sessionKey => $definition]);
                    
                    // Yanıt kalıpları
                    $responses = [
                        "'$term', $definition.",
                        "Bildiğim kadarıyla $term, $definition.",
                        "$term kelimesi $definition.",
                        "$term kavramı $definition."
                    ];
                    
                    Log::info("Veritabanından kelime bulundu: $term");
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => $responses[array_rand($responses)]
                    ]);
                }
                
                // Kelime bulunamadıysa
                // Son sorguyu kaydet - daha sonra kullanıcı "budur" derse kullanmak için
                session(['last_unknown_query' => $term]);
                
                $unknownResponses = [
                    "Üzgünüm, '$term' hakkında bilgim yok. Bana bu kelime hakkında bilgi verebilir misiniz? Örneğin: '$term demek, ... demektir'",
                    "'$term' hakkında bir şey bilmiyorum. Bana '$term demektir' diyerek bu kelimeyi öğretebilir misiniz?",
                    "Henüz '$term' kavramını öğrenmedim. Ne anlama geldiğini bana söyleyebilir misiniz?",
                    "'$term' hakkında bilgim yok. Bana açıklayabilir misiniz?",
                    "Maalesef '$term' hakkında hiçbir bilgiye sahip değilim. Bunu bana öğretir misiniz?"
                ];
                
                return response()->json([
                    'status' => 'success',
                    'message' => $unknownResponses[array_rand($unknownResponses)]
                ]);
            }
            
            // Normal mesaj işleme - Brain üzerinden yap
            try {
                $brain = new \App\AI\Core\Brain();
                $response = $brain->processInput($message);
                
                return response()->json([
                    'status' => 'success',
                    'message' => $response
                ]);
            } catch (\Exception $e) {
                Log::error("Brain işleme hatası: " . $e->getMessage());
                
                return response()->json([
                    'status' => 'success',
                    'message' => "Düşünme sürecimde bir hata oluştu. Lütfen tekrar deneyin."
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Genel hata: " . $e->getMessage());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Üzgünüm, bir hata oluştu. Lütfen tekrar deneyin.'
            ]);
        }
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
        if (preg_match('/^(.+?)\s+(.+?)d[ıiuü]r\.?$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "X budur" kalıbı - son sorgu biliniyorsa
        if (preg_match('/^(.+?)\s+(budur|odur|şudur)\.?$/i', $message, $matches)) {
            $lastQuery = session('last_unknown_query', '');
            if (!empty($lastQuery)) {
                return [
                    'word' => $lastQuery,
                    'definition' => trim($matches[1])
                ];
            }
        }
        
        // "X köpek demek" gibi basit kalıp
        if (preg_match('/^(.+?)\s+(.+?)\s+demek$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
            ];
        }
        
        // "tank silah demektir" gibi kalıp
        if (preg_match('/^(.+?)\s+(.+?)\s+demektir$/i', $message, $matches)) {
            return [
                'word' => trim($matches[1]),
                'definition' => trim($matches[2])
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
     * Duygusal durum al - güvenli şekilde
     */
    private function getEmotionalState()
    {
        try {
            return $this->brain->getEmotionalState();
        } catch (\Exception $e) {
            \Log::warning('Duygusal durum alınamadı: ' . $e->getMessage());
            return ['emotion' => 'neutral', 'intensity' => 0.5];
        }
    }
} 