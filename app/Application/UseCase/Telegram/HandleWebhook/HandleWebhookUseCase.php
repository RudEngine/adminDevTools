<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\HandleWebhook;

use App\Domain\Telegram\Entity\TelegramChannels;
use App\Domain\Telegram\Repository\TelegramChannelsRepositoryInterface;

final readonly class HandleWebhookUseCase
{
    public function __construct(
        private TelegramChannelsRepositoryInterface $telegramChannelsRepository
    ) {
    }

    public function execute(HandleWebhookInput $input): HandleWebhookResponse
    {
        $entity = new TelegramChannels(
            telegramEventType: $input->telegramEventType,
            chatId: $input->chatId,
            chatName: $input->chatName
        );

        $channel = $this->telegramChannelsRepository->save($entity);

        return new HandleWebhookResponse(
            channelId: (int) $channel->id,
            chatId: $channel->chatId,
            chatName: $channel->chatName,
        );
    }
}