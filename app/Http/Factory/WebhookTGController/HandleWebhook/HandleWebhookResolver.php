<?php
declare(strict_types=1);

namespace App\Http\Factory\WebhookTGController\HandleWebhook;

use App\Application\UseCase\Telegram\HandleWebhook\HandleWebhookInput;
use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Entity\BotMention;
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
                mention: $eventType === TelegramEventTypeEnum::MESSAGE
                    ? $this->resolveMention($data[$eventType->value], (int) $chat['id'])
                    : null,
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
     * Упоминание бота — сущность mention с его @username в тексте или подписи к медиа.
     * Сам @username из вопроса вырезаем: модели он ничего не говорит. Если кроме
     * упоминания в сообщении ничего нет, спрашивать модель не о чем.
     *
     * @param array<string, mixed> $message
     */
    private function resolveMention(array $message, int $chatId): ?BotMention
    {
        $username = ltrim((string) config('telegram.mention.bot_username'), '@');

        if ($username === '' || !isset($message['message_id'])) {
            return null;
        }

        [$text, $entities] = isset($message['text'])
            ? [$message['text'], $message['entities'] ?? []]
            : [$message['caption'] ?? null, $message['caption_entities'] ?? []];

        if (!is_string($text) || !is_array($entities)) {
            return null;
        }

        // Смещения сущностей Bot API считает в UTF-16 code units, поэтому режем
        // текст в UTF-16: иначе эмодзи перед упоминанием сдвигают позиции.
        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $mentionRanges = [];

        foreach ($entities as $entity) {
            if (($entity['type'] ?? null) !== 'mention') {
                continue;
            }

            $offset = (int) ($entity['offset'] ?? 0) * 2;
            $length = (int) ($entity['length'] ?? 0) * 2;
            $mentioned = mb_convert_encoding(substr($utf16, $offset, $length), 'UTF-8', 'UTF-16LE');

            if (strcasecmp($mentioned, '@'.$username) === 0) {
                $mentionRanges[] = [$offset, $length];
            }
        }

        if ($mentionRanges === []) {
            return null;
        }

        // С конца, чтобы вырезание не сдвигало ещё не обработанные смещения.
        foreach (array_reverse($mentionRanges) as [$offset, $length]) {
            $utf16 = substr_replace($utf16, '', $offset, $length);
        }

        // Пробелы вокруг вырезанного упоминания схлопываем, переносы строк оставляем.
        $question = trim((string) preg_replace('/[ \t]{2,}/u', ' ', mb_convert_encoding($utf16, 'UTF-8', 'UTF-16LE')));

        if ($question === '') {
            return null;
        }

        return new BotMention(
            chatId: $chatId,
            messageId: (int) $message['message_id'],
            question: $question,
        );
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