<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\NoteCacheService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Statistics', description: 'Live aggregate counts calculated from stored notes.', weight: 30)]
class NoteStatsController extends Controller
{
    #[Endpoint(title: 'Get note statistics', description: 'Returns live totals for notes, generated summaries, and notes created in the last seven days. Allows 60 requests per minute per client IP.')]
    #[Response(status: 200, description: 'Current note statistics.', type: 'array{data: array{total_notes: int, ai_summaries: int, recent_notes: int}}', examples: [['data' => ['total_notes' => 12, 'ai_summaries' => 4, 'recent_notes' => 3]]])]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function __invoke(NoteCacheService $noteCache): JsonResponse
    {
        $stats = $noteCache->rememberStats(fn () => [
            'total_notes' => Note::count(),

            'ai_summaries' => Note::whereNotNull('summary')
                ->where('summary', '!=', '')
                ->count(),

            'recent_notes' => Note::where(
                'created_at',
                '>=',
                now()->subDays(7)
            )->count(),
        ]);

        return response()->json(['data' => $stats]);
    }
}
