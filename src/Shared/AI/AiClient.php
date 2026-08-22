<?php

namespace App\Shared\AI;

interface AiClient
{
    public function complete(string $systemPrompt, string $userPrompt, AiUseCase $useCase): string;
}
