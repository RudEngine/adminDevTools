<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Проект — админка на MoonShine, публичной части нет:
// любой адрес вне /admin уводит на домашний роут MoonShine,
// а неавторизованного оттуда перехватит Authenticate и отправит на /admin/login.
// На поддомене API редирект в админку не нужен — отдаём честный 404 (JSON).
$toAdmin = function (Request $request) {
    abort_if($request->getHost() === config('app.api_domain'), 404);

    return redirect()->to(moonshineRouter()->getEndpoints()->home());
};

Route::get('/', $toAdmin);

Route::fallback($toAdmin);
