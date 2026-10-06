<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository\Postgress\Telegram\TelegramChannels;

use App\Domain\Telegram\Entity\TelegramChannels;
use App\Domain\Telegram\Repository\TelegramChannelsRepositoryInterface;
use App\Models\TelegramChannel;

class TelegramChannelsRepository implements TelegramChannelsRepositoryInterface
{
    public function save(TelegramChannels $channel): TelegramChannels
    {
        // chat_id уникален: от одного канала вебхук приходит много раз,
        // поэтому вставка превращается в обновление уже известного канала.
        $model = TelegramChannel::query()->firstOrNew(['chat_id' => $channel->chatId]);

        // Тип события — повод, по которому канал попал в базу, а не его текущее
        // состояние: у существующей строки он остаётся тем, с которым она создана.
        if (!$model->exists) {
            $model->last_event_type = $channel->telegramEventType;
        }

        $model->chat_name = $channel->chatName;
        $model->save();

        return $this->toEntity($model);
    }

    public function findByChatId(int $chatId): ?TelegramChannels
    {
        $model = TelegramChannel::query()
            ->where('chat_id', $chatId)
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function findById(int $id): ?TelegramChannels
    {
        $model = TelegramChannel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    private function toEntity(TelegramChannel $model): TelegramChannels
    {
        return new TelegramChannels(
            telegramEventType: $model->last_event_type,
            chatId: $model->chat_id,
            chatName: $model->chat_name,
            id: $model->id,
        );
    }
}
