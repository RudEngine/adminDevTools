<?php

use Illuminate\Support\Facades\Route;
use Nutgram\Laravel\Middleware\ValidateWebAppData;

Route::middleware(ValidateWebAppData::class)->group(function () {
});
Route::get('/hello', fn () => 'hello world');

