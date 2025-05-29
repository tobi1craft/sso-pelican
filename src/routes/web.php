<?php

use Illuminate\Support\Facades\Route;
use WemX\Sso\Http\Controllers\SsoController;

Route::middleware(['web'])->group(function () {
    Route::get('/request-token', [SsoController::class, 'webhook']);
    Route::get('/sso/{token}', [SsoController::class, 'handle'])->name('sso-wemx.login');
});
