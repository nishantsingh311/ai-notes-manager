<?php

namespace App\Services\Search;

use App\Contracts\EmbeddingProviderInterface;
use App\Exceptions\EmbeddingProviderException;

class TextEmbeddingService
{
    public function __construct(
        private EmbeddingProviderInterface $provider
    ) {}

    /** @return list<float> */
    public function generate(string $text): array
    {
        if (trim($text) === '') {
            throw new EmbeddingProviderException('Cannot create an embedding from empty text.');
        }

        $embedding = $this->provider->embed($text);
        if ($embedding === [] || ! array_is_list($embedding)) {
            throw new EmbeddingProviderException('The embedding provider returned an invalid vector.');
        }

        $vector = [];
        foreach ($embedding as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new EmbeddingProviderException('The embedding provider returned non-numeric vector values.');
            }
            $vector[] = (float) $value;
        }

        if ($this->magnitude($vector) === 0.0) {
            throw new EmbeddingProviderException('The embedding provider returned a zero vector.');
        }

        return $vector;
    }

    public function similarity(array $first, array $second): float
    {
        if ($first === [] || $second === [] || count($first) !== count($second)) {
            throw new EmbeddingProviderException(
                'Embedding dimensions do not match. Re-embed existing notes using the configured model.'
            );
        }

        $dotProduct = 0.0;
        foreach ($first as $index => $value) {
            $dotProduct += (float) $value * (float) $second[$index];
        }

        $denominator = $this->magnitude($first) * $this->magnitude($second);

        return $denominator === 0.0 ? 0.0 : $dotProduct / $denominator;
    }

    private function magnitude(array $vector): float
    {
        return sqrt(array_sum(array_map(
            fn ($value) => ((float) $value) ** 2,
            $vector
        )));
    }
}
