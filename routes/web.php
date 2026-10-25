<?php

use App\Http\Controllers\WebhookEventReplayController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', static fn (): Response => response()->noContent())
    ->name('health');

Route::post('/events/{webhookEvent}/replays', WebhookEventReplayController::class)
    ->middleware('throttle:replays')
    ->name('events.replays.store');
