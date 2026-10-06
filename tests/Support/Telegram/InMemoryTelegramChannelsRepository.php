<?php

declare(strict_types=1);

namespace Tests\Support\Telegram;

use App\Domain\Telegram\Entity\TelegramChannels;
use App\Domain\Telegram\Repository\TelegramChannelsRepositoryInterface;

final class InMemoryTelegramChannelsRepository implements TelegramChannelsRepositoryInterface
{
    /** @param list<TelegramChannels> $channels */
    public function __construct(private array $channels = [])
    {
    }

    public function save(TelegramChannels $channel): TelegramChannels
    {
        $this->channels[] = $channel;

        return $channel;
    }

    public function findByChatId(int $chatId): ?TelegramChannels
    {
        foreach ($this->channels as $channel) {
            if ($channel->chatId === $chatId) {
                return $channel;
            }
        }

        return null;
    }

    public function findById(int $id): ?TelegramChannels
    {
        foreach ($this->channels as $channel) {
            if ($channel->id === $id) {
                return $channel;
            }
        }

        return null;
    }
}
