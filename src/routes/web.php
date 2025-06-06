<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tobi1craft\Sso\Http\Controllers\SsoController;

Route::middleware(['web'])->group(function (): void {
    Route::get('/request-sso', [SsoController::class, 'requestLogin']);
    Route::get('/sso/{token}', [SsoController::class, 'handle'])->name('sso-tobi1craft.login');
});
