<?php

namespace Tobi1craft\Sso;

use Illuminate\Support\ServiceProvider;
use Tobi1craft\Sso\Commands\GenerateSecretKey;

class SsoServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            GenerateSecretKey::class,
        ]);

        // Registration of routes
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    public function register() {}
}
