<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\AI\Core\Brain;
use App\AI\Core\Consciousness;
use App\AI\Core\WordRelations;
use App\Models\AIData;
use Illuminate\Support\Facades\Log;

class GenerateAISentences extends Command
{
    protected $signature = 'ai:generate-sentences {--count=10 : Oluşturulacak cümle sayısı} {--save=true : Cümleleri veritabanına kaydetme}';
    protected $description = 'Yapay zeka için otomatik cümleler oluşturur';

    private $brain;
    private $consciousness;
    private $wordRelations;

    public function __construct()
    {
        parent::__construct();
        $this->brain = new Brain();
        $this->consciousness = new Consciousness();
        $this->wordRelations = new WordRelations();
    }

    public function handle()
    {
        $count = $this->option('count');
        $saveToDb = $this->option('save') === 'true';
        
        $this->info("AI cümle üretme işlemi başlatılıyor...");
        $this->info("Oluşturulacak cümle sayısı: {$count}");
        $this->info("Veritabanına kaydetme: " . ($saveToDb ? 'Evet' : 'Hayır'));
        $this->newLine();
        
        $startTime = now();
        $generatedSentences = [];
        
        try {
            // Sık kullanılan kelimeleri al
            $frequentWords = AIData::where('frequency', '>', 3)
                ->inRandomOrder()
                ->limit(50)
                ->get();
                
            if ($frequentWords->isEmpty()) {
                $this->error('Yeterli kelime verisi bulunamadı!');
                return 1;
            }
            
            $bar = $this->output->createProgressBar($count);
            $bar->start();
            
            for ($i = 0; $i < $count; $i++) {
                // Rasgele kelime seç
                $wordData = $frequentWords->random();
                $word = $wordData->word;
                
                // Cümle üretme yöntemini rasgele seç
                $method = rand(0, 3);
                $sentence = '';
                
                switch ($method) {
                    case 0:
                        // Kavramsal cümle üretimi
                        $sentence = $this->wordRelations->generateConceptualSentence($word);
                        $sentenceType = 'Kavramsal';
                        break;
                    case 1:
                        // İlişkisel cümle üretimi
                        $sentence = $this->wordRelations->generateSentenceWithRelations($word);
                        $sentenceType = 'İlişkisel';
                        break;
                    case 2:
                        // Bilinç tabanlı cümle üretimi
                        $sentence = $this->consciousness->generateConceptualSentence($word);
                        $sentenceType = 'Bilinç';
                        break;
                    case 3:
                        // Brain duygusal cümle 
                        $emotions = ['happy', 'sad', 'neutral', 'curious', 'surprised'];
                        $emotion = $emotions[array_rand($emotions)];
                        
                        // Brain sınıfındaki metodu erişilebilir değil, kendi implementasyonumuz
                        $sentence = $this->generateEmotionalSentence($word, $emotion);
                        $sentenceType = 'Duygusal';
                        break;
                }
                
                if (!empty($sentence)) {
                    $generatedSentences[] = [
                        'sentence' => $sentence,
                        'type' => $sentenceType,
                        'word' => $word
                    ];
                    
                    // Cümleyi Consciousness ile öğren
                    $this->consciousness->update($sentence, ['emotion' => 'neutral', 'intensity' => 0.5]);
                    
                    // Cümleyi veritabanına kaydet
                    if ($saveToDb) {
                        $this->saveToDatabase($sentence, $word, $sentenceType);
                    }
                } else {
                    // Üretim başarısız olduysa geri sayım azaltılmasın
                    $i--;
                }
                
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            
            // Sonuçları göster
            $this->info('Cümle üretimi tamamlandı!');
            $this->newLine();
            
            $headers = ['No', 'Kaynak Kelime', 'Cümle Tipi', 'Üretilen Cümle'];
            $rows = [];
            
            foreach ($generatedSentences as $index => $generated) {
                $rows[] = [
                    $index + 1,
                    $generated['word'],
                    $generated['type'],
                    $generated['sentence']
                ];
            }
            
            $this->table($headers, $rows);
            
            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);
            $this->info("Toplam işlem süresi: {$duration} saniye");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Cümle üretimi sırasında bir hata oluştu: ' . $e->getMessage());
            Log::error('Cümle üretimi hatası: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Üretilen cümleyi veritabanına kaydet
     */
    private function saveToDatabase($sentence, $word, $type)
    {
        try {
            // Cümlenin ilk kelimesini al
            $firstWord = explode(' ', $sentence)[0];
            
            // Kategori belirle
            $category = $this->determineSentenceCategory($sentence, $type);
            
            // Cümleyi veritabanına kaydet
            AIData::updateOrCreate(
                ['word' => $firstWord],
                [
                    'sentence' => $sentence,
                    'category' => $category,
                    'context' => 'AI tarafından ' . now()->format('Y-m-d') . ' tarihinde oluşturuldu',
                    'language' => 'tr',
                    'confidence' => 0.7,
                    'emotional_context' => json_encode([
                        'emotion' => 'neutral',
                        'intensity' => 0.6
                    ])
                ]
            );
            
            // Kelime ilişkilerini öğren
            $words = explode(' ', $sentence);
            foreach ($words as $w) {
                if (strlen($w) > 3 && $w != $firstWord) {
                    $this->wordRelations->learnAssociation($firstWord, $w, 'generated', 0.6);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cümle kaydı hatası: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cümle kategorisini belirle
     */
    private function determineSentenceCategory($sentence, $type)
    {
        // Kategori belirlemek için anahtar kelimeler
        $categoryKeywords = [
            'greeting' => ['merhaba', 'selam', 'günaydın', 'iyi günler', 'nasılsın'],
            'question' => ['mi', 'mı', 'mu', 'mü', 'ne', 'neden', 'nasıl', 'kim', 'hangi'],
            'statement' => ['dır', 'dir', 'tır', 'tir', 'olarak', 'şeklinde'],
            'technology' => ['bilgisayar', 'yapay', 'zeka', 'yazılım', 'internet', 'teknoloji'],
            'emotion' => ['mutlu', 'üzgün', 'kızgın', 'sevinçli', 'mutsuz', 'neşeli'],
            'education' => ['öğren', 'eğitim', 'okul', 'ders', 'bilgi'],
            'daily' => ['bugün', 'yarın', 'hava', 'yemek', 'uyku', 'sabah', 'akşam']
        ];
        
        $sentence = strtolower($sentence);
        
        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($sentence, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        // Cümle tipine göre kategorize et
        if ($type === 'Duygusal') {
            return 'emotion';
        } else if ($type === 'Kavramsal') {
            return 'concept';
        } else if ($type === 'İlişkisel') {
            return 'association';
        }
        
        // Varsayılan kategori
        return 'generated';
    }
    
    /**
     * Duygusal duruma göre cümle üret
     */
    private function generateEmotionalSentence($concept, $emotion)
    {
        // Duygu durumuna göre kelimeler
        $emotionalWords = [
            'happy' => ['güzel', 'harika', 'sevindirici', 'keyifli', 'mutlu', 'neşeli'],
            'sad' => ['üzücü', 'kötü', 'zorlu', 'zor', 'hüzünlü', 'acıklı'],
            'surprised' => ['şaşırtıcı', 'beklenmedik', 'sürpriz', 'ilginç', 'hayret'],
            'curious' => ['merak', 'ilginç', 'düşündürücü', 'araştırma', 'keşif'],
            'neutral' => ['normal', 'olağan', 'standart', 'bilinen', 'alışılmış']
        ];
        
        // Kullanılacak duygusal kelimeler
        $wordSet = $emotionalWords[$emotion] ?? $emotionalWords['neutral'];
        
        // Cümle kalıpları
        $templates = [
            "%concept% gerçekten %emotion_word% bir kavramdır.",
            "%concept% hakkında düşünmek %emotion_word% bir deneyimdir.",
            "%emotion_word% bir şekilde, %concept% önemlidir.",
            "%concept% ile ilgili %emotion_word% düşüncelerim var."
        ];
        
        // Rasgele bir duygu kelimesi seç
        $emotionWord = $wordSet[array_rand($wordSet)];
        
        // Rasgele bir kalıp seç
        $template = $templates[array_rand($templates)];
        
        // Kalıbı doldur
        $sentence = str_replace(
            ['%concept%', '%emotion_word%'],
            [$concept, $emotionWord],
            $template
        );
        
        return $sentence;
    }
} 