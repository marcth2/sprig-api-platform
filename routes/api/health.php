<?php

declare(strict_types=1);

use App\HealthCheck\Actions\CheckServiceHealth;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/health', CheckServiceHealth::class);
    Route::get('/health/{service}', CheckServiceHealth::class);
});
