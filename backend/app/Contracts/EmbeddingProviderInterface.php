<?php

namespace App\Contracts;

interface EmbeddingProviderInterface
{
    /** @return list<float> */
    public function embed(string $text): array;
}
