<?php

declare(strict_types=1);

namespace Tests\Support\DevTools;

use App\Domain\DevTools\Redis\RedisKeyBrowserInterface;
use App\Domain\DevTools\Redis\RedisKeyInfo;
use App\Domain\DevTools\Redis\RedisKeyPage;
use App\Domain\DevTools\Redis\RedisKeyValue;
use InvalidArgumentException;

/**
 * Redis в памяти: тесты ресурса не должны зависеть от живого сервера.
 * Поведение самого phpredis проверяет PhpRedisKeyBrowserTest.
 */
final class InMemoryRedisKeyBrowser implements RedisKeyBrowserInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $data = [
        'default' => [],
        'cache' => [],
    ];

    /**
     * Аргументы последнего обхода — чтобы проверить, что фильтры доехали до Redis.
     *
     * @var array{connection: string, pattern: string, limit: int}|null
     */
    public ?array $lastScan = null;

    public function put(string $connection, string $key, mixed $value): void
    {
        $this->data[$connection][$key] = $value;
    }

    public function connections(): array
    {
        return array_keys($this->data);
    }

    public function keys(string $connection, string $pattern, int $limit): RedisKeyPage
    {
        $this->lastScan = ['connection' => $connection, 'pattern' => $pattern, 'limit' => $limit];

        $matched = array_filter(
            array_keys($this->storage($connection)),
            static fn (string $key): bool => fnmatch($pattern, $key),
        );

        $keys = array_slice($matched, 0, $limit);

        return new RedisKeyPage(
            array_map(
                fn (string $key): RedisKeyInfo => new RedisKeyInfo(
                    key: $key,
                    type: $this->type($this->data[$connection][$key]),
                    ttl: null,
                    size: $this->size($this->data[$connection][$key]),
                ),
                array_values($keys),
            ),
            count($matched) > $limit,
        );
    }

    public function find(string $connection, string $key): ?RedisKeyValue
    {
        $value = $this->storage($connection)[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return new RedisKeyValue(
            key: $key,
            type: $this->type($value),
            ttl: null,
            value: $value,
        );
    }

    public function delete(string $connection, string $key): bool
    {
        if (!array_key_exists($key, $this->storage($connection))) {
            return false;
        }

        unset($this->data[$connection][$key]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(string $connection): array
    {
        if (!isset($this->data[$connection])) {
            throw new InvalidArgumentException('Неизвестное соединение Redis: ' . $connection);
        }

        return $this->data[$connection];
    }

    private function type(mixed $value): string
    {
        return is_array($value) ? 'hash' : 'string';
    }

    private function size(mixed $value): int
    {
        return is_array($value) ? count($value) : strlen((string) $value);
    }
}
