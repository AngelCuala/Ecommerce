<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Local PSA PSGC dataset — load the JSON files once per request.
        $this->app->singleton(\App\Services\PsgcDirectory::class, fn () => new \App\Services\PsgcDirectory());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}