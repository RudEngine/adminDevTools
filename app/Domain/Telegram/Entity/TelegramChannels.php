<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Entity;

use App\Domain\Enum\TelegramEventTypeEnum;

class TelegramChannels
{
    public function __construct(
        public TelegramEventTypeEnum $telegramEventType,
        public int                   $chatId,
        public string                $chatName,
        public ?int                  $id = null
    ) {}

    public function isStored(): bool
    {
        return $this->id !== null;
    }
}