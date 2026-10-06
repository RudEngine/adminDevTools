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
    /** @var list<array{chatId: int, text: string}> */
    public array $sent = [];

    public function __construct(
        private readonly int $messageId = 1,
        private readonly ?string $failWith = null,
    ) {
    }

    public function send(int $chatId, string $text): int
    {
        if ($this->failWith !== null) {
            throw new TelegramMessageNotSentException($this->failWith);
        }

        $this->sent[] = ['chatId' => $chatId, 'text' => $text];

        return $this->messageId;
    }
}
