<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пропускает только те запросы, в которых Telegram прислал наш секретный токен.
 * Токен задаётся при установке вебхука (setWebhook: secret_token) и приходит
 * в заголовке X-Telegram-Bot-Api-Secret-Token.
 */
class VerifyTelegramWebhookToken
{
    public const string HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('nutgram.webhook_secret');

        if ($secret === '') {
            Log::warning('Секретный токен вебхука телеграмм не настроен: nutgram.webhook_secret пуст');

            abort(Response::HTTP_FORBIDDEN);
        }

        if (!hash_equals($secret, (string) $request->header(self::HEADER, ''))) {
            Log::warning('Отклонили запрос вебхука телеграмм: неверный секретный токен', [
                'ip' => $request->ip(),
            ]);

            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
