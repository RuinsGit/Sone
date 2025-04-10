<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\AI\Learn\LearningSystem;
use Illuminate\Support\Facades\Log;

class ContinuousLearning extends Command
{
    protected $signature = 'ai:learn {--limit=100 : İşlenecek maksimum öğe sayısı} {--force : Zaman kontrolünü atla ve hemen öğrenmeye başla}';
    protected $description = 'Yapay zeka için sürekli öğrenme işlemini çalıştır';

    private $learningSystem;

    public function __construct(LearningSystem $learningSystem)
    {
        parent::__construct();
        $this->learningSystem = $learningSystem;
    }

    public function handle()
    {
        $this->info('Sürekli öğrenme işlemi başlatılıyor...');
        $startTime = now();
        
        try {
            $limit = $this->option('limit');
            $force = $this->option('force');
            
            $this->info("İşlenecek maksimum öğe sayısı: $limit");
            if ($force) {
                $this->info("Zorunlu çalıştırma modu aktif - zamanlamayı atla");
            }
            
            // Öğrenme sistemine parametreleri gönder
            $result = $this->learningSystem->continuousLearning([
                'limit' => $limit,
                'force' => $force
            ]);
            
            if ($result['success']) {
                $endTime = now();
                $duration = $endTime->diffInSeconds($startTime);
                
                $this->info('==========================================');
                if (isset($result['type']) && $result['type'] === 'random') {
                    $this->info('RANDOM MOD - Rasgele verilerden öğrenildi');
                    $this->info("İşlenen öğe sayısı: " . $result['learned_items']);
                } else {
                    $this->info('NORMAL MOD - Veritabanından öğrenildi');
                    $this->info("Öğrenilen yeni öğe sayısı: " . $result['learned_items']);
                }
                
                if (isset($result['patterns'])) {
                    $this->info('------------------------------------------');
                    $this->info('Öğrenilen Kalıplar:');
                    foreach ($result['patterns'] as $pattern => $info) {
                        $this->line(" - $pattern: Frekans: {$info['frequency']}, Güven: {$info['confidence']}");
                    }
                }
                
                if (isset($result['relations'])) {
                    $this->info('------------------------------------------');
                    $this->info('Öğrenilen İlişkiler:');
                    $this->info("Eş anlamlı: {$result['relations']['synonyms']}");
                    $this->info("Zıt anlamlı: {$result['relations']['antonyms']}");
                    $this->info("İlişkili: {$result['relations']['associations']}");
                }
                
                $this->info('------------------------------------------');
                $this->info("İşlem süresi: $duration saniye");
                $this->info('==========================================');
                
                return 0;
            } else {
                $this->info('Öğrenme işlemi yapılmadı: ' . ($result['message'] ?? 'Bilinmeyen sebep'));
                return 0;
            }
        } catch (\Exception $e) {
            $this->error('Sürekli öğrenme hatası: ' . $e->getMessage());
            Log::error('Sürekli öğrenme hatası: ' . $e->getMessage());
            return 1;
        }
    }
} 