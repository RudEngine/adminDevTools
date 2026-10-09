<?php
declare(strict_types=1);

namespace App\Infrastructure\Queue\Telegram;

use App\Domain\Telegram\Entity\BotMention;
use App\Domain\Telegram\Queue\BotMentionQueueInterface;

/**
 * Очередь Laravel (QUEUE_CONNECTION, здесь — redis, база REDIS_QUEUE_DB).
 */
final readonly class LaravelBotMentionQueue implements BotMentionQueueInterface
{
    public function push(BotMention $mention): void
    {
        AnswerBotMentionJob::dispatch($mention);
    }
}
