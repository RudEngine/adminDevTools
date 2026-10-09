<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Gateway;

use App\Domain\Telegram\Exception\TelegramMessageNotEditedException;
use App\Domain\Telegram\Exception\TelegramMessageNotSentException;

/**
 * Исходящая отправка в телеграмм. Домен знает только то, что сообщение можно
 * отправить в чат по его идентификатору; чем именно оно отправляется — дело
 * инфраструктуры.
 */
interface TelegramMessageSenderInterface
{
    /**
     * Лимит Bot API на длину текста сообщения.
     */
    public const int MAX_TEXT_LENGTH = 4096;

    /**
     * Возвращает message_id отправленного сообщения. С $replyToMessageId сообщение
     * уходит ответом на указанное.
     *
     * @throws TelegramMessageNotSentException если телеграмм не принял сообщение
     */
    public function send(int $chatId, string $text, ?int $replyToMessageId = null): int;

    /**
     * Заменяет текст ранее отправленного ботом сообщения.
     *
     * @throws TelegramMessageNotEditedException если телеграмм не дал изменить сообщение
     */
    public function edit(int $chatId, int $messageId, string $text): void;
}
