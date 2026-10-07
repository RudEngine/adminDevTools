<?php

use Illuminate\Support\Facades\Route;

// Проект — админка на MoonShine, публичной части нет:
// любой адрес вне /admin уводит на домашний роут MoonShine,
// а неавторизованного оттуда перехватит Authenticate и отправит на /admin/login.
// Файл подключается только на app.domain (bootstrap/app.php), поэтому
// на api-домене неизвестные адреса не попадают сюда и получают 404 (JSON).
$toAdmin = fn () => redirect()->to(moonshineRouter()->getEndpoints()->home());

Route::get('/', $toAdmin);

Route::fallback($toAdmin);
