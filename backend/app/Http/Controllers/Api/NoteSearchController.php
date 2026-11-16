<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmbeddingProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\NoteSearchRequest;
use App\Services\Search\SemanticSearchService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Search', description: 'Search notes by semantic meaning using Ollama text embeddings.', weight: 20)]
class NoteSearchController extends Controller
{
    #[Endpoint(
        title: 'Search notes semantically',
        description: 'Embeds the query with the configured Ollama model (`EMBEDDING_MODEL`, default `nomic-embed-text`) and ranks notes by cosine similarity. Notes below `EMBEDDING_MIN_SIMILARITY` are omitted. Allows 10 requests per minute per client IP.'
    )]
    #[QueryParameter('q', description: 'Natural language search query, 2–500 characters.', type: 'string', required: true, example: 'planning a weekend outdoors')]
    #[QueryParameter('limit', description: 'Maximum number of ranked results, from 1 to 100.', type: 'integer', default: 10, example: 5)]
    #[Response(status: 200, description: 'Ranked semantic matches. Similarity scores are cosine similarity; embedding vectors are not returned.', type: 'array{success: bool, message: string, data: list<array{note: \\App\\Models\\Note, similarity_score: float}>}', examples: [['success' => true, 'message' => 'Semantic search completed successfully.', 'data' => [['note' => ['id' => 42, 'title' => 'Camping plans', 'content' => 'Ideas for an outdoor weekend.', 'summary' => null, 'created_at' => '2026-11-16T10:00:00.000000Z', 'updated_at' => '2026-11-16T10:00:00.000000Z'], 'similarity_score' => 0.82]]]])]
    #[Response(status: 422, description: 'Query or limit failed validation.', type: 'array{message: string, errors: array<string, list<string>>}', examples: [['message' => 'The q field must be at least 2 characters.', 'errors' => ['q' => ['The q field must be at least 2 characters.']]]])]
    #[Response(status: 429, description: 'Per-IP semantic search limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 503, description: 'Ollama or the configured embedding model is unavailable.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'The embedding service is temporarily unavailable.']])]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function __invoke(
        NoteSearchRequest $request,
        SemanticSearchService $searchService
    ): JsonResponse {

        $validated = $request->validated();

        try {
            $results = $searchService->search($validated['q'], $validated['limit'] ?? 10);
        } catch (EmbeddingProviderException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'The embedding service is temporarily unavailable.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Semantic search completed successfully.',
            'data' => $results,
        ], 200);
    }
}
