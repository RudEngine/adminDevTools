<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\RedisKey\Support;

use JsonException;

/**
 * Приводит значение из Redis к тексту, который имеет смысл показывать в админке.
 *
 * Laravel кладёт в кэш результат serialize(), а приложения — чаще JSON, поэтому
 * обе упаковки распаковываем: иначе на странице будет нечитаемый a:2:{s:7:"chat_id";...}.
 */
final readonly class RedisValueFormatter
{
    /**
     * Один ключ может весить мегабайты; страница админки столько показывать не должна.
     */
    public const int DEFAULT_LIMIT = 65536;

    public function __construct(
        private int $limit = self::DEFAULT_LIMIT,
    ) {
    }

    public function format(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (!is_string($value)) {
            return $this->cut($this->toJson($value));
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            return '… двоичное значение, ' . strlen($value) . ' байта';
        }

        return $this->cut($this->unpack($value));
    }

    /**
     * Неудачная распаковка — не ошибка: значение просто не было ни serialize, ни JSON.
     */
    private function unpack(string $value): string
    {
        if ($this->looksSerialized($value)) {
            $unserialized = unserialize($value, ['allowed_classes' => false]);

            // serialize(false) — единственный случай, когда false означает успех.
            if ($unserialized !== false || $value === 'b:0;') {
                return $this->toJson($unserialized);
            }
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $this->toJson($decoded);
        }

        return $value;
    }

    /**
     * Проверка формы перед распаковкой: unserialize() на обычной строке пишет
     * notice, и подавлять его через @ нельзя — обработчик ошибок его всё равно
     * увидит, а в логах появится мусор на каждом просмотре ключа.
     */
    private function looksSerialized(string $value): bool
    {
        return preg_match('/^(?:N;|b:[01];|[aOCEis]:\\d+[:;]|d:(?:\\d|-|I|N))/', $value) === 1;
    }

    private function toJson(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            return (string) var_export($value, true);
        }
    }

    private function cut(string $value): string
    {
        $length = strlen($value);

        if ($length <= $this->limit) {
            return $value;
        }

        return substr($value, 0, $this->limit)
            . "\n\n… значение обрезано, показаны первые " . $this->limit . ' из ' . $length . ' байт';
    }
}
