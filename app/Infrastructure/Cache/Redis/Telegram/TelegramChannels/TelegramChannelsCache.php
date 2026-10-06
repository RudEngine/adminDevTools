<?php
declare(strict_types=1);

namespace App\Infrastructure\Cache\Redis\Telegram\TelegramChannels;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Cache\TelegramChannelsCacheInterface;
use App\Domain\Telegram\Entity\TelegramChannels;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheStore;

class TelegramChannelsCache implements TelegramChannelsCacheInterface
{
    private const string KEY_PREFIX = 'telegram:channel:chat:';

    public function __construct(
        private readonly CacheFactory $cacheFactory
    ) {
    }

    public function get(int $chatId): ?TelegramChannels
    {
        $payload = $this->store()->get($this->key($chatId));

        return is_array($payload) ? $this->toEntity($payload) : null;
    }

    public function put(TelegramChannels $channel): void
    {
        $this->store()->put($this->key($channel->chatId), [
            'id' => $channel->id,
            'chat_id' => $channel->chatId,
            'chat_name' => $channel->chatName,
            'last_event_type' => $channel->telegramEventType->value,
        ], $this->ttl());
    }

    public function forget(int $chatId): void
    {
        $this->store()->forget($this->key($chatId));
    }

    /**
     * Имя стора — из конфига; null означает стор приложения по умолчанию
     * (в этом проекте CACHE_STORE=redis, в тестах — array).
     */
    private function store(): CacheStore
    {
        return $this->cacheFactory->store(config('telegram.channel_cache.store'));
    }

    private function key(int $chatId): string
    {
        return self::KEY_PREFIX . $chatId;
    }

    private function ttl(): int
    {
        return (int) config('telegram.channel_cache.ttl');
    }

    /**
     * В кэше лежит плоский массив, а не сериализованная сущность: после переименования
     * или переноса класса старые ключи остались бы нечитаемыми.
     *
     * Любой неузнанный формат (старый релиз, ручная правка ключа) считаем промахом —
     * тогда состояние просто перезапишется в базу.
     *
     * @param array<string, mixed> $payload
     */
    private function toEntity(array $payload): ?TelegramChannels
    {
        if (!isset($payload['id'], $payload['chat_id'], $payload['chat_name'], $payload['last_event_type'])) {
            return null;
        }

        $eventType = TelegramEventTypeEnum::tryFrom((string) $payload['last_event_type']);

        if ($eventType === null) {
            return null;
        }

        return new TelegramChannels(
            telegramEventType: $eventType,
            chatId: (int) $payload['chat_id'],
            chatName: (string) $payload['chat_name'],
            id: (int) $payload['id'],
        );
    }
}