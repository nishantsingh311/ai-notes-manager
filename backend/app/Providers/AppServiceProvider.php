<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\EmbeddingProviderInterface;
use App\Documentation\AddNotePatchOperation;
use App\Exceptions\EmbeddingProviderException;
use App\Services\Providers\GeminiAIProvider;
use App\Services\Providers\MockAIProvider;
use App\Services\Providers\OpenAIProvider;
use App\Services\Search\Providers\OllamaEmbeddingProvider;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            AIProviderInterface::class,
            function ($app) {
                return match (config('services.ai.provider', 'mock')) {
                    'openai' => $app->make(OpenAIProvider::class),
                    'gemini' => $app->make(GeminiAIProvider::class),
                    'mock' => $app->make(MockAIProvider::class),
                    default => throw new InvalidArgumentException(
                        'Unsupported AI summary provider. Configure AI_PROVIDER as mock, openai, or gemini.'
                    ),
                };
            }
        );

        $this->app->bind(
            EmbeddingProviderInterface::class,
            function ($app) {
                return match (config('services.embeddings.provider', 'ollama')) {
                    'ollama' => $app->make(OllamaEmbeddingProvider::class),
                    default => throw new EmbeddingProviderException(
                        'Unsupported embedding provider. Configure EMBEDDING_PROVIDER=ollama or register a provider implementation.'
                    ),
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::configure()->withDocumentTransformers(AddNotePatchOperation::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(
            max(1, (int) config('rate_limits.api', 60))
        )->by($request->ip()));

        RateLimiter::for('semantic-search', fn (Request $request) => Limit::perMinute(
            max(1, (int) config('rate_limits.semantic_search', 10))
        )->by($request->ip()));

        RateLimiter::for('ai-summary', fn (Request $request) => Limit::perMinute(
            max(1, (int) config('rate_limits.ai_summary', 5))
        )->by($request->ip()));
    }
}
