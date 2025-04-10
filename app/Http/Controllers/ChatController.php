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
        $message = $request->input('message');
        
        // Gelen mesajı logla
        Log::info("Chat mesajı alındı: " . $message);
        
        try {
            // Boş mesaj kontrolü
            if (empty($message)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lütfen bir mesaj yazın.'
                ]);
            }
            
            // Çok uzun mesaj kontrolü 
            if (strlen($message) > 1000) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mesajınız çok uzun. Lütfen daha kısa bir mesaj yazın.'
                ]);
            }

            // Özel durumlar - Basit kelimeler (merhaba, selam vb.)
            $singleWordResponse = $this->handleSingleWordMessages($message);
            if ($singleWordResponse !== null) {
                return response()->json([
                    'status' => 'success',
                    'message' => $singleWordResponse
                ]);
            }

            // AI'ye yönelik kişisel sorular (kimsin, adın ne, vs.)
            $personalResponse = $this->handlePersonalQuestions($message);
            if ($personalResponse !== null) {
                return response()->json([
                    'status' => 'success',
                    'message' => $personalResponse
                ]);
            }

            // Basit terim sorguları için gelişmiş yanıt
            $wordRelations = app()->make(WordRelations::class);
            
            // Tek kelimelik soru veya "[kelime] nedir" gibi sorular için özel işleme
            $simpleTermQuery = $this->checkIfSimpleTermQuery($message);
            
            if ($simpleTermQuery !== false) {
                $term = $simpleTermQuery;
                
                // Kelime geçerli mi kontrol et
                if (!$wordRelations->isValidWord($term)) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Bu bir kelime gibi görünmüyor. Lütfen geçerli bir kelime girin.'
                    ]);
                }
                
                try {
                    // Kelime bilgilerini al
                    $definition = $wordRelations->getDefinition($term);
                    $synonyms = $wordRelations->getSynonyms($term);
                    $relatedWords = $wordRelations->getAssociatedWords($term);
                    
                    $response = "";
                    $hasInfo = false;
                    
                    // Tanım varsa
                    if (!empty($definition)) {
                        $hasInfo = true;
                        $templates = [
                            "{$term}, {$definition}.",
                            "Bildiğim kadarıyla {$term}, {$definition}.",
                            "{$term} kelimesi {$definition}.",
                            "{$term} kavramı {$definition}.",
                            "Bana göre {$term} {$definition}.",
                            "{$term} denildiğinde, {$definition}."
                        ];
                        $response = $templates[array_rand($templates)];
                    }
                    
                    // Eşanlamlılar varsa
                    if (!empty($synonyms)) {
                        $hasInfo = true;
                        $synonymList = implode(", ", array_slice($synonyms, 0, 5));
                        
                        if (empty($response)) {
                            $templates = [
                                "{$term} kelimesinin eş anlamlıları: {$synonymList}.",
                                "{$term} ile {$synonymList} benzer anlamlara gelir.",
                                "{$term} yerine {$synonymList} kelimelerini de kullanabilirsiniz.",
                                "{$term} demek, {$synonymList} demektir."
                            ];
                            $response = $templates[array_rand($templates)];
                        } else {
                            // Tanım zaten varsa, eşanlamlıları da ekle
                            $templates = [
                                " Eş anlamlıları arasında {$synonymList} bulunur.",
                                " Ayrıca {$synonymList} kelimeleri de benzer anlama gelir.",
                                " {$synonymList} de benzer kavramlardır."
                            ];
                            $response .= $templates[array_rand($templates)];
                        }
                    }
                    
                    // İlişkili kelimeler varsa
                    if (!empty($relatedWords)) {
                        $hasInfo = true;
                        $relatedList = [];
                        
                        // En fazla 5 ilişkili kelime al
                        foreach (array_slice($relatedWords, 0, 5) as $related) {
                            if (is_array($related) && isset($related['word'])) {
                                $relatedList[] = $related['word'];
                            } else if (is_string($related)) {
                                $relatedList[] = $related;
                            }
                        }
                        
                        if (!empty($relatedList)) {
                            $relatedText = implode(", ", $relatedList);
                            
                            if (empty($response)) {
                                $templates = [
                                    "{$term} denince aklıma {$relatedText} geliyor.",
                                    "{$term} kelimesini duyduğumda {$relatedText} gibi kavramlar geliyor.",
                                    "{$term} ile ilişkili kelimeler: {$relatedText}.",
                                    "{$term} kavramı {$relatedText} ile bağlantılıdır."
                                ];
                                $response = $templates[array_rand($templates)];
                            } else {
                                // Önceki bilgilere ilişkili kelimeleri ekle
                                $templates = [
                                    " Bu kavramla ilişkili olarak {$relatedText} söylenebilir.",
                                    " {$term} denilince akla {$relatedText} da gelir.",
                                    " {$relatedText} kavramları da {$term} ile ilişkilidir."
                                ];
                                $response .= $templates[array_rand($templates)];
                            }
                        }
                    }
                    
                    // Özel kavramsal cümle oluştur
                    try {
                        $conceptualSentence = $wordRelations->generateConceptualSentence($term);
                        if (!empty($conceptualSentence) && $conceptualSentence !== false) {
                            if (empty($response)) {
                                $response = $conceptualSentence;
                            } else {
                                // Rastgele şekilde konuma bağlı olarak ekleyelim
                                if (rand(0, 1) == 0) {
                                    $response = $conceptualSentence . " " . $response;
                                } else {
                                    $response .= " " . $conceptualSentence;
                                }
                            }
                            $hasInfo = true;
                        }
                    } catch (\Exception $e) {
                        Log::error("Kavramsal cümle oluşturma hatası: " . $e->getMessage());
                    }
                    
                    // Eğer kelime hakkında bilgi bulunamadıysa
                    if (!$hasInfo) {
                        $unknownResponses = [
                            "Üzgünüm, '{$term}' hakkında bilgim yok. Bana bu kelime hakkında bilgi verebilir misiniz?",
                            "'{$term}' hakkında bir şey bilmiyorum. Bu konuda bana ne öğretebilirsiniz?",
                            "Henüz '{$term}' kavramını öğrenmedim. Bu kelime ne anlama geliyor?",
                            "'{$term}' hakkında bilgim yok. Bana açıklayabilir misiniz?",
                            "Maalesef '{$term}' hakkında hiçbir bilgiye sahip değilim. Bunu bana öğretir misiniz?"
                        ];
                        $response = $unknownResponses[array_rand($unknownResponses)];
                    }
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => $response
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("Kelime bilgisi alınırken hata: " . $e->getMessage());
                    // Hata durumunda normal akışa devam et
                }
            }
            
            // Gelen mesajı işle ve yanıt oluştur
            $brain = app()->make(Brain::class);
            $responseMessage = $brain->processInput($message);
            
            return response()->json([
                'status' => 'success',
                'message' => $responseMessage
            ]);
            
        } catch (\Exception $e) {
            Log::error("Chat mesajı işlenirken hata: " . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Mesajınız işlenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
            ]);
        }
    }
    
    /**
     * Basit terim sorgusu mu kontrol et (tek kelime veya "nedir/kimdir" sorguları)
     */
    private function checkIfSimpleTermQuery($message)
    {
        // Mesajı temizle
        $message = trim(strtolower($message));
        
        // Tek kelime mi?
        if (!str_contains($message, ' ')) {
            return $message;
        }
        
        // "X nedir" formatı
        if (preg_match('/^(.+)\s+nedir\??$/i', $message, $matches)) {
            return trim($matches[1]);
        }
        
        // "X kimdir" formatı
        if (preg_match('/^(.+)\s+kimdir\??$/i', $message, $matches)) {
            return trim($matches[1]);
        }
        
        // "X ne demek" formatı
        if (preg_match('/^(.+)\s+ne\s+demek\??$/i', $message, $matches)) {
            return trim($matches[1]);
        }
        
        // "X hakkında bilgi ver" formatı
        if (preg_match('/^(.+)\s+hakkında\s+bilgi\s+ver/i', $message, $matches)) {
            return trim($matches[1]);
        }
        
        // "X anlamı nedir" formatı
        if (preg_match('/^(.+)\s+anlamı\s+nedir\??$/i', $message, $matches)) {
            return trim($matches[1]);
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