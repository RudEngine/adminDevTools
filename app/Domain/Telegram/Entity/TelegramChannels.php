<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Entity;

use App\Domain\Enum\TelegramEventTypeEnum;

readonly class TelegramChannels
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

    /**
     * Совпадает ли то, что вебхук вообще способен перезаписать.
     *
     * Тип события сюда не входит: он фиксируется при создании записи и описывает повод,
     * по которому канал впервые попал в базу. Запись нужна как свидетельство, что чат
     * существует, и новое событие другого типа этого свидетельства не меняет.
     */
    public function hasSameStateAs(self $other): bool
    {
        return $this->chatId === $other->chatId
            && $this->chatName === $other->chatName;
    }
}
