<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Application\UseCase\Telegram\SendMessage\SendMessageInput;
use App\Application\UseCase\Telegram\SendMessage\SendMessageUseCase;
use App\Domain\Enum\TelegramEventTypeEnum;
use App\Domain\Telegram\Entity\TelegramChannels;
use App\Domain\Telegram\Exception\TelegramChannelNotFoundException;
use App\Domain\Telegram\Exception\TelegramMessageNotSentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Telegram\FakeTelegramMessageSender;
use Tests\Support\Telegram\InMemoryTelegramChannelsRepository;

class SendMessageUseCaseTest extends TestCase
{
    private function repository(): InMemoryTelegramChannelsRepository
    {
        return new InMemoryTelegramChannelsRepository([
            new TelegramChannels(
                telegramEventType: TelegramEventTypeEnum::MESSAGE,
                chatId: -1003807797608,
                chatName: 'Посоны на апщении',
                id: 7,
            ),
        ]);
    }

    public function test_sends_text_to_chat_of_requested_channel(): void
    {
        $sender = new FakeTelegramMessageSender(messageId: 42);

        $response = (new SendMessageUseCase($this->repository(), $sender))
            ->execute(new SendMessageInput(channelId: 7, text: 'Привет, посоны'));

        $this->assertSame(
            [['chatId' => -1003807797608, 'text' => 'Привет, посоны']],
            $sender->sent
        );
        $this->assertSame(42, $response->messageId);
        $this->assertSame(-1003807797608, $response->chatId);
        $this->assertSame('Посоны на апщении', $response->chatName);
    }

    public function test_fails_when_channel_is_unknown(): void
    {
        $sender = new FakeTelegramMessageSender();

        $this->expectException(TelegramChannelNotFoundException::class);

        try {
            (new SendMessageUseCase($this->repository(), $sender))
                ->execute(new SendMessageInput(channelId: 404, text: 'Привет'));
        } finally {
            // Неизвестный канал не должен превратиться в отправку куда-нибудь ещё.
            $this->assertSame([], $sender->sent);
        }
    }

    public function test_propagates_sender_failure(): void
    {
        $sender = new FakeTelegramMessageSender(failWith: 'Bot API: chat not found');

        $this->expectException(TelegramMessageNotSentException::class);
        $this->expectExceptionMessage('Bot API: chat not found');

        (new SendMessageUseCase($this->repository(), $sender))
            ->execute(new SendMessageInput(channelId: 7, text: 'Привет'));
    }
}
