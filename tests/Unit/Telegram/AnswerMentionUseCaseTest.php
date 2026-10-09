<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Application\UseCase\Telegram\AnswerMention\AnswerMentionUseCase;
use App\Domain\Ai\Exception\AssistantAnswerFailedException;
use App\Domain\Telegram\Entity\BotMention;
use Illuminate\Support\Facades\Config;
use Tests\Support\Ai\FakeChatAssistant;
use Tests\Support\Telegram\FakeTelegramMessageSender;
use Tests\TestCase;

class AnswerMentionUseCaseTest extends TestCase
{
    private const int CHAT_ID = -1003807797608;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('telegram.mention.typing_text', 'Печатает…');
        Config::set('telegram.mention.error_text', 'Не получилось');
    }

    private function mention(): BotMention
    {
        return new BotMention(chatId: self::CHAT_ID, messageId: 8290, question: 'Сколько будет 2+2?');
    }

    public function test_replies_with_placeholder_then_replaces_it_with_answer(): void
    {
        $sender = new FakeTelegramMessageSender(messageId: 77);
        $assistant = new FakeChatAssistant(answer: 'Четыре');

        (new AnswerMentionUseCase($sender, $assistant))->execute($this->mention());

        $this->assertSame(
            [['chatId' => self::CHAT_ID, 'text' => 'Печатает…', 'replyTo' => 8290]],
            $sender->sent
        );
        $this->assertSame(['Сколько будет 2+2?'], $assistant->questions);
        $this->assertSame(
            [['chatId' => self::CHAT_ID, 'messageId' => 77, 'text' => 'Четыре']],
            $sender->edited
        );
    }

    public function test_replaces_placeholder_with_error_text_when_llm_fails(): void
    {
        $sender = new FakeTelegramMessageSender(messageId: 77);

        try {
            (new AnswerMentionUseCase($sender, new FakeChatAssistant(failWith: 'timeout')))
                ->execute($this->mention());
            $this->fail('Ожидали AssistantAnswerFailedException');
        } catch (AssistantAnswerFailedException) {
        }

        $this->assertSame(
            [['chatId' => self::CHAT_ID, 'messageId' => 77, 'text' => 'Не получилось']],
            $sender->edited
        );
    }

    public function test_cuts_answer_to_telegram_limit(): void
    {
        $sender = new FakeTelegramMessageSender();

        (new AnswerMentionUseCase($sender, new FakeChatAssistant(answer: str_repeat('я', 5000))))
            ->execute($this->mention());

        $this->assertSame(4096, mb_strlen($sender->edited[0]['text']));
        $this->assertStringEndsWith('…', $sender->edited[0]['text']);
    }
}
