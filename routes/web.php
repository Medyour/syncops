<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WebhookEventController;
use App\Http\Controllers\WebhookEventReplayController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/events', [WebhookEventController::class, 'index'])
    ->name('events.index');

Route::get('/health', static fn (): Response => response()->noContent())
    ->name('health');

Route::post('/events/{webhookEvent}/replays', WebhookEventReplayController::class)
    ->middleware('throttle:replays')
    ->name('events.replays.store');
