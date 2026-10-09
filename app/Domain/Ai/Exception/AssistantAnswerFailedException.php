<?php
declare(strict_types=1);

namespace App\Domain\Ai\Exception;

use RuntimeException;
use Throwable;

class AssistantAnswerFailedException extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self("LLM не ответила: {$reason}", previous: $previous);
    }
}
