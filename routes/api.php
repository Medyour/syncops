<?php

use App\Http\Controllers\WebhookController;
use App\Http\Middleware\EnsureWebhookPayloadSize;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/{connector}', WebhookController::class)
    ->middleware([EnsureWebhookPayloadSize::class, 'throttle:webhooks'])
    ->name('webhooks.store');
