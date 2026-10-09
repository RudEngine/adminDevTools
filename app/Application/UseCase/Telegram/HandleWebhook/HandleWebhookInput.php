<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\HandleWebhook;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Entity\BotMention;

readonly class HandleWebhookInput
{
    public function __construct(
        public TelegramEventTypeEnum $telegramEventType,
        public int                   $chatId,
        public string                $chatName,
        public ?BotMention           $mention = null
    ) {}
}
