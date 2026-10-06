<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\SendMessage;

use App\Domain\Telegram\Exception\TelegramChannelNotFoundException;
use App\Domain\Telegram\Gateway\TelegramMessageSenderInterface;
use App\Domain\Telegram\Repository\TelegramChannelsRepositoryInterface;

final readonly class SendMessageUseCase
{
    public function __construct(
        private TelegramChannelsRepositoryInterface $telegramChannelsRepository,
        private TelegramMessageSenderInterface $telegramMessageSender
    ) {
    }

    /**
     * На входе — идентификатор нашей записи о канале, а не chat_id: адрес отправки
     * берётся из базы. Так из админки нельзя заставить бота написать в произвольный чат.
     */
    public function execute(SendMessageInput $input): SendMessageResponse
    {
        $channel = $this->telegramChannelsRepository->findById($input->channelId);

        if ($channel === null) {
            throw TelegramChannelNotFoundException::byId($input->channelId);
        }

        $messageId = $this->telegramMessageSender->send($channel->chatId, $input->text);

        return new SendMessageResponse(
            chatId: $channel->chatId,
            chatName: $channel->chatName,
            messageId: $messageId,
        );
    }
}
