<?php

declare(strict_types=1);

namespace App\Domain\DevTools\Redis;

/**
 * Строка списка ключей: само значение сюда не попадает — на обзорной
 * странице его не показывают, а тянуть мегабайты ради списка незачем.
 */
final readonly class RedisKeyInfo
{
    /**
     * @param string $key  Имя ключа ровно в том виде, в каком оно лежит в Redis.
     * @param null|int $ttl  Секунды до истечения; null — ключ живёт вечно.
     * @param int $size  Байты для строки, количество элементов для коллекции.
     */
    public function __construct(
        public string $key,
        public string $type,
        public ?int $ttl,
        public int $size,
    ) {
    }
}
