<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enum\TelegramEventTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Запись о телеграмм-канале (чате/супергруппе), от которого пришёл вебхук.
 *
 * @property int $id
 * @property int $chat_id
 * @property string $chat_name
 * @property TelegramEventTypeEnum $last_event_type
 */
#[Fillable(['chat_id', 'chat_name', 'last_event_type'])]
class TelegramChannel extends Model
{
    protected $table = 'telegram_channels';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'last_event_type' => TelegramEventTypeEnum::class,
        ];
    }
}