<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group(name: 'AI', description: 'AI summaries and provider-backed note assistance.', weight: 40)]
class NoteSummaryStatusController extends Controller
{
    #[Endpoint(title: 'Get summary job status', description: 'Returns the safe processing state for a note summary job. Allows 60 requests per minute per client IP. The summary text is included only when status is completed.')]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 42)]
    #[Response(status: 200, description: 'Summary status.', type: 'array{success: bool, data: array{note_id: int, status: string, summary?: string}}', examples: [['success' => true, 'data' => ['note_id' => 42, 'status' => 'processing']]])]
    #[Response(status: 404, description: 'Note not found.', type: 'array{success: bool, message: string}')]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}')]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    public function __invoke(Note $note): JsonResponse
    {
        $data = [
            'note_id' => $note->id,
            'status' => $note->summary_status ?? ($note->summary ? 'completed' : 'not_started'),
        ];

        if ($data['status'] === 'completed') {
            $data['summary'] = $note->summary;
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
