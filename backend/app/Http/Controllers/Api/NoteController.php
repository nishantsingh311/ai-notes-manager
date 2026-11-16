<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmbeddingProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\NoteIndexRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Note;
use App\Services\NoteService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Notes', description: 'Create, browse, update, and delete notes.', weight: 10)]
class NoteController extends Controller
{
    private NoteService $noteService;

    public function __construct(NoteService $noteService)
    {
        $this->noteService = $noteService;
    }

    #[Endpoint(
        title: 'List notes',
        description: 'Returns notes newest first using Laravel pagination. `limit` defaults to 10 and is capped at 100. This route allows 60 requests per minute per client IP.'
    )]
    #[QueryParameter('page', description: 'Page number, starting at 1.', type: 'integer', default: 1, example: 1)]
    #[QueryParameter('limit', description: 'Notes per page, from 1 to 100.', type: 'integer', default: 10, example: 10)]
    #[Response(
        status: 200,
        description: 'Paginated notes. Note embedding vectors are omitted.',
        type: 'array{success: bool, message: string, data: array{current_page: int, data: list<\\App\\Models\\Note>, first_page_url: string, from: ?int, last_page: int, last_page_url: string, links: list<array{url: ?string, label: string, active: bool}>, next_page_url: ?string, path: string, per_page: int, prev_page_url: ?string, to: ?int, total: int}}',
        examples: [[
            'success' => true,
            'message' => 'Notes fetched successfully',
            'data' => [
                'current_page' => 1,
                'data' => [['id' => 42, 'title' => 'Project ideas', 'content' => 'Explore a local-first notes workflow.', 'summary' => null, 'created_at' => '2026-11-16T10:00:00.000000Z', 'updated_at' => '2026-11-16T10:00:00.000000Z']],
                'first_page_url' => 'http://localhost/api/notes?page=1',
                'from' => 1,
                'last_page' => 1,
                'last_page_url' => 'http://localhost/api/notes?page=1',
                'links' => [],
                'next_page_url' => null,
                'path' => 'http://localhost/api/notes',
                'per_page' => 10,
                'prev_page_url' => null,
                'to' => 1,
                'total' => 1,
            ],
        ]]
    )]
    #[Response(status: 422, description: 'Invalid pagination parameter.', type: 'array{message: string, errors: array<string, list<string>>}', examples: [['message' => 'The limit field must be at most 100.', 'errors' => ['limit' => ['The limit field must be at most 100.']]]])]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function index(NoteIndexRequest $request): JsonResponse
    {
        $limit = $request->validated('limit', 10);

        $notes = $this->noteService->getAllNotes($limit);

        return response()->json([
            'success' => true,
            'message' => 'Notes fetched successfully',
            'data' => $notes,
        ], 200);
    }

    // Create note
    #[Endpoint(
        title: 'Create a note',
        description: 'Creates a note and generates a genuine embedding from its title and content using the configured Ollama model. Returns HTTP 503 if the embedding provider is unavailable. This route allows 60 requests per minute per client IP.'
    )]
    #[BodyParameter('title', description: 'Note title. Required, up to 255 characters.', type: 'string', example: 'Project ideas')]
    #[BodyParameter('content', description: 'Note content. Required, up to 50,000 characters.', type: 'string', example: 'Explore a local-first notes workflow.')]
    #[Response(status: 201, description: 'Note created. The embedding field is intentionally omitted.', type: 'array{success: bool, message: string, data: \\App\\Models\\Note}', examples: [['success' => true, 'message' => 'Note created successfully', 'data' => ['id' => 42, 'title' => 'Project ideas', 'content' => 'Explore a local-first notes workflow.', 'summary' => null, 'created_at' => '2026-11-16T10:00:00.000000Z', 'updated_at' => '2026-11-16T10:00:00.000000Z']]])]
    #[Response(status: 422, description: 'Validation failed. Title is required and limited to 255 characters; content is required and limited to 50,000 characters.', type: 'array{message: string, errors: array<string, list<string>>}', examples: [['message' => 'The title field is required.', 'errors' => ['title' => ['The title field is required.']]]])]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 503, description: 'Embedding provider is unavailable. No note is created.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'The embedding service is temporarily unavailable.']])]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function store(StoreNoteRequest $request): JsonResponse
    {
        try {
            $note = $this->noteService->createNote($request->validated());
        } catch (EmbeddingProviderException $exception) {
            return $this->embeddingError($exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully',
            'data' => $note,
        ], 201);
    }

    // Get single note
    #[Endpoint(title: 'Get a note', description: 'Returns one note. The `{note}` path segment is the integer note ID; its stored embedding vector is hidden.')]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 42)]
    #[Response(status: 200, description: 'Note details without the embedding vector.', type: 'array{success: bool, data: \\App\\Models\\Note}', examples: [['success' => true, 'data' => ['id' => 42, 'title' => 'Project ideas', 'content' => 'Explore a local-first notes workflow.', 'summary' => null, 'created_at' => '2026-11-16T10:00:00.000000Z', 'updated_at' => '2026-11-16T10:00:00.000000Z']]])]
    #[Response(status: 404, description: 'Note not found.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Resource not found.']])]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function show(Note $note): JsonResponse
    {
        $note = $this->noteService->getNote($note);

        return response()->json([
            'success' => true,
            'data' => $note,
        ], 200);
    }

    // Update note
    #[Endpoint(title: 'Update a note', description: 'Partially updates a note and regenerates its embedding from the resulting title and content. At least one valid field is required. This route allows 60 requests per minute per client IP.')]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 42)]
    #[BodyParameter('title', description: 'Optional replacement title, up to 255 characters.', type: 'string', example: 'Updated project ideas')]
    #[BodyParameter('content', description: 'Optional replacement content, up to 50,000 characters.', type: 'string', example: 'Review the local-first notes workflow.')]
    #[Response(status: 200, description: 'Updated note without the embedding vector.', type: 'array{success: bool, message: string, data: \\App\\Models\\Note}', examples: [['success' => true, 'message' => 'Note updated successfully', 'data' => ['id' => 42, 'title' => 'Updated project ideas', 'content' => 'Review the local-first notes workflow.', 'summary' => null, 'created_at' => '2026-11-16T10:00:00.000000Z', 'updated_at' => '2026-11-16T10:15:00.000000Z']]])]
    #[Response(status: 404, description: 'Note not found.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Resource not found.']])]
    #[Response(status: 422, description: 'Validation failed or no update fields were supplied.', type: 'array{message: string, errors: array<string, list<string>>}', examples: [['message' => 'Provide at least one field to update.', 'errors' => ['title' => ['Provide at least one field to update.'], 'content' => ['Provide at least one field to update.']]]])]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 503, description: 'Embedding provider is unavailable. The note is not updated.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'The embedding service is temporarily unavailable.']])]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function update(
        UpdateNoteRequest $request,
        Note $note
    ): JsonResponse {
        try {
            $updatedNote = $this->noteService->updateNote($note, $request->validated());
        } catch (EmbeddingProviderException $exception) {
            return $this->embeddingError($exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => $updatedNote,
        ], 200);
    }

    // Delete note
    #[Endpoint(title: 'Delete a note', description: 'Permanently deletes a note. This route allows 60 requests per minute per client IP.')]
    #[PathParameter('note', description: 'Note ID.', type: 'integer', example: 42)]
    #[Response(status: 200, description: 'Note deleted.', type: 'array{success: bool, message: string}', examples: [['success' => true, 'message' => 'Note deleted successfully']])]
    #[Response(status: 404, description: 'Note not found.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Resource not found.']])]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    #[Response(status: 500, description: 'Unexpected server error.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'An unexpected server error occurred.']])]
    public function destroy(Note $note): JsonResponse
    {
        $this->noteService->deleteNote($note);

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully',
        ], 200);
    }

    private function embeddingError(EmbeddingProviderException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The embedding service is temporarily unavailable.',
        ], 503);
    }
}
