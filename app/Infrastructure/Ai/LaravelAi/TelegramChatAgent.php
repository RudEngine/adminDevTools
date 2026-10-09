<?php
declare(strict_types=1);

namespace App\Infrastructure\Ai\LaravelAi;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Агент laravel/ai, который отвечает в чатах телеграмм. Провайдер и модель — по
 * умолчанию из config/ai.php, системный промпт — из config/telegram.php.
 */
final class TelegramChatAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return (string) config('telegram.mention.instructions');
    }
}
