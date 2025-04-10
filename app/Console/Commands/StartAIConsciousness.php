<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\AI\Core\Consciousness;
use Illuminate\Support\Facades\Log;

class StartAIConsciousness extends Command
{
    protected $signature = 'ai:consciousness {--interval=30 : Öğrenme aralığı (saniye)}';
    protected $description = 'Yapay zeka bilinç sistemini başlat';

    private $consciousness;

    public function __construct(Consciousness $consciousness)
    {
        parent::__construct();
        $this->consciousness = $consciousness;
    }

    public function handle()
    {
        try {
            $interval = $this->option('interval');
            
            $this->info('Yapay zeka bilinç sistemi başlatılıyor...');
            $this->info('Öğrenme aralığı: ' . $interval . ' saniye');
            
            // Öğrenme aralığını ayarla
            $this->consciousness->setLearningInterval($interval);
            
            // Sistemi aktif et
            $this->consciousness->activate();
            
            $this->info('Bilinç sistemi başlatıldı!');
            
            // İlk öğrenmeyi başlat
            $this->info('İlk öğrenme işlemi başlatılıyor...');
            $this->consciousness->learnNewData();
            $this->info('İlk öğrenme işlemi tamamlandı.');
            
            // Her 30 saniyede bir durumu göster
            $counter = 0;
            while (true) {
                sleep(30);
                $counter++;
                
                $status = $this->consciousness->getStatus();
                $this->info('Durum Güncellemesi (#' . $counter . '):');
                $this->info('Aktif: ' . ($status['is_active'] ? 'Evet' : 'Hayır'));
                $this->info('Son öğrenme: ' . $status['last_learning']);
                $this->info('Sonraki öğrenme: ' . $status['next_learning']);
                $this->info('Bağlantı sayısı: ' . $status['word_connections']);
                $this->info('-------------------------------------');
                
                // Sürekli öğrenmeyi kontrol et
                $this->consciousness->learnNewData();
            }
            
        } catch (\Exception $e) {
            $this->error('Bilinç sistemi hatası: ' . $e->getMessage());
            Log::error('Bilinç sistemi hatası: ' . $e->getMessage());
            return 1;
        }
    }
} 