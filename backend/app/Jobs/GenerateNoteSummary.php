<?php

namespace App\Jobs;

use App\Models\Note;
use App\Services\AIService;
use App\Services\NoteCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateNoteSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 30];

    public function __construct(public readonly int $noteId) {}

    public function handle(AIService $aiService, NoteCacheService $noteCache): void
    {
        $note = Note::find($this->noteId);

        if (! $note) {
            return;
        }

        $note->update(['summary_status' => 'processing']);
        $noteCache->invalidate();

        if (trim($note->content) === '') {
            throw new \InvalidArgumentException('Cannot summarize an empty note.');
        }

        $summary = $aiService->generateSummary($note->content);
        $note->update([
            'summary' => $summary,
            'summary_status' => 'completed',
        ]);
        $noteCache->invalidate();
    }

    public function failed(Throwable $exception): void
    {
        Note::whereKey($this->noteId)->update(['summary_status' => 'failed']);
        app(NoteCacheService::class)->invalidate();

        Log::error('Queued note summary generation failed.', [
            'note_id' => $this->noteId,
            'exception' => $exception::class,
        ]);
    }
}
