<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\SendMessage;

readonly class SendMessageInput
{
    public function __construct(
        public int    $channelId,
        public string $text
    ) {}
}
