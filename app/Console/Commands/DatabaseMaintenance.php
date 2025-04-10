<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\AIData;
use App\Models\WordRelation;
use App\Models\WordDefinition;

class DatabaseMaintenance extends Command
{
    protected $signature = 'ai:db-maintenance {--mode=clean : Maintenance mode (clean, optimize, reset)}';
    protected $description = 'Veritabanı bakımı gerçekleştirir (temizlik, optimizasyon)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $mode = $this->option('mode');
        
        $this->info('Veritabanı bakımı başlatılıyor... Mod: ' . $mode);
        
        try {
            switch ($mode) {
                case 'clean':
                    $this->cleanDatabase();
                    break;
                case 'optimize':
                    $this->optimizeDatabase();
                    break;
                case 'reset':
                    if ($this->confirm('Bu işlem tüm verileri sıfırlayacak. Devam etmek istiyor musunuz?')) {
                        $this->resetDatabase();
                    }
                    break;
                default:
                    $this->error('Geçersiz mod. Kullanılabilir modlar: clean, optimize, reset');
                    return 1;
            }
            
            $this->info('Veritabanı bakımı başarıyla tamamlandı!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Veritabanı bakımı sırasında hata: ' . $e->getMessage());
            return 1;
        }
    }
    
    private function cleanDatabase()
    {
        $this->info('Veritabanı temizleniyor...');
        
        // Düşük frekanslı AI verileri temizle (frekansı 2'den az olan öğeler)
        $lowFrequencyCount = AIData::where('frequency', '<', 2)->count();
        if ($lowFrequencyCount > 0) {
            $this->info("Düşük frekanslı $lowFrequencyCount veri temizleniyor...");
            AIData::where('frequency', '<', 2)->delete();
        }
        
        // AIData tablosundaki en eski kayıtları temizle (maksimum 450 veri kalacak şekilde)
        $aiDataCount = AIData::count();
        if ($aiDataCount > 450) {
            $limit = $aiDataCount - 450;
            $this->info("AIData tablosundaki en eski $limit kayıt temizleniyor...");
            
            $oldestIds = AIData::orderBy('frequency', 'asc')
                ->orderBy('updated_at', 'asc')
                ->limit($limit)
                ->pluck('id');
                
            AIData::whereIn('id', $oldestIds)->delete();
        }
        
        // Zayıf kelime ilişkilerini temizle (gücü 0.3'ten az olan)
        $weakRelationsCount = WordRelation::where('strength', '<', 0.3)->count();
        if ($weakRelationsCount > 0) {
            $this->info("Zayıf ilişkili $weakRelationsCount kayıt temizleniyor...");
            WordRelation::where('strength', '<', 0.3)->delete();
        }
        
        // WordRelation tablosundaki en eski kayıtları temizle (maksimum 400 veri kalacak şekilde)
        $wordRelationsCount = WordRelation::count();
        if ($wordRelationsCount > 400) {
            $limit = $wordRelationsCount - 400;
            $this->info("WordRelation tablosundaki en eski $limit kayıt temizleniyor...");
            
            $oldestIds = WordRelation::orderBy('strength', 'asc')
                ->orderBy('updated_at', 'asc')
                ->limit($limit)
                ->pluck('id');
                
            WordRelation::whereIn('id', $oldestIds)->delete();
        }
        
        $this->info('Temizlik tamamlandı!');
    }
    
    private function optimizeDatabase()
    {
        $this->info('Veritabanı optimize ediliyor...');
        
        // MySQL/MariaDB için tablo optimizasyonu
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        
        foreach ($tables as $table) {
            $this->info("$table tablosu optimize ediliyor...");
            try {
                DB::statement("OPTIMIZE TABLE $table");
            } catch (\Exception $e) {
                $this->warn("$table tablosu optimize edilemedi: " . $e->getMessage());
                // OPTIMIZE TABLE komutu çalışmazsa, alternatif olarak analiz yap
                try {
                    DB::statement("ANALYZE TABLE $table");
                } catch (\Exception $e2) {
                    $this->warn("$table tablosu analiz edilemedi: " . $e2->getMessage());
                }
            }
        }
        
        $this->info('Optimizasyon tamamlandı!');
    }
    
    private function resetDatabase()
    {
        $this->info('Veritabanı sıfırlanıyor...');
        
        // Tüm verileri sil
        AIData::truncate();
        WordRelation::truncate();
        WordDefinition::truncate();
        
        // Cache'i temizle
        $this->call('cache:clear');
        
        // Temel verileri yeniden ekle
        $this->call('db:seed', ['--class' => 'DefaultAIDataSeeder']);
        
        $this->info('Veritabanı başarıyla sıfırlandı!');
    }
} 