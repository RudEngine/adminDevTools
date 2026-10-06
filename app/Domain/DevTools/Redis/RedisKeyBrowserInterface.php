<?php

declare(strict_types=1);

namespace App\Domain\DevTools\Redis;

use InvalidArgumentException;

interface RedisKeyBrowserInterface
{
    /**
     * Имена доступных соединений в порядке из конфигурации.
     *
     * @return list<string>
     */
    public function connections(): array;

    /**
     * @param string $pattern  Маска в синтаксисе Redis MATCH.
     * @param int $limit  Сколько ключей максимум собрать за обход.
     *
     * @throws InvalidArgumentException Если соединение не описано в конфигурации.
     */
    public function keys(string $connection, string $pattern, int $limit): RedisKeyPage;

    /**
     * @throws InvalidArgumentException Если соединение не описано в конфигурации.
     */
    public function find(string $connection, string $key): ?RedisKeyValue;

    /**
     * @return bool Был ли ключ на момент удаления.
     *
     * @throws InvalidArgumentException Если соединение не описано в конфигурации.
     */
    public function delete(string $connection, string $key): bool;
}
