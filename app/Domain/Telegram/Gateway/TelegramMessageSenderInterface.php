<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Gateway;

use App\Domain\Telegram\Exception\TelegramMessageNotSentException;

/**
 * Исходящая отправка в телеграмм. Домен знает только то, что сообщение можно
 * отправить в чат по его идентификатору; чем именно оно отправляется — дело
 * инфраструктуры.
 */
interface TelegramMessageSenderInterface
{
    /**
     * Возвращает message_id отправленного сообщения.
     *
     * @throws TelegramMessageNotSentException если телеграмм не принял сообщение
     */
    public function send(int $chatId, string $text): int;
}
