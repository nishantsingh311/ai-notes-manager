<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;

class AIService
{
    public function __construct(
        private AIProviderInterface $provider
    ) {}

    public function generateSummary(string $content): string
    {
        return $this->provider->generateSummary($content);
    }
}
