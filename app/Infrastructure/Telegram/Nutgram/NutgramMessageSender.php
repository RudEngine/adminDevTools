<?php
declare(strict_types=1);

namespace App\Infrastructure\Telegram\Nutgram;

use App\Domain\Telegram\Exception\TelegramMessageNotEditedException;
use App\Domain\Telegram\Exception\TelegramMessageNotSentException;
use App\Domain\Telegram\Gateway\TelegramMessageSenderInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Types\Message\ReplyParameters;

/**
 * Единственное место в приложении, которое знает про Nutgram: выше по стеку есть
 * только порт TelegramMessageSenderInterface.
 */
final readonly class NutgramMessageSender implements TelegramMessageSenderInterface
{
    public function __construct(private Nutgram $bot)
    {
    }

    public function send(int $chatId, string $text, ?int $replyToMessageId = null): int
    {
        // Если исходное сообщение успели удалить, ответ всё равно уходит — просто без цитаты.
        $replyParameters = $replyToMessageId === null
            ? null
            : ReplyParameters::make(message_id: $replyToMessageId, allow_sending_without_reply: true);

        try {
            $message = $this->bot->sendMessage(
                text: $text,
                chat_id: $chatId,
                reply_parameters: $replyParameters,
            );
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

    public function edit(int $chatId, int $messageId, string $text): void
    {
        try {
            $result = $this->bot->editMessageText(text: $text, chat_id: $chatId, message_id: $messageId);
        } catch (TelegramException $e) {
            throw TelegramMessageNotEditedException::forMessage($chatId, $messageId, $e->getMessage());
        }

        if ($result === null || $result === false) {
            throw TelegramMessageNotEditedException::forMessage($chatId, $messageId, 'телеграмм не подтвердил правку');
        }
    }
}
