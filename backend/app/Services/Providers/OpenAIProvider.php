<?php

namespace App\Services\Providers;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AIProviderInterface
{
    public function generateSummary(string $content): string
    {
        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model');

        if (empty($apiKey) || empty($model)) {
            throw new RuntimeException(
                'OpenAI configuration is missing.'
            );
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'instructions' =>
                    'Summarize the note clearly. Preserve important facts and return only the summary.',
                'input' => $content,
            ]);

        $response->throw();

        $summary = collect($response->json('output', []))
            ->where('type', 'message')
            ->flatMap(fn ($item) => $item['content'] ?? [])
            ->where('type', 'output_text')
            ->pluck('text')
            ->implode("\n");

        if (trim($summary) === '') {
            throw new RuntimeException(
                'AI provider returned an empty summary.'
            );
        }

        return trim($summary);
    }
}
