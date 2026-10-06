<?php
declare(strict_types=1);

namespace App\Domain\Telegram\Cache;

use App\Domain\Telegram\Entity\TelegramChannels;

/**
 * Снимок уже сохранённого канала: что именно, по нашим данным, лежит в базе.
 *
 * Нужен не для чтения данных приложением, а чтобы не повторять запись тем же значением.
 * Промах кэша всегда безопасен — он просто возвращает поведение к записи в базу.
 */
interface TelegramChannelsCacheInterface
{
    public function get(int $chatId): ?TelegramChannels;

    public function put(TelegramChannels $channel): void;

    public function forget(int $chatId): void;
}