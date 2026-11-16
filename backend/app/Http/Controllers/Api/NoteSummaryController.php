<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Services\AIService;
use App\Services\NoteCacheService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Group(name: 'AI', description: 'AI summaries and provider-backed note assistance.', weight: 40)]
class NoteSummaryController extends Controller
{
    #[Endpoint(
        title: 'Generate an AI summary',
        description: 'Generates and saves a note summary. `AI_PROVIDER=mock` returns a deterministic preview prefixed with `[MOCK AI SUMMARY]`; it is not model-generated. Set `AI_PROVIDER=openai` with `OPENAI_MODEL` and `OPENAI_API_KEY`, or `AI_PROVIDER=gemini` with `GEMINI_MODEL` and `GEMINI_API_KEY`, in the server environment for a real model-generated summary. This route allows 5 requests per minute per client IP.'
    )]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 42)]
    #[Response(status: 200, description: 'Generated summary saved to the note.', type: 'array{success: bool, message: string, data: array{note_id: int, summary: string}}', examples: [['success' => true, 'message' => 'Summary generated successfully.', 'data' => ['note_id' => 42, 'summary' => 'A concise overview of the note.']]])]
    #[Response(status: 404, description: 'Note not found.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Resource not found.']])]
    #[Response(status: 422, description: 'The note content is empty.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Note content is empty.']])]
    #[Response(status: 429, description: 'Per-IP AI summary limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 502, description: 'The configured AI summary provider is unavailable or failed.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Unable to generate summary at this time.']])]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function __invoke(
        Note $note,
        AIService $aiService
    ): JsonResponse {
        if (trim($note->content) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Note content is empty.',
            ], 422);
        }

        try {
            $summary = $aiService->generateSummary(
                $note->content
            );

            $note->update([
                'summary' => $summary,
                'summary_status' => 'completed',
            ]);

            app(NoteCacheService::class)->invalidate();

            return response()->json([
                'success' => true,
                'message' => 'Summary generated successfully.',
                'data' => [
                    'note_id' => $note->id,
                    'summary' => $summary,
                ],
            ], 200);

        } catch (Throwable $e) {
            Log::error('Note summary generation failed.', [
                'note_id' => $note->id,
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate summary at this time.',
            ], 502);
        }
    }
}
