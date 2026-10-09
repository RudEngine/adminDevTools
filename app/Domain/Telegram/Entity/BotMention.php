<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Entity;

/**
 * Сообщение, в котором бота позвали по @username: куда отвечать и о чём спросили.
 * Упоминание из вопроса уже вырезано.
 */
final readonly class BotMention
{
    public function __construct(
        public int    $chatId,
        public int    $messageId,
        public string $question
    ) {
    }
}
