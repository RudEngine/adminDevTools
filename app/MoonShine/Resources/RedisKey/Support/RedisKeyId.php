<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\RedisKey\Support;

/**
 * Идентификатор строки таблицы: имя соединения плюс имя ключа.
 *
 * Соединение едет вместе с ключом, потому что страница просмотра и удаление
 * открываются по собственным маршрутам и фильтр со списка до них не доезжает.
 *
 * Имена ключей содержат двоеточия, слэши и вообще любые байты, поэтому в URL
 * уходит base64url — подставлять сырой ключ в сегмент адреса нельзя.
 */
final readonly class RedisKeyId
{
    public function __construct(
        public string $connection,
        public string $key,
    ) {
    }

    public function toString(): string
    {
        $payload = json_encode([$this->connection, $this->key], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * Любой неразобранный идентификатор — это промах, а не ошибка: подправленный
     * руками адрес должен приводить к «не найдено», а не к падению страницы.
     */
    public static function fromString(string $id): ?self
    {
        $payload = base64_decode(strtr($id, '-_', '+/'), true);

        if ($payload === false) {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded) || !isset($decoded[0], $decoded[1])) {
            return null;
        }

        if (!is_string($decoded[0]) || !is_string($decoded[1])) {
            return null;
        }

        return new self($decoded[0], $decoded[1]);
    }
}
