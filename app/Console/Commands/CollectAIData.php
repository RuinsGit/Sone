<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AIDataCollectorService;

class CollectAIData extends Command
{
    protected $signature = 'ai:collect-data';
    protected $description = 'Yapay zeka için veri topla ve işle';

    private $collector;

    public function __construct(AIDataCollectorService $collector)
    {
        parent::__construct();
        $this->collector = $collector;
    }

    public function handle()
    {
        $this->info('Veri toplama işlemi başlatılıyor...');
        $startTime = now();

        try {
            // İlerlemeyi göstermek için bar tanımla
            $this->output->progressStart(5); // 5 kaynak için

            $result = $this->collector->collectData();

            $this->output->progressFinish();

            if ($result) {
                $this->info('Veri toplama işlemi başarıyla tamamlandı!');
                $this->table(
                    ['Kaynak', 'Toplanan Öğe Sayısı'],
                    [
                        ['Wikipedia', $result['sources']['wikipedia']],
                        ['Sözlük', $result['sources']['dictionary']],
                        ['Haberler', $result['sources']['news']],
                        ['Twitter', $result['sources']['twitter']],
                        ['Reddit', $result['sources']['reddit'] ?? 0]
                    ]
                );
                
                $endTime = now();
                $duration = $endTime->diffInSeconds($startTime);
                $this->info("Toplam işlem süresi: {$duration} saniye");
                $this->info("Toplam toplanan öğe sayısı: {$result['total']}");
                
                return 0;
            } else {
                $this->error('Veri toplama işlemi sırasında bir hata oluştu.');
                return 1;
            }
        } catch (\Exception $e) {
            $this->output->progressFinish();
            $this->error('Hata: ' . $e->getMessage());
            return 1;
        }
    }
} 