<?php

namespace Tests\Unit;

use App\Contracts\EmbeddingProviderInterface;
use App\Exceptions\EmbeddingProviderException;
use App\Services\Search\TextEmbeddingService;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TextEmbeddingService::class)]
class TextEmbeddingServiceTest extends TestCase
{
    public function test_cosine_similarity_uses_dot_product_and_vector_magnitudes(): void
    {
        $service = new TextEmbeddingService(Mockery::mock(EmbeddingProviderInterface::class));

        $this->assertEqualsWithDelta(0.96, $service->similarity([3, 4], [4, 3]), 0.000001);
        $this->assertSame(0.0, $service->similarity([1, 0], [0, 1]));
        $this->assertSame(-1.0, $service->similarity([1, 0], [-1, 0]));
    }

    public function test_cosine_similarity_rejects_different_dimensions(): void
    {
        $service = new TextEmbeddingService(Mockery::mock(EmbeddingProviderInterface::class));

        $this->expectException(EmbeddingProviderException::class);
        $service->similarity([1, 0], [1, 0, 0]);
    }

    public function test_generated_embedding_comes_from_configured_provider(): void
    {
        $provider = Mockery::mock(EmbeddingProviderInterface::class);
        $provider->shouldReceive('embed')->once()->with('note content')->andReturn([0.25, 0.75]);

        $this->assertSame([0.25, 0.75], (new TextEmbeddingService($provider))->generate('note content'));
    }
}
