<?php

namespace App\Http\Controllers;

use App\Application\UseCase\Telegram\HandleWebhook\HandleWebhookUseCase;
use App\Domain\Telegram\Exception\UnsupportedTelegramEventException;
use App\Http\Factory\WebhookTGController\HandleWebhook\HandleWebhookResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookTGController extends Controller
{
    public function handleWebhook(Request $request): Response
    {
        try {
            $payload = $request->all();

            Log::info('Пришло обновление от телеграмм', [
                'update_id' => $payload['update_id'] ?? null,
                'event_types' => array_values(array_diff(array_keys($payload), ['update_id'])),
            ]);

            // Полный апдейт несёт текст сообщения, имя и username автора. В прод-логи
            // и в breadcrumbs Sentry это не отправляем — только когда отладка включена руками.
            if (config('app.debug')) {
                Log::debug('Полный апдейт телеграмм', $payload);
            }

            $input = app(HandleWebhookResolver::class)->resolve($request);

            $result = app(HandleWebhookUseCase::class)->execute($input);

            // chat_name не логируем: у личных чатов это имя или username человека,
            // для поиска записи хватает channel_id и chat_id.
            Log::info('Успешно обработали', [
                'channel_id' => $result->channelId,
                'chat_id' => $result->chatId,
                'event_type' => $input->telegramEventType->value,
            ]);
        } catch (UnsupportedTelegramEventException $e) {
            Log::info('Пропустили обновление телеграмм: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('Ошибка обработки веб хука: ' . $e->getMessage());
        }

        // Telegram повторяет доставку, пока не получит 2xx, поэтому отвечаем успехом всегда.
        return response()->noContent();
    }
}
