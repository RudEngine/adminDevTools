<?php

namespace Tests\Feature;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Cache\TelegramChannelsCacheInterface;
use App\Http\Middleware\VerifyTelegramWebhookToken;
use App\Models\TelegramChannel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelegramChannelCacheTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'test-webhook-secret';

    private const int CHAT_ID = -1003807797608;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('nutgram.webhook_secret', self::SECRET);
    }

    /**
     * @param array<string, mixed> $chat
     * @return array<string, mixed>
     */
    private function payload(array $chat = [], string $eventType = 'message_reaction'): array
    {
        return [
            'update_id' => 1,
            $eventType => [
                'chat' => array_merge(
                    ['id' => self::CHAT_ID, 'type' => 'supergroup', 'title' => 'Посоны на апщении'],
                    $chat,
                ),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postUpdate(array $payload): \Illuminate\Testing\TestResponse
    {
        $domain = config('app.api_domain');

        return $this->postJson($domain ? 'http://'.$domain.'/webhook' : '/webhook', $payload, [
            VerifyTelegramWebhookToken::HEADER => self::SECRET,
        ]);
    }

    /**
     * Запросы к таблице каналов, выполненные во время $callback.
     *
     * @return list<string>
     */
    private function queriesOnChannels(callable $callback): array
    {
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'telegram_channels')) {
                $queries[] = $query->sql;
            }
        });

        $callback();

        return $queries;
    }

    public function test_first_update_warms_the_cache(): void
    {
        $this->postUpdate($this->payload())->assertNoContent();

        $cached = app(TelegramChannelsCacheInterface::class)->get(self::CHAT_ID);

        $this->assertNotNull($cached);
        $this->assertTrue($cached->isStored());
        $this->assertSame('Посоны на апщении', $cached->chatName);
        $this->assertSame(TelegramEventTypeEnum::MESSAGE_REACTION, $cached->telegramEventType);
    }

    public function test_repeated_identical_update_does_not_touch_the_database(): void
    {
        $this->postUpdate($this->payload())->assertNoContent();

        $queries = $this->queriesOnChannels(fn () => $this->postUpdate($this->payload())->assertNoContent());

        $this->assertSame([], $queries);
        $this->assertDatabaseCount('telegram_channels', 1);
    }

    public function test_renamed_chat_is_written_through_and_recached(): void
    {
        $this->postUpdate($this->payload())->assertNoContent();

        $queries = $this->queriesOnChannels(
            fn () => $this->postUpdate($this->payload(['title' => 'Переименованный чат']))->assertNoContent(),
        );

        $this->assertNotSame([], $queries);
        $this->assertDatabaseCount('telegram_channels', 1);
        $this->assertDatabaseHas('telegram_channels', ['chat_name' => 'Переименованный чат']);

        $this->assertSame(
            'Переименованный чат',
            app(TelegramChannelsCacheInterface::class)->get(self::CHAT_ID)?->chatName,
        );
    }

    /**
     * Запись нужна как свидетельство, что чат существует. Тип события, которым он себя
     * в очередной раз проявил, этого не меняет — значит, и писать в базу нечего.
     */
    public function test_other_event_type_from_known_chat_does_not_touch_the_database(): void
    {
        $this->postUpdate($this->payload())->assertNoContent();

        $queries = $this->queriesOnChannels(
            fn () => $this->postUpdate($this->payload(eventType: 'channel_post'))->assertNoContent(),
        );

        $this->assertSame([], $queries);
        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => self::CHAT_ID,
            'last_event_type' => TelegramEventTypeEnum::MESSAGE_REACTION->value,
        ]);
    }

    public function test_deleting_channel_in_admin_drops_the_cache_and_row_is_recreated(): void
    {
        $this->postUpdate($this->payload())->assertNoContent();

        TelegramChannel::query()->where('chat_id', self::CHAT_ID)->first()?->delete();

        $this->assertNull(app(TelegramChannelsCacheInterface::class)->get(self::CHAT_ID));

        $this->postUpdate($this->payload())->assertNoContent();

        $this->assertDatabaseHas('telegram_channels', ['chat_id' => self::CHAT_ID]);
    }

    public function test_renaming_channel_in_admin_drops_the_cache(): void
    {
        $this->postUpdate($this->payload())->assertNoContent();

        $model = TelegramChannel::query()->where('chat_id', self::CHAT_ID)->firstOrFail();
        $model->update(['chat_name' => 'Правка из админки']);

        $this->assertNull(app(TelegramChannelsCacheInterface::class)->get(self::CHAT_ID));
    }

    /**
     * Непрочитанное значение в кэше не должно ломать обработку: это обычный промах.
     */
    public function test_unreadable_cached_payload_falls_back_to_the_database(): void
    {
        $this->postUpdate($this->payload())->assertNoContent();

        \Illuminate\Support\Facades\Cache::put('telegram:channel:chat:'.self::CHAT_ID, ['id' => 1, 'legacy' => true]);

        $queries = $this->queriesOnChannels(fn () => $this->postUpdate($this->payload())->assertNoContent());

        $this->assertNotSame([], $queries);
        $this->assertDatabaseCount('telegram_channels', 1);
    }
}