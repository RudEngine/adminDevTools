<?php
declare(strict_types=1);

namespace App\Infrastructure\Queue\Telegram;

use App\Application\UseCase\Telegram\AnswerMention\AnswerMentionUseCase;
use App\Domain\Telegram\Entity\BotMention;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class AnswerBotMentionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Без повторов: каждая попытка заново отправляет заглушку «печатает», и при
     * ретраях в чате остались бы лишние сообщения. Неудачу use case уже показал в чате.
     */
    public int $tries = 1;

    /**
     * Секунды. Больше таймаута запроса к LLM (telegram.mention.timeout), чтобы задача
     * успела заменить заглушку текстом ошибки, и меньше retry_after очереди (90),
     * иначе воркер выдал бы задачу второй раз, пока первая ещё идёт.
     */
    public int $timeout = 80;

    public function __construct(public readonly BotMention $mention)
    {
    }

    public function handle(AnswerMentionUseCase $useCase): void
    {
        $useCase->execute($this->mention);
    }
}
