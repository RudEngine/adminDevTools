<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTelegramWebhookToken;
use App\Infrastructure\Queue\Telegram\AnswerBotMentionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramBotMentionTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'test-webhook-secret';

    private const int CHAT_ID = -1003807797608;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('nutgram.webhook_secret', self::SECRET);
        Config::set('telegram.mention.bot_username', 'PosonyBot');

        Queue::fake();
    }

    /**
     * @param list<array<string, mixed>> $entities
     */
    private function postMessage(string $text, array $entities, string $textKey = 'text'): void
    {
        $domain = config('app.api_domain');

        $this->postJson($domain ? 'http://'.$domain.'/webhook' : '/webhook', [
            'update_id' => 1,
            'message' => [
                'message_id' => 8290,
                'date' => 1791140406,
                'chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup', 'title' => 'Посоны на апщении'],
                $textKey => $text,
                ($textKey === 'text' ? 'entities' : 'caption_entities') => $entities,
            ],
        ], [VerifyTelegramWebhookToken::HEADER => self::SECRET])->assertNoContent();
    }

    private function assertQueuedQuestion(string $question): void
    {
        Queue::assertPushed(AnswerBotMentionJob::class, function (AnswerBotMentionJob $job) use ($question): bool {
            return $job->mention->chatId === self::CHAT_ID
                && $job->mention->messageId === 8290
                && $job->mention->question === $question;
        });
    }

    public function test_mention_is_queued_without_username_in_question(): void
    {
        $this->postMessage('@PosonyBot сколько будет 2+2?', [
            ['type' => 'mention', 'offset' => 0, 'length' => 10],
        ]);

        $this->assertQueuedQuestion('сколько будет 2+2?');
        $this->assertDatabaseHas('telegram_channels', ['chat_id' => self::CHAT_ID]);
    }

    public function test_mention_offsets_are_counted_in_utf16(): void
    {
        // 🤔 — два UTF-16 code unit'а: упоминание начинается с 6, а в символах было бы 5.
        $this->postMessage('🤔 эй @posonybot, как дела?', [
            ['type' => 'mention', 'offset' => 6, 'length' => 10],
        ]);

        $this->assertQueuedQuestion('🤔 эй , как дела?');
    }

    public function test_mention_in_media_caption_is_queued(): void
    {
        $this->postMessage('что на фото, @PosonyBot', [
            ['type' => 'mention', 'offset' => 13, 'length' => 10],
        ], textKey: 'caption');

        $this->assertQueuedQuestion('что на фото,');
    }

    public function test_mention_of_someone_else_is_ignored(): void
    {
        $this->postMessage('@Spell28 привет', [
            ['type' => 'mention', 'offset' => 0, 'length' => 8],
        ]);

        Queue::assertNothingPushed();
    }

    public function test_bare_mention_without_question_is_ignored(): void
    {
        $this->postMessage('@PosonyBot', [
            ['type' => 'mention', 'offset' => 0, 'length' => 10],
        ]);

        Queue::assertNothingPushed();
    }

    public function test_mentions_are_ignored_without_configured_username(): void
    {
        Config::set('telegram.mention.bot_username', null);

        $this->postMessage('@PosonyBot привет', [
            ['type' => 'mention', 'offset' => 0, 'length' => 10],
        ]);

        Queue::assertNothingPushed();
    }
}
