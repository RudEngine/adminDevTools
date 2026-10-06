<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Repository;

use App\Domain\Telegram\Entity\TelegramChannels;

interface TelegramChannelsRepositoryInterface
{
    /**
     * Сохраняет канал: создаёт новый или обновляет существующий с тем же chatId.
     * Возвращает сущность с присвоенным идентификатором.
     */
    public function save(TelegramChannels $channel): TelegramChannels;

    public function findByChatId(int $chatId): ?TelegramChannels;

    public function findById(int $id): ?TelegramChannels;
}