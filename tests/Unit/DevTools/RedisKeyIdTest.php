<?php

declare(strict_types=1);

namespace Tests\Unit\DevTools;

use App\MoonShine\Resources\RedisKey\Support\RedisKeyId;
use PHPUnit\Framework\TestCase;

class RedisKeyIdTest extends TestCase
{
    public function test_round_trip_preserves_connection_and_key(): void
    {
        $id = (new RedisKeyId('cache', 'telegram:channel:chat:-100380'))->toString();

        $decoded = RedisKeyId::fromString($id);

        $this->assertNotNull($decoded);
        $this->assertSame('cache', $decoded->connection);
        $this->assertSame('telegram:channel:chat:-100380', $decoded->key);
    }

    public function test_identifier_is_safe_for_a_url_segment(): void
    {
        $id = (new RedisKeyId('default', "ключ/с+любыми\x00байтами"))->toString();

        $this->assertSame($id, rawurlencode($id));
    }

    public function test_malformed_identifier_is_rejected(): void
    {
        $this->assertNull(RedisKeyId::fromString('не-base64-!!!'));
    }

    public function test_identifier_without_a_key_is_rejected(): void
    {
        $this->assertNull(RedisKeyId::fromString(rtrim(strtr(base64_encode('["cache"]'), '+/', '-_'), '=')));
    }
}
