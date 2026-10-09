<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\HandleWebhook;

readonly class HandleWebhookResponse
{
    public function __construct(
        public int    $channelId,
        public int    $chatId,
        public string $chatName,
        public bool   $mentionQueued = false
    ) {
    }
}
