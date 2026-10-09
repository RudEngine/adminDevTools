<?php

declare(strict_types=1);

namespace Tests\Support\Ai;

use App\Domain\Ai\Exception\AssistantAnswerFailedException;
use App\Domain\Ai\Gateway\ChatAssistantInterface;

/**
 * Подменяет LLM: отвечает заданным текстом или падает так же, как адаптер.
 */
final class FakeChatAssistant implements ChatAssistantInterface
{
    /** @var list<string> */
    public array $questions = [];

    public function __construct(
        private readonly string $answer = 'Ответ',
        private readonly ?string $failWith = null,
    ) {
    }

    public function answer(string $question): string
    {
        $this->questions[] = $question;

        if ($this->failWith !== null) {
            throw AssistantAnswerFailedException::because($this->failWith);
        }

        return $this->answer;
    }
}
