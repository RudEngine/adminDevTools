<?php

declare(strict_types=1);

namespace Tests\Unit\DevTools;

use App\MoonShine\Resources\RedisKey\Support\RedisValueFormatter;
use PHPUnit\Framework\TestCase;

class RedisValueFormatterTest extends TestCase
{
    private RedisValueFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new RedisValueFormatter;
    }

    public function test_plain_string_is_shown_as_is(): void
    {
        $this->assertSame('просто строка', $this->formatter->format('просто строка'));
    }

    public function test_serialized_value_is_unpacked(): void
    {
        $value = serialize(['chat_id' => -100380, 'chat_name' => 'Посоны']);

        $this->assertSame(
            "{\n    \"chat_id\": -100380,\n    \"chat_name\": \"Посоны\"\n}",
            $this->formatter->format($value),
        );
    }

    public function test_json_string_is_pretty_printed(): void
    {
        $this->assertSame(
            "{\n    \"a\": 1\n}",
            $this->formatter->format('{"a":1}'),
        );
    }

    public function test_collection_value_is_shown_as_json(): void
    {
        $this->assertSame(
            "[\n    \"один\",\n    \"два\"\n]",
            $this->formatter->format(['один', 'два']),
        );
    }

    public function test_long_value_is_truncated_with_a_notice(): void
    {
        $formatter = new RedisValueFormatter(limit: 10);

        $this->assertSame(
            "0123456789\n\n… значение обрезано, показаны первые 10 из 20 байт",
            $formatter->format('01234567890123456789'),
        );
    }

    public function test_binary_value_is_replaced_with_a_notice(): void
    {
        $this->assertSame(
            '… двоичное значение, 4 байта',
            $this->formatter->format("\xff\xfe\x00\x01"),
        );
    }

    /**
     * Подбор упаковки не должен шуметь в логах: @unserialize() прячет вывод, но
     * сам notice никуда не девается и всплывает у любого обработчика ошибок.
     */
    public function test_plain_string_does_not_raise_php_notices(): void
    {
        $notices = [];

        set_error_handler(static function (int $severity, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        });

        try {
            $this->formatter->format('просто строка');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $notices);
    }

    public function test_missing_value_is_shown_as_a_dash(): void
    {
        $this->assertSame('—', $this->formatter->format(null));
    }
}
