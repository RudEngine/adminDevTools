<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\HandleWebhook;

use App\Domain\Enum\TelegramEventTypeEnum;

readonly class HandleWebhookInput
{
    public function __construct(
        public TelegramEventTypeEnum $telegramEventType,
        public int                   $chatId,
        public string                $chatName
    ) {}
}
