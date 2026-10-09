<?php
declare(strict_types=1);

namespace App\Domain\Ai\Gateway;

use App\Domain\Ai\Exception\AssistantAnswerFailedException;

/**
 * Языковая модель, которая отвечает на вопрос из чата. Какая модель и через что
 * к ней ходим — дело инфраструктуры.
 */
interface ChatAssistantInterface
{
    /**
     * @throws AssistantAnswerFailedException если модель недоступна или ответила пустотой
     */
    public function answer(string $question): string;
}
