<?php

namespace App\Services;

use App\Models\Note;
use App\Services\Search\TextEmbeddingService;

class NoteService
{
    public function __construct(
        private TextEmbeddingService $embeddingService,
        private NoteCacheService $noteCache
    ) {}

    public function getAllNotes(
        int $limit = 10
    ): array {
        $page = max(1, (int) request()->query('page', 1));

        return $this->noteCache->rememberList(
            $page,
            $limit,
            fn () => Note::latest()->paginate($limit, ['*'], 'page', $page)->toArray()
        );
    }

    public function createNote(array $data): Note
    {
        $data['embedding'] = $this->embeddingService->generate(
            $data['title'].' '.$data['content']
        );

        $note = Note::create($data);
        $this->noteCache->invalidate();

        return $note;
    }

    public function getNote(Note $note): Note
    {
        return $note;
    }

    public function updateNote(
        Note $note,
        array $data
    ): Note {
        $title = $data['title'] ?? $note->title;
        $content = $data['content'] ?? $note->content;

        $data['embedding'] = $this->embeddingService->generate(
            $title.' '.$content
        );

        $note->update($data);
        $this->noteCache->invalidate();

        return $note->fresh();
    }

    public function deleteNote(Note $note): bool
    {
        $deleted = $note->delete();
        $this->noteCache->invalidate();

        return $deleted;
    }
}
