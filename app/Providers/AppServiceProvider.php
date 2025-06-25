<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FileProcessCacheService;
use App\Services\FastApiService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FileProcessCacheService::class, function ($app) {
            return new FileProcessCacheService($app->make(FastApiService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
