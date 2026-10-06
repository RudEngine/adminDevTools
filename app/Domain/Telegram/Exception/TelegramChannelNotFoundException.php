<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Exception;

use RuntimeException;

class TelegramChannelNotFoundException extends RuntimeException
{
    public static function byId(int $id): self
    {
        return new self("Канал телеграмм #{$id} не найден");
    }
}
