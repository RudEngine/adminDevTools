<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\SendMessage;

readonly class SendMessageResponse
{
    public function __construct(
        public int    $chatId,
        public string $chatName,
        public int    $messageId
    ) {
    }
}
