<?php
declare(strict_types=1);

namespace App\Infrastructure\Cache\Redis\Telegram\TelegramChannels;

use App\Domain\Telegram\Cache\TelegramChannelsCacheInterface;
use App\Models\TelegramChannel;

/**
 * Админка (MoonShine) правит и удаляет каналы напрямую через Eloquent, минуя use case.
 * Без сброса ключа вебхук продолжил бы верить кэшу: переименование откатилось бы
 * при следующем событии, а удалённая строка не восстановилась бы до истечения TTL.
 */
class TelegramChannelCacheObserver
{
    public function __construct(
        private readonly TelegramChannelsCacheInterface $cache
    ) {
    }

    public function saved(TelegramChannel $model): void
    {
        $this->forget($model);
    }

    public function deleted(TelegramChannel $model): void
    {
        $this->forget($model);
    }

    /**
     * Сбрасываем и прежний chat_id: в админке его можно поменять, и старый ключ
     * остался бы висеть с данными уже другого канала.
     */
    private function forget(TelegramChannel $model): void
    {
        $this->cache->forget($model->chat_id);

        $original = $model->getOriginal('chat_id');

        if ($original !== null && (int) $original !== $model->chat_id) {
            $this->cache->forget((int) $original);
        }
    }
}