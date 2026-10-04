<?php

use Illuminate\Support\Facades\Route;
use App\Services\AntaraService;
use App\Http\Controllers\TelegramWebhookController;

Route::view('/', 'welcome')->name('home');

Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::get('/test-antara', function (AntaraService $service) {
    return $service->getArticle(
        'https://branda.antaranews.com/data/content.php?id=13453399&date=2026-09-28&page=1'
    );
});
