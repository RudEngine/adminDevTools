<?php

declare(strict_types=1);

namespace App\Domain\DevTools\Redis;

/**
 * Ключ вместе со значением: строка как string, коллекция как массив.
 * Приводить значение к читаемому виду — задача представления, не этого слоя.
 */
final readonly class RedisKeyValue
{
    public function __construct(
        public string $key,
        public string $type,
        public ?int $ttl,
        public mixed $value,
    ) {
    }
}
