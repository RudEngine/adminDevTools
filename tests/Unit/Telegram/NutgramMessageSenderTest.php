<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Domain\Telegram\Exception\TelegramMessageNotSentException;
use App\Infrastructure\Telegram\Nutgram\NutgramMessageSender;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SergiX44\Nutgram\Nutgram;

class NutgramMessageSenderTest extends TestCase
{
    private const int CHAT_ID = -1003807797608;

    public function test_sends_text_to_given_chat_and_returns_message_id(): void
    {
        $bot = Nutgram::fake();
        $bot->willReceive(['message_id' => 99, 'date' => 0, 'chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup']]);

        $messageId = (new NutgramMessageSender($bot))->send(self::CHAT_ID, 'Привет, посоны');

        $this->assertSame(99, $messageId);
        $bot->assertCalled('sendMessage');
        $bot->assertReplyMessage([
            'chat_id' => self::CHAT_ID,
            'text' => 'Привет, посоны',
        ]);
    }

    public function test_turns_telegram_error_into_domain_exception(): void
    {
        // Причину отказа Bot API кладёт в description верхнего уровня, поэтому ответ
        // собираем целиком, а не через willReceive() — тот оборачивает всё в result.
        $bot = Nutgram::fake(responses: [
            new Response(400, [], json_encode([
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(TelegramMessageNotSentException::class);
        $this->expectExceptionMessageMatches('/chat not found/');

        (new NutgramMessageSender($bot))->send(self::CHAT_ID, 'Привет');
    }
}
