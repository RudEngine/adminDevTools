<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Exception;

use RuntimeException;

/**
 * Телеграмм не принял сообщение: бота выгнали из чата, чат удалён, токен отозван
 * и прочее, что мы со своей стороны исправить не можем.
 */
class TelegramMessageNotSentException extends RuntimeException
{
    public static function forChat(int $chatId, string $reason): self
    {
        return new self("Не удалось отправить сообщение в чат {$chatId}: {$reason}");
    }
}
