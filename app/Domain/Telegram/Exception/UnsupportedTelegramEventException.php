<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Exception;

use App\Domain\Enum\TelegramEventTypeEnum;
use RuntimeException;

class UnsupportedTelegramEventException extends RuntimeException
{
    /**
     * @param list<string> $payloadKeys
     */
    public static function forPayload(array $payloadKeys): self
    {
        return new self('Необрабатываемый тип события телеграмм: ' . implode(', ', $payloadKeys));
    }

    public static function withoutChat(TelegramEventTypeEnum $eventType): self
    {
        return new self("Событие телеграмм {$eventType->value} пришло без данных чата");
    }
}