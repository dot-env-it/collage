<?php

namespace DotEnvIt\Collage;

use Illuminate\Support\ServiceProvider;

class CollageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge default configuration array so fallback keys always exist
        $this->mergeConfigFrom(
            __DIR__ . '/../config/collage.php',
            'collage'
        );

        $this->app->bind('collage', function () {
            return new Collage();
        });
    }

    public function boot(): void
    {
        // Allow developers to run: php artisan vendor:publish --tag=collage-config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/collage.php' => config_path('collage.php'),
            ], 'collage-config');
        }
    }
}
