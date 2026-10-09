<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\AnswerMention;

use App\Domain\Ai\Exception\AssistantAnswerFailedException;
use App\Domain\Ai\Gateway\ChatAssistantInterface;
use App\Domain\Telegram\Entity\BotMention;
use App\Domain\Telegram\Gateway\TelegramMessageSenderInterface;
use Illuminate\Support\Str;

final readonly class AnswerMentionUseCase
{
    public function __construct(
        private TelegramMessageSenderInterface $telegramMessageSender,
        private ChatAssistantInterface $chatAssistant
    ) {
    }

    /**
     * Модель отвечает долго, поэтому сначала отвечаем на упоминание заглушкой
     * «печатает», а когда ответ готов — подменяем её текст. Так в чате сразу видно,
     * что бот услышал, и ответ остаётся одним сообщением, а не двумя.
     *
     * Если модель не ответила, заглушка не должна висеть вечно: меняем её на текст
     * ошибки и пробрасываем исключение дальше — в логи и failed_jobs.
     */
    public function execute(BotMention $mention): void
    {
        $placeholderId = $this->telegramMessageSender->send(
            $mention->chatId,
            (string) config('telegram.mention.typing_text'),
            replyToMessageId: $mention->messageId,
        );

        try {
            $answer = $this->chatAssistant->answer($mention->question);
        } catch (AssistantAnswerFailedException $e) {
            $this->telegramMessageSender->edit(
                $mention->chatId,
                $placeholderId,
                (string) config('telegram.mention.error_text'),
            );

            throw $e;
        }

        $this->telegramMessageSender->edit(
            $mention->chatId,
            $placeholderId,
            Str::limit($answer, TelegramMessageSenderInterface::MAX_TEXT_LENGTH - 1, '…'),
        );
    }
}
