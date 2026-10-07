<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * На api-домене клиент всегда «ждёт JSON», что бы ни прислал в Accept.
 * Тогда ошибки, валидация, режим обслуживания и /up отвечают JSON-ом.
 * Подключается глобально (prepend): /up не входит в api-группу,
 * а режим обслуживания срабатывает раньше групповых middleware.
 * Успешные ответы контроллеров не трогаем — в API они и так JSON.
 */
class ForceJsonOnApiDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() === config('app.api_domain')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
