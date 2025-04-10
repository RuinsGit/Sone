<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Veri toplama işlemini 30 dakikada bir çalıştır
        $schedule->command('ai:collect-data')
                 ->everyThirtyMinutes()
                 ->appendOutputTo(storage_path('logs/scheduler-collect.log'))
                 ->onFailure(function () {
                     \Log::error('ai:collect-data komutu çalıştırılamadı');
                 });
        
        // Bilinç sistemini 3 dakikada bir kontrol et
        $schedule->command('ai:consciousness --interval=180')
                 ->everyThreeMinutes()
                 ->appendOutputTo(storage_path('logs/scheduler-consciousness.log'))
                 ->onFailure(function () {
                     \Log::error('ai:consciousness komutu çalıştırılamadı');
                 });
        
        // Sürekli öğrenme işlemini 5 dakikada bir çalıştır
        $schedule->command('ai:learn --limit=50')
                 ->everyFiveMinutes()
                 ->appendOutputTo(storage_path('logs/scheduler-learn.log'))
                 ->onFailure(function () {
                     \Log::error('ai:learn komutu çalıştırılamadı');
                 });
        
        // Rasgele öğrenme işlemini 15 dakikada bir çalıştır
        $schedule->command('ai:learn --limit=20 --force')
                 ->everyFifteenMinutes()
                 ->appendOutputTo(storage_path('logs/scheduler-random-learn.log'))
                 ->onFailure(function () {
                     \Log::error('ai:learn --force komutu çalıştırılamadı');
                 });
        
        // Kelime ilişkilerini öğrenme işlemini 30 dakikada bir çalıştır
        $schedule->command('ai:learn-relations')
                 ->everyThirtyMinutes()
                 ->appendOutputTo(storage_path('logs/scheduler-relations.log'))
                 ->onFailure(function () {
                     \Log::error('ai:learn-relations komutu çalıştırılamadı');
                 });
                 
        // Her 10 dakikada bir otomatik cümle üretme işlemini çalıştır 
        $schedule->command('ai:generate-sentences --count=5')
                 ->everyTenMinutes()
                 ->appendOutputTo(storage_path('logs/scheduler-sentences.log'))
                 ->onFailure(function () {
                     \Log::error('ai:generate-sentences komutu çalıştırılamadı');
                 });
        
        // Saatlik veritabanı temizliği
        $schedule->command('ai:db-maintenance --mode=clean')
                 ->hourly()
                 ->appendOutputTo(storage_path('logs/db-maintenance.log'));
                 
        // Haftalık veritabanı optimizasyonu
        $schedule->command('ai:db-maintenance --mode=optimize')
                 ->weekly()
                 ->appendOutputTo(storage_path('logs/db-maintenance.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
