<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Exception;

use RuntimeException;

/**
 * Телеграмм не дал изменить сообщение: его удалили, бота выгнали из чата и т.п.
 */
class TelegramMessageNotEditedException extends RuntimeException
{
    public static function forMessage(int $chatId, int $messageId, string $reason): self
    {
        return new self("Не удалось изменить сообщение {$messageId} в чате {$chatId}: {$reason}");
    }
}
