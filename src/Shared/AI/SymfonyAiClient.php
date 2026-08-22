<?php

declare(strict_types=1);

namespace App\Shared\AI;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;

final readonly class SymfonyAiClient implements AiClient
{
    public function __construct(
        private PlatformInterface $anthropicPlatform,
        private PlatformInterface $geminiPlatform,
        private string $provider,
        private string $summaryModel,
    ) {
    }

    public function complete(string $systemPrompt, string $userPrompt, AiUseCase $useCase): string
    {
        $platform = match ($this->provider) {
            'anthropic' => $this->anthropicPlatform,
            'gemini' => $this->geminiPlatform,
            default => throw new \RuntimeException(sprintf('Unsupported AI_PROVIDER "%s". Use anthropic or gemini.', $this->provider)),
        };
        $model = $this->summaryModel;
        if (trim($model) === '') {
            throw new \RuntimeException('The configured AI model is empty.');
        }

        $options = ['temperature' => 0];
        if ($this->provider === 'gemini') {
            $options['maxOutputTokens'] = 900;
        } else {
            $options['max_tokens'] = 900;
        }
        $deferred = $platform->invoke($model, new MessageBag(Message::forSystem($systemPrompt), Message::ofUser($userPrompt)), $options);
        $result = $deferred->getResult();
        $text = match (true) {
            $result instanceof TextResult => $result->getContent(),
            $result instanceof MultiPartResult => $result->asText(),
            default => $deferred->asText(),
        };

        return trim($text);
    }
}
