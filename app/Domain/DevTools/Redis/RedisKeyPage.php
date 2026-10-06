<?php

declare(strict_types=1);

namespace App\Domain\DevTools\Redis;

/**
 * Результат обхода ключей. Флаг truncated означает, что ключей больше, чем
 * разрешённый предел, и показанная выборка неполная — об этом важно сказать
 * пользователю, иначе пустое место в списке он примет за отсутствие данных.
 */
final readonly class RedisKeyPage
{
    /**
     * @param list<RedisKeyInfo> $keys
     */
    public function __construct(
        public array $keys,
        public bool $truncated,
    ) {
    }
}
