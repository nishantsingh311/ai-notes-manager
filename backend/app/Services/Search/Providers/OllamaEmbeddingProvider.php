<?php

namespace App\Services\Search\Providers;

use App\Contracts\EmbeddingProviderInterface;
use App\Exceptions\EmbeddingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        $baseUrl = rtrim((string) config('services.embeddings.base_url'), '/');
        $model = (string) config('services.embeddings.model');

        if ($baseUrl === '' || $model === '') {
            throw new EmbeddingProviderException(
                'Embedding provider is not configured. Set EMBEDDING_BASE_URL and EMBEDDING_MODEL.'
            );
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.embeddings.timeout', 30))
                ->post($baseUrl.'/api/embed', [
                    'model' => $model,
                    'input' => $text,
                ]);

            if (! $response->successful()) {
                throw new EmbeddingProviderException(
                    "Ollama embedding request failed with HTTP {$response->status()}. Ensure the model '{$model}' is available."
                );
            }

            $embedding = $response->json('embeddings.0');

            if (! is_array($embedding) || $embedding === []) {
                throw new EmbeddingProviderException(
                    'Ollama returned an invalid embedding response. Check the configured model.'
                );
            }

            return $embedding;
        } catch (ConnectionException $exception) {
            throw new EmbeddingProviderException(
                "Cannot connect to Ollama at {$baseUrl}. Start Ollama and pull the '{$model}' model.",
                previous: $exception
            );
        } catch (EmbeddingProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new EmbeddingProviderException(
                'The embedding provider returned an unusable response.',
                previous: $exception
            );
        }
    }
}
