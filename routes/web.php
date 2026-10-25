<?php

use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuarantinedWebhookEventController;
use App\Http\Controllers\WebhookEventController;
use App\Http\Controllers\WebhookEventReplayController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/events', [WebhookEventController::class, 'index'])
    ->name('events.index');

Route::get('/events/{webhookEvent}', [WebhookEventController::class, 'show'])
    ->name('events.show');

Route::get('/quarantine', [QuarantinedWebhookEventController::class, 'index'])
    ->name('quarantine.index');

Route::get('/connectors', [ConnectorController::class, 'index'])
    ->name('connectors.index');

Route::get('/architecture', ArchitectureController::class)
    ->name('architecture');

Route::get('/health', static fn (): Response => response()->noContent())
    ->name('health');

Route::post('/events/{webhookEvent}/replays', WebhookEventReplayController::class)
    ->middleware('throttle:replays')
    ->name('events.replays.store');
