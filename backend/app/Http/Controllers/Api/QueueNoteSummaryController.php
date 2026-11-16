<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateNoteSummary;
use App\Models\Note;
use App\Services\NoteCacheService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Group(name: 'AI', description: 'AI summaries and provider-backed note assistance.', weight: 40)]
class QueueNoteSummaryController extends Controller
{
    #[Endpoint(
        title: 'Queue an AI summary',
        description: 'Queues summary generation in the background and returns HTTP 202. Allows 5 submissions per minute per client IP. Poll `/api/notes/{note}/summary/status` to check progress. The job retries provider failures up to three times with backoff.'
    )]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 42)]
    #[Response(status: 202, description: 'Summary job accepted.', type: 'array{success: bool, message: string, data: array{note_id: int, status: string}}', examples: [['success' => true, 'message' => 'Summary generation queued.', 'data' => ['note_id' => 42, 'status' => 'pending']]])]
    #[Response(status: 404, description: 'Note not found.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Resource not found.']])]
    #[Response(status: 409, description: 'A summary job is already pending or processing.', type: 'array{success: bool, message: string}')]
    #[Response(status: 422, description: 'The note has no content to summarize.', type: 'array{success: bool, message: string}')]
    #[Response(status: 429, description: 'Per-IP AI summary limit exceeded.', type: 'array{success: bool, message: string}')]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 503, description: 'The queue is temporarily unavailable.', type: 'array{success: bool, message: string}')]
    public function __invoke(Note $note, NoteCacheService $noteCache): JsonResponse
    {
        if (trim($note->content) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Note content is empty.',
            ], 422);
        }

        $queueStatus = DB::transaction(function () use ($note): string {
            $lockedNote = Note::query()->lockForUpdate()->find($note->id);

            if (! $lockedNote) {
                return 'missing';
            }

            if (in_array($lockedNote->summary_status, ['pending', 'processing'], true)) {
                return 'active';
            }

            $lockedNote->update(['summary_status' => 'pending']);

            return 'queued';
        });

        if ($queueStatus === 'missing') {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        if ($queueStatus === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Summary generation is already in progress.',
            ], 409);
        }

        $noteCache->invalidate();

        try {
            GenerateNoteSummary::dispatch($note->id);
        } catch (Throwable $exception) {
            $note->update(['summary_status' => 'failed']);
            $noteCache->invalidate();
            Log::error('Unable to queue note summary generation.', [
                'note_id' => $note->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue summary generation at this time.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Summary generation queued.',
            'data' => [
                'note_id' => $note->id,
                'status' => 'pending',
            ],
        ], 202);
    }
}
