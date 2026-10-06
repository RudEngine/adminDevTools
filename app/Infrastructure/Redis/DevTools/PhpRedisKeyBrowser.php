<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis\DevTools;

use App\Domain\DevTools\Redis\RedisKeyBrowserInterface;
use App\Domain\DevTools\Redis\RedisKeyInfo;
use App\Domain\DevTools\Redis\RedisKeyPage;
use App\Domain\DevTools\Redis\RedisKeyValue;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\RedisManager;
use InvalidArgumentException;
use Redis;

/**
 * Чтение Redis «как есть» через phpredis.
 *
 * Соединения берём не у приложения, а у собственного менеджера с отключённым
 * префиксом: инструмент отладки должен показывать ровно те ключи, которые видит
 * redis-cli. С включённым префиксом SCAN дописывал бы его в маску, и всё, что
 * записано мимо Laravel, стало бы невидимым — для отладки это худший вариант.
 */
final class PhpRedisKeyBrowser implements RedisKeyBrowserInterface
{
    /**
     * Ключи конфигурации database.redis, которые не являются соединениями.
     */
    private const array RESERVED = ['client', 'options', 'clusters'];

    /**
     * Сколько ключей просить у Redis за одну итерацию SCAN.
     */
    private const int SCAN_COUNT = 100;

    private ?RedisManager $manager = null;

    public function __construct(
        private readonly Application $app,
        private readonly ConfigRepository $config,
    ) {
    }

    public function connections(): array
    {
        /** @var array<string, mixed> $redis */
        $redis = $this->config->get('database.redis', []);

        return array_values(array_filter(
            array_keys($redis),
            static fn (string $name): bool => !in_array($name, self::RESERVED, true),
        ));
    }

    public function keys(string $connection, string $pattern, int $limit): RedisKeyPage
    {
        $client = $this->client($connection);

        /** @var list<string> $keys */
        $keys = [];
        $seen = [];
        $cursor = null;
        $truncated = false;

        do {
            /** @var false|list<string> $batch */
            $batch = $client->scan($cursor, $pattern, self::SCAN_COUNT);

            // SCAN законно возвращает false, когда на этой итерации ничего не нашлось:
            // обход на этом не заканчивается, пока курсор не вернулся к нулю.
            foreach ($batch === false ? [] : $batch as $key) {
                $key = (string) $key;

                // Один и тот же ключ SCAN может вернуть дважды — это нормально.
                if (isset($seen[$key])) {
                    continue;
                }

                if (count($keys) >= $limit) {
                    $truncated = true;

                    break 2;
                }

                $seen[$key] = true;
                $keys[] = $key;
            }
        } while ((int) $cursor !== 0);

        return new RedisKeyPage($this->describe($client, $keys), $truncated);
    }

    public function find(string $connection, string $key): ?RedisKeyValue
    {
        $client = $this->client($connection);

        $type = $this->typeName($client->type($key));

        if ($type === null) {
            return null;
        }

        return new RedisKeyValue(
            key: $key,
            type: $type,
            ttl: $this->ttl($client->ttl($key)),
            value: $this->value($client, $key, $type),
        );
    }

    public function delete(string $connection, string $key): bool
    {
        return (int) $this->client($connection)->del($key) > 0;
    }

    /**
     * Тип и TTL спрашиваем одним конвейером, размер — вторым: какую команду
     * просить для размера, известно только после того, как стал известен тип.
     *
     * @param list<string> $keys
     * @return list<RedisKeyInfo>
     */
    private function describe(Redis $client, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $pipe = $client->pipeline();

        foreach ($keys as $key) {
            $pipe->type($key);
            $pipe->ttl($key);
        }

        /** @var list<mixed> $meta */
        $meta = $pipe->exec();

        $types = [];
        $ttls = [];

        foreach ($keys as $index => $key) {
            $types[$key] = $this->typeName($meta[$index * 2] ?? null);
            $ttls[$key] = $this->ttl($meta[$index * 2 + 1] ?? null);
        }

        $pipe = $client->pipeline();

        foreach ($keys as $key) {
            $this->sizeCommand($pipe, $key, $types[$key]);
        }

        /** @var list<mixed> $sizes */
        $sizes = $pipe->exec();

        $result = [];

        foreach ($keys as $index => $key) {
            // Ключ мог истечь между SCAN и опросом — тогда показывать нечего.
            if ($types[$key] === null) {
                continue;
            }

            $result[] = new RedisKeyInfo(
                key: $key,
                type: $types[$key],
                ttl: $ttls[$key],
                size: (int) ($sizes[$index] ?? 0),
            );
        }

        return $result;
    }

    private function sizeCommand(Redis $pipe, string $key, ?string $type): void
    {
        match ($type) {
            'list' => $pipe->lLen($key),
            'set' => $pipe->sCard($key),
            'zset' => $pipe->zCard($key),
            'hash' => $pipe->hLen($key),
            'stream' => $pipe->xLen($key),
            default => $pipe->strLen($key),
        };
    }

    private function value(Redis $client, string $key, string $type): mixed
    {
        return match ($type) {
            'list' => $client->lRange($key, 0, -1),
            'set' => $client->sMembers($key),
            'zset' => $client->zRange($key, 0, -1, true),
            'hash' => $client->hGetAll($key),
            'stream' => $client->xRange($key, '-', '+'),
            default => $client->get($key),
        };
    }

    /**
     * null означает, что ключа нет: phpredis отвечает на TYPE отсутствующего
     * ключа константой REDIS_NOT_FOUND, а не ошибкой.
     */
    private function typeName(mixed $type): ?string
    {
        return match ($type) {
            Redis::REDIS_STRING => 'string',
            Redis::REDIS_SET => 'set',
            Redis::REDIS_LIST => 'list',
            Redis::REDIS_ZSET => 'zset',
            Redis::REDIS_HASH => 'hash',
            Redis::REDIS_STREAM => 'stream',
            default => null,
        };
    }

    /**
     * -1 — ключ без срока жизни, -2 — ключа уже нет. Оба случая для нас «без TTL».
     */
    private function ttl(mixed $ttl): ?int
    {
        $ttl = (int) $ttl;

        return $ttl < 0 ? null : $ttl;
    }

    private function client(string $connection): Redis
    {
        /** @var Redis $client */
        $client = $this->connection($connection)->client();

        return $client;
    }

    private function connection(string $name): Connection
    {
        if (!in_array($name, $this->connections(), true)) {
            throw new InvalidArgumentException('Неизвестное соединение Redis: ' . $name);
        }

        return $this->manager()->connection($name);
    }

    private function manager(): RedisManager
    {
        if ($this->manager instanceof RedisManager) {
            return $this->manager;
        }

        /** @var array<string, mixed> $config */
        $config = $this->config->get('database.redis', []);

        $driver = is_string($config['client'] ?? null) ? $config['client'] : 'phpredis';
        unset($config['client']);

        /** @var array<string, mixed> $options */
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];
        $options['prefix'] = '';
        $config['options'] = $options;

        return $this->manager = new RedisManager($this->app, $driver, $config);
    }
}
