<?php
declare(strict_types=1);

namespace App\Infrastructure\Telegram\Nutgram;

use App\Domain\Telegram\Exception\TelegramMessageNotSentException;
use App\Domain\Telegram\Gateway\TelegramMessageSenderInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;

/**
 * Единственное место в приложении, которое знает про Nutgram: выше по стеку есть
 * только порт TelegramMessageSenderInterface.
 */
final readonly class NutgramMessageSender implements TelegramMessageSenderInterface
{
    public function __construct(private Nutgram $bot)
    {
    }

    public function send(int $chatId, string $text): int
    {
        try {
            $message = $this->bot->sendMessage(text: $text, chat_id: $chatId);
        } catch (TelegramException $e) {
            throw TelegramMessageNotSentException::forChat($chatId, $e->getMessage());
        }

        // Отправку вне обработки апдейта Bot API подтверждает объектом Message.
        // Пустой ответ — тоже отказ, просто без описания причины.
        if ($message === null) {
            throw TelegramMessageNotSentException::forChat($chatId, 'телеграмм не вернул сообщение');
        }

        return $message->message_id;
    }
}
