<?php

namespace Tobi1craft\Sso;

use Illuminate\Support\ServiceProvider;

class SsoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/sso.php' => config_path('sso.php'),
        ], 'sso');

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/sso.php',
            'sso'
        );
    }
}
