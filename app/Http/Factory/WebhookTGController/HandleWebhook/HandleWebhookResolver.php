<?php
declare(strict_types=1);

namespace App\Http\Factory\WebhookTGController\HandleWebhook;

use App\Application\UseCase\Telegram\HandleWebhook\HandleWebhookInput;
use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Exception\UnsupportedTelegramEventException;
use Illuminate\Http\Request;

class HandleWebhookResolver
{
    public function resolve(Request $request): HandleWebhookInput
    {
        $data = $request->all();

        foreach (TelegramEventTypeEnum::cases() as $eventType) {
            if (!array_key_exists($eventType->value, $data)) {
                continue;
            }

            $chat = $this->resolveChat($data[$eventType->value], $eventType->chatPath());

            if ($chat === null) {
                throw UnsupportedTelegramEventException::withoutChat($eventType);
            }

            return new HandleWebhookInput(
                telegramEventType: $eventType,
                chatId: (int) $chat['id'],
                chatName: $this->resolveChatName($chat),
            );
        }

        throw UnsupportedTelegramEventException::forPayload(array_keys($data));
    }

    /**
     * Спускаемся по пути до объекта Chat. Возвращаем null, если на пути нет массивов
     * или в конце не оказалось id: такое событие просто нечего привязывать к каналу.
     *
     * @param mixed $payload
     * @param list<string> $path
     * @return array<string, mixed>|null
     */
    private function resolveChat(mixed $payload, array $path): ?array
    {
        $chat = $payload;

        foreach ($path as $key) {
            if (!is_array($chat) || !isset($chat[$key])) {
                return null;
            }

            $chat = $chat[$key];
        }

        if (!is_array($chat) || !isset($chat['id'])) {
            return null;
        }

        return $chat;
    }

    /**
     * У групп и каналов есть title, у личных чатов — только имя или username.
     *
     * @param array<string, mixed> $chat
     */
    private function resolveChatName(array $chat): string
    {
        foreach (['title', 'username', 'first_name'] as $key) {
            if (isset($chat[$key]) && $chat[$key] !== '') {
                return (string) $chat[$key];
            }
        }

        return (string) $chat['id'];
    }
}