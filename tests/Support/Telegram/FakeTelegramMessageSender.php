<?php

declare(strict_types=1);

namespace Tests\Support\Telegram;

use App\Domain\Telegram\Exception\TelegramMessageNotSentException;
use App\Domain\Telegram\Gateway\TelegramMessageSenderInterface;

/**
 * Подменяет реальную отправку в телеграмм: запоминает вызовы и, если попросили,
 * падает так же, как падал бы адаптер при ошибке Bot API.
 */
final class FakeTelegramMessageSender implements TelegramMessageSenderInterface
{
    /** @var list<array{chatId: int, text: string, replyTo: int|null}> */
    public array $sent = [];

    /** @var list<array{chatId: int, messageId: int, text: string}> */
    public array $edited = [];

    public function __construct(
        private readonly int $messageId = 1,
        private readonly ?string $failWith = null,
    ) {
    }

    public function send(int $chatId, string $text, ?int $replyToMessageId = null): int
    {
        if ($this->failWith !== null) {
            throw new TelegramMessageNotSentException($this->failWith);
        }

        $this->sent[] = ['chatId' => $chatId, 'text' => $text, 'replyTo' => $replyToMessageId];

        return $this->messageId;
    }

    public function edit(int $chatId, int $messageId, string $text): void
    {
        $this->edited[] = ['chatId' => $chatId, 'messageId' => $messageId, 'text' => $text];
    }
}
