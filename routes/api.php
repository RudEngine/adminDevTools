<?php

use App\Http\Controllers\WebhookTGController;
use App\Http\Middleware\VerifyTelegramWebhookToken;
use Illuminate\Support\Facades\Route;
use Nutgram\Laravel\Middleware\ValidateWebAppData;

Route::middleware(ValidateWebAppData::class)->group(function () {
});

Route::post('/webhook', [WebhookTGController::class, 'handleWebhook'])
    ->middleware(VerifyTelegramWebhookToken::class);

