<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Learning System bağlantısı
        $this->app->singleton('App\AI\Learn\LearningSystem', function ($app) {
            return new \App\AI\Learn\LearningSystem(
                $app->make('App\AI\Core\CategoryManager'),
                $app->make('App\AI\Core\WordRelations')
            );
        });
    }
}
