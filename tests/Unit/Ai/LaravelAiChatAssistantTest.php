<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Domain\Ai\Exception\AssistantAnswerFailedException;
use App\Infrastructure\Ai\LaravelAi\LaravelAiChatAssistant;
use App\Infrastructure\Ai\LaravelAi\TelegramChatAgent;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;
use Tests\TestCase;

class LaravelAiChatAssistantTest extends TestCase
{
    public function test_returns_trimmed_agent_answer(): void
    {
        TelegramChatAgent::fake(["  Четыре\n"]);

        $this->assertSame('Четыре', (new LaravelAiChatAssistant())->answer('Сколько будет 2+2?'));

        TelegramChatAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Сколько будет 2+2?');
    }

    public function test_empty_answer_is_a_failure(): void
    {
        TelegramChatAgent::fake(['   ']);

        $this->expectException(AssistantAnswerFailedException::class);

        (new LaravelAiChatAssistant())->answer('Привет');
    }

    public function test_provider_error_becomes_domain_exception(): void
    {
        TelegramChatAgent::fake(fn () => throw new RuntimeException('Connection refused'));

        $this->expectException(AssistantAnswerFailedException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        (new LaravelAiChatAssistant())->answer('Привет');
    }
}
