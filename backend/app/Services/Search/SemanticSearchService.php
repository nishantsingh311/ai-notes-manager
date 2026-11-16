<?php

namespace App\Services\Search;

use App\Models\Note;

class SemanticSearchService
{
    public function __construct(
        private TextEmbeddingService $embeddingService
    ) {}

    public function search(string $query, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $minSimilarity = max(-1.0, min(1.0, (float) config('services.embeddings.min_similarity', 0.35)));
        $queryVector = $this->embeddingService->generate($query);

        $notes = Note::latest()->get();

        $results = [];

        foreach ($notes as $note) {

            $noteVector = $note->embedding;

            // Older releases stored sparse hash vectors. Replace them as well
            // as null vectors when a genuine provider vector is available.
            if (empty($noteVector)
                || ! array_is_list($noteVector)
                || count($noteVector) !== count($queryVector)) {
                $noteVector = $this->embeddingService->generate(
                    $note->title.' '.$note->content
                );

                $note->forceFill(['embedding' => $noteVector])->save();
            }

            $score = $this->embeddingService->similarity(
                $queryVector,
                $noteVector
            );

            if ($score >= $minSimilarity) {
                $results[] = [
                    'note' => $note,
                    'similarity_score' => round($score, 4),
                ];
            }
        }

        // Highest similarity first
        usort(
            $results,
            fn ($a, $b) => $b['similarity_score'] <=> $a['similarity_score']
        );

        return array_slice($results, 0, $limit);
    }
}
