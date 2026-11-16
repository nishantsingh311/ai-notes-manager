<?php

namespace App\Services\Providers;

use App\Contracts\AIProviderInterface;

class MockAIProvider implements AIProviderInterface
{
    public function generateSummary(string $content): string
    {
        $content = trim(preg_replace('/\s+/', ' ', $content));

        if ($content === '') {
            throw new \InvalidArgumentException(
                'Note content cannot be empty.'
            );
        }

        // Simulate a short summary using the first 35 words.
        $words = preg_split('/\s+/', $content);

        $summary = implode(' ', array_slice($words, 0, 35));

        if (count($words) > 35) {
            $summary .= '...';
        }

        return '[MOCK AI SUMMARY] ' . $summary;
    }
}
