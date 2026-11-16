<?php

namespace App\Contracts;

interface AIProviderInterface
{
    public function generateSummary(string $content): string;
}
