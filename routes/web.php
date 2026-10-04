<?php

use Illuminate\Support\Facades\Route;

// Проект — админка на MoonShine, публичной части нет:
// любой адрес вне /admin уводит на домашний роут MoonShine,
// а неавторизованного оттуда перехватит Authenticate и отправит на /admin/login.
Route::get('/', fn () => redirect()->to(moonshineRouter()->getEndpoints()->home()));

Route::fallback(fn () => redirect()->to(moonshineRouter()->getEndpoints()->home()));
