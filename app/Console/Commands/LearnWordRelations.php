<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\AI\Core\WordRelations;
use Illuminate\Support\Facades\Log;

class LearnWordRelations extends Command
{
    protected $signature = 'ai:learn-relations';
    protected $description = 'Kelimeler arasındaki ilişkileri öğren';

    private $wordRelations;

    public function __construct(WordRelations $wordRelations)
    {
        parent::__construct();
        $this->wordRelations = $wordRelations;
    }

    public function handle()
    {
        $this->info('Kelime ilişkileri öğrenme işlemi başlatılıyor...');
        
        try {
            $result = $this->wordRelations->collectAndLearnRelations();
            
            if ($result['success']) {
                $this->info('İşlem tamamlandı!');
                $this->info('İşlenen kelime sayısı: ' . $result['processed']);
                $this->info('Öğrenilen ilişki sayısı: ' . $result['learned']);
                
                // İstatistikleri göster
                $stats = $this->wordRelations->getStats();
                $this->info('Toplam eş anlamlı kelime çifti: ' . $stats['synonym_pairs']);
                $this->info('Toplam zıt anlamlı kelime çifti: ' . $stats['antonym_pairs']);
                $this->info('Toplam ilişkili kelime çifti: ' . $stats['association_pairs']);
                $this->info('Toplam kelime tanımı: ' . $stats['definitions']);
                
                return 0;
            } else {
                $this->info($result['message'] ?? 'İşlenecek veri bulunamadı.');
                return 0;
            }
        } catch (\Exception $e) {
            $this->error('Kelime ilişkileri öğrenme hatası: ' . $e->getMessage());
            Log::error('Kelime ilişkileri öğrenme hatası: ' . $e->getMessage());
            return 1;
        }
    }
} 