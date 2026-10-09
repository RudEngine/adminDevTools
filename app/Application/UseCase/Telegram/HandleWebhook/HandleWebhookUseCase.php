<?php
declare(strict_types=1);

namespace App\Application\UseCase\Telegram\HandleWebhook;

use App\Domain\Telegram\Cache\TelegramChannelsCacheInterface;
use App\Domain\Telegram\Entity\TelegramChannels;
use App\Domain\Telegram\Queue\BotMentionQueueInterface;
use App\Domain\Telegram\Repository\TelegramChannelsRepositoryInterface;

final readonly class HandleWebhookUseCase
{
    public function __construct(
        private TelegramChannelsRepositoryInterface $telegramChannelsRepository,
        private TelegramChannelsCacheInterface $telegramChannelsCache,
        private BotMentionQueueInterface $botMentionQueue
    ) {
    }

    public function execute(HandleWebhookInput $input): HandleWebhookResponse
    {
        $channel = $this->storeChannel(new TelegramChannels(
            telegramEventType: $input->telegramEventType,
            chatId: $input->chatId,
            chatName: $input->chatName
        ));

        // Отвечает на упоминание очередь: запрос к LLM не должен держать вебхук.
        if ($input->mention !== null) {
            $this->botMentionQueue->push($input->mention);
        }

        return new HandleWebhookResponse(
            channelId: (int) $channel->id,
            chatId: $channel->chatId,
            chatName: $channel->chatName,
            mentionQueued: $input->mention !== null,
        );
    }

    /**
     * От активного чата события идут потоком, и подавляющая часть их — с тем же именем
     * чата. Пока кэш подтверждает, что в базе лежит ровно это состояние, запрос в базу
     * не нужен: UPDATE записал бы те же значения. Тип события на это не влияет — он
     * фиксируется при создании записи и дальше не перезаписывается.
     *
     * Как только что-то разошлось (чат переименовали, ключ истёк или его сбросили) —
     * пишем в базу и запоминаем новое состояние.
     */
    private function storeChannel(TelegramChannels $incoming): TelegramChannels
    {
        $stored = $this->telegramChannelsCache->get($incoming->chatId);

        if ($stored !== null && $stored->isStored() && $stored->hasSameStateAs($incoming)) {
            return $stored;
        }

        $channel = $this->telegramChannelsRepository->save($incoming);

        $this->telegramChannelsCache->put($channel);

        return $channel;
    }
}