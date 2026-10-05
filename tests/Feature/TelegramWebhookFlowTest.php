<?php

namespace Tests\Feature;

use App\Domain\Enum\TelegramEventTypeEnum;
use App\Http\Middleware\VerifyTelegramWebhookToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelegramWebhookFlowTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('nutgram.webhook_secret', self::SECRET);
    }

    private function webhookUrl(): string
    {
        $domain = config('app.api_domain');

        return $domain ? 'http://'.$domain.'/webhook' : '/webhook';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postUpdate(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson($this->webhookUrl(), $payload, [
            VerifyTelegramWebhookToken::HEADER => self::SECRET,
        ]);
    }

    public function test_message_reaction_update_creates_channel(): void
    {
        $this->postUpdate(json_decode(file_get_contents(base_path('new.json')), true))
            ->assertNoContent();

        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => -1003807797608,
            'chat_name' => 'Посоны на апщении',
            'last_event_type' => TelegramEventTypeEnum::MESSAGE_REACTION->value,
        ]);
    }

    public function test_repeated_update_does_not_duplicate_channel(): void
    {
        $payload = json_decode(file_get_contents(base_path('new.json')), true);

        $this->postUpdate($payload)->assertNoContent();
        $this->postUpdate($payload)->assertNoContent();

        $this->assertDatabaseCount('telegram_channels', 1);
    }

    public function test_private_chat_without_title_falls_back_to_username(): void
    {
        $this->postUpdate([
            'update_id' => 1,
            'message_reaction' => [
                'chat' => ['id' => 320951812, 'type' => 'private', 'username' => 'Spell28'],
            ],
        ])->assertNoContent();

        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => 320951812,
            'chat_name' => 'Spell28',
        ]);
    }

    public function test_unsupported_update_is_skipped_without_writing(): void
    {
        $this->postUpdate(['update_id' => 1, 'inline_query' => ['id' => '1', 'query' => 'hi']])
            ->assertNoContent();

        $this->assertDatabaseCount('telegram_channels', 0);
    }

    /**
     * Любое событие с чатом в корне полезной нагрузки создаёт канал.
     */
    #[DataProvider('eventsWithChatInRoot')]
    public function test_event_with_chat_in_root_creates_channel(TelegramEventTypeEnum $eventType): void
    {
        $this->postUpdate([
            'update_id' => 1,
            $eventType->value => [
                'chat' => ['id' => -1001, 'type' => 'supergroup', 'title' => 'Группа'],
            ],
        ])->assertNoContent();

        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => -1001,
            'chat_name' => 'Группа',
            'last_event_type' => $eventType->value,
        ]);
    }

    /**
     * @return iterable<string, array{TelegramEventTypeEnum}>
     */
    public static function eventsWithChatInRoot(): iterable
    {
        foreach (TelegramEventTypeEnum::cases() as $eventType) {
            if ($eventType->chatPath() !== ['chat']) {
                continue;
            }

            yield $eventType->value => [$eventType];
        }
    }

    public function test_callback_query_takes_chat_from_message(): void
    {
        $this->postUpdate([
            'update_id' => 1,
            'callback_query' => [
                'id' => '42',
                'from' => ['id' => 320951812, 'is_bot' => false, 'first_name' => 'Spell'],
                'chat_instance' => '-1',
                'message' => [
                    'message_id' => 8290,
                    'date' => 1791140406,
                    'chat' => ['id' => -1002, 'type' => 'supergroup', 'title' => 'Кнопочная'],
                ],
            ],
        ])->assertNoContent();

        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => -1002,
            'chat_name' => 'Кнопочная',
            'last_event_type' => TelegramEventTypeEnum::CALLBACK_QUERY->value,
        ]);
    }

    public function test_poll_answer_takes_chat_from_voter_chat(): void
    {
        $this->postUpdate([
            'update_id' => 1,
            'poll_answer' => [
                'poll_id' => '1',
                'voter_chat' => ['id' => -1003, 'type' => 'channel', 'title' => 'Канал'],
                'option_ids' => [0],
            ],
        ])->assertNoContent();

        $this->assertDatabaseHas('telegram_channels', [
            'chat_id' => -1003,
            'chat_name' => 'Канал',
            'last_event_type' => TelegramEventTypeEnum::POLL_ANSWER->value,
        ]);
    }

    /**
     * Содержимое апдейта (имена, username, текст) не должно попадать в прод-логи,
     * а оттуда — в breadcrumbs Sentry.
     */
    public function test_payload_content_is_not_logged_when_debug_is_off(): void
    {
        Config::set('app.debug', false);

        $written = [];

        Log::listen(function (MessageLogged $message) use (&$written): void {
            $written[] = $message->message.' '.json_encode($message->context, JSON_UNESCAPED_UNICODE);
        });

        $this->postUpdate(json_decode(file_get_contents(base_path('new.json')), true))
            ->assertNoContent();

        $logged = implode("\n", $written);

        $this->assertStringNotContainsString('Посоны на апщении', $logged);
        $this->assertStringNotContainsString('Spell28', $logged);
        $this->assertStringNotContainsString('🤔', $logged);

        // Опознать апдейт по логам по-прежнему можно.
        $this->assertStringContainsString('879999772', $logged);
        $this->assertStringContainsString(TelegramEventTypeEnum::MESSAGE_REACTION->value, $logged);
        $this->assertStringContainsString('-1003807797608', $logged);
    }

    public function test_known_event_without_chat_is_skipped_without_writing(): void
    {
        // Неанонимный голос в опросе приходит без voter_chat — привязывать нечего.
        $this->postUpdate([
            'update_id' => 1,
            'poll_answer' => [
                'poll_id' => '1',
                'user' => ['id' => 320951812, 'is_bot' => false, 'first_name' => 'Spell'],
                'option_ids' => [0],
            ],
        ])->assertNoContent();

        $this->assertDatabaseCount('telegram_channels', 0);
    }
}