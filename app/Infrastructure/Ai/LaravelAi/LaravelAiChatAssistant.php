<?php
declare(strict_types=1);

namespace App\Infrastructure\Ai\LaravelAi;

use App\Domain\Ai\Exception\AssistantAnswerFailedException;
use App\Domain\Ai\Gateway\ChatAssistantInterface;
use Throwable;

/**
 * Единственное место, которое знает про laravel/ai: выше по стеку есть только
 * порт ChatAssistantInterface.
 */
final readonly class LaravelAiChatAssistant implements ChatAssistantInterface
{
    public function answer(string $question): string
    {
        try {
            $response = TelegramChatAgent::make()->prompt(
                $question,
                timeout: (int) config('telegram.mention.timeout'),
            );
        } catch (Throwable $e) {
            throw AssistantAnswerFailedException::because($e->getMessage(), $e);
        }

        $answer = trim($response->text);

        if ($answer === '') {
            throw AssistantAnswerFailedException::because('пустой ответ');
        }

        return $answer;
    }
}
