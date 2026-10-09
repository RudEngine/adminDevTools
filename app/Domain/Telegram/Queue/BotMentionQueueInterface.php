<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Queue;

use App\Domain\Telegram\Entity\BotMention;

/**
 * Ответ на упоминание — это запрос к LLM на десятки секунд, держать на нём вебхук
 * нельзя. Обработка вебхука только ставит упоминание в очередь.
 */
interface BotMentionQueueInterface
{
    public function push(BotMention $mention): void;
}
