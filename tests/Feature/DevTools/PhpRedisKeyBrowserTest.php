<?php

declare(strict_types=1);

namespace Tests\Feature\DevTools;

use App\Domain\DevTools\Redis\RedisKeyBrowserInterface;
use App\Infrastructure\Redis\DevTools\PhpRedisKeyBrowser;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use Tests\TestCase;
use Throwable;

class PhpRedisKeyBrowserTest extends TestCase
{
    private const string PREFIX = 'devtools-test-';

    private RedisKeyBrowserInterface $browser;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.redis', [
            'client' => 'phpredis',
            'options' => [
                'prefix' => self::PREFIX,
            ],
            'default' => $this->server(15),
            'cache' => $this->server(14),
        ]);

        try {
            Redis::connection('default')->command('ping', []);
        } catch (Throwable $e) {
            $this->markTestSkipped('Redis недоступен: ' . $e->getMessage());
        }

        Redis::connection('default')->command('flushdb', []);

        $this->browser = app(PhpRedisKeyBrowser::class);
    }

    protected function tearDown(): void
    {
        try {
            Redis::connection('default')->command('flushdb', []);
        } catch (Throwable) {
            // Соединения не было — чистить нечего.
        }

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function server(int $database): array
    {
        return [
            'host' => env('REDIS_TEST_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_TEST_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => $database,
        ];
    }

    private function set(string $key, string $value): void
    {
        Redis::connection('default')->command('set', [$key, $value]);
    }

    public function test_connections_are_listed_without_client_and_options(): void
    {
        $this->assertSame(['default', 'cache'], $this->browser->connections());
    }

    public function test_keys_are_reported_with_their_raw_names(): void
    {
        $this->set('telegram:channel:chat:1', 'значение');

        $page = $this->browser->keys('default', '*', 100);

        $this->assertFalse($page->truncated);
        $this->assertCount(1, $page->keys);
        $this->assertSame(self::PREFIX . 'telegram:channel:chat:1', $page->keys[0]->key);
        $this->assertSame('string', $page->keys[0]->type);
        $this->assertSame(strlen('значение'), $page->keys[0]->size);
        $this->assertNull($page->keys[0]->ttl);
    }

    public function test_mask_filters_keys(): void
    {
        $this->set('telegram:channel:chat:1', 'a');
        $this->set('session:abc', 'b');

        $page = $this->browser->keys('default', self::PREFIX . 'telegram:*', 100);

        $this->assertSame([self::PREFIX . 'telegram:channel:chat:1'], array_map(
            static fn ($info): string => $info->key,
            $page->keys,
        ));
    }

    public function test_limit_stops_the_scan_and_is_reported(): void
    {
        foreach (range(1, 5) as $index) {
            $this->set('key:' . $index, 'a');
        }

        $page = $this->browser->keys('default', '*', 2);

        $this->assertCount(2, $page->keys);
        $this->assertTrue($page->truncated);
    }

    public function test_ttl_is_reported_in_seconds(): void
    {
        Redis::connection('default')->command('setex', ['expiring', 60, 'a']);

        $page = $this->browser->keys('default', self::PREFIX . 'expiring', 100);

        $this->assertNotNull($page->keys[0]->ttl);
        $this->assertGreaterThan(50, $page->keys[0]->ttl);
        $this->assertLessThanOrEqual(60, $page->keys[0]->ttl);
    }

    public function test_hash_is_returned_as_an_array_with_its_field_count(): void
    {
        Redis::connection('default')->command('hset', ['profile', 'name', 'Карл']);

        $page = $this->browser->keys('default', self::PREFIX . 'profile', 100);
        $value = $this->browser->find('default', self::PREFIX . 'profile');

        $this->assertSame('hash', $page->keys[0]->type);
        $this->assertSame(1, $page->keys[0]->size);
        $this->assertNotNull($value);
        $this->assertSame(['name' => 'Карл'], $value->value);
    }

    public function test_string_value_is_found_by_its_raw_key(): void
    {
        $this->set('telegram:channel:chat:1', 'значение');

        $value = $this->browser->find('default', self::PREFIX . 'telegram:channel:chat:1');

        $this->assertNotNull($value);
        $this->assertSame('string', $value->type);
        $this->assertSame('значение', $value->value);
    }

    public function test_missing_key_is_not_found(): void
    {
        $this->assertNull($this->browser->find('default', self::PREFIX . 'nope'));
    }

    public function test_delete_removes_the_key(): void
    {
        $this->set('doomed', 'a');

        $this->assertTrue($this->browser->delete('default', self::PREFIX . 'doomed'));
        $this->assertNull($this->browser->find('default', self::PREFIX . 'doomed'));
    }

    public function test_deleting_a_missing_key_reports_failure(): void
    {
        $this->assertFalse($this->browser->delete('default', self::PREFIX . 'nope'));
    }

    public function test_unknown_connection_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->browser->keys('no-such-connection', '*', 10);
    }
}
