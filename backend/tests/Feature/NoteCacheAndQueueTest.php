<?php

namespace Tests\Feature;

use App\Jobs\GenerateNoteSummary;
use App\Models\Note;
use App\Services\AIService;
use App\Services\NoteCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NoteCacheAndQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_note_list_and_statistics_cache_are_invalidated_after_creation(): void
    {
        Note::create(['title' => 'Existing', 'content' => 'Existing content']);

        $this->getJson('/api/notes?page=1&limit=10')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
        $this->getJson('/api/notes/stats')->assertJsonPath('data.total_notes', 1);

        // A direct database insert confirms the response really is cached.
        Note::create(['title' => 'Inserted directly', 'content' => 'Not yet visible in the cache']);
        $this->getJson('/api/notes?page=1&limit=10')
            ->assertJsonCount(1, 'data.data');
        $this->getJson('/api/notes/stats')->assertJsonPath('data.total_notes', 1);

        Http::fake(['*' => Http::response(['embeddings' => [[0.1, 0.2]]], 200)]);
        $this->postJson('/api/notes', [
            'title' => 'Created through API',
            'content' => 'Cache should be invalidated',
        ])->assertCreated();

        $this->getJson('/api/notes?page=1&limit=10')
            ->assertJsonCount(3, 'data.data');
        $this->getJson('/api/notes/stats')->assertJsonPath('data.total_notes', 3);

        $noteId = $this->getJson('/api/notes?page=1&limit=10')->json('data.data.0.id');
        $this->putJson("/api/notes/{$noteId}", ['title' => 'Updated through API'])
            ->assertOk();
        $this->getJson('/api/notes?page=1&limit=10')
            ->assertJsonFragment(['title' => 'Updated through API']);

        $this->deleteJson("/api/notes/{$noteId}")->assertOk();
        $this->getJson('/api/notes?page=1&limit=10')
            ->assertJsonCount(2, 'data.data');
        $this->getJson('/api/notes/stats')->assertJsonPath('data.total_notes', 2);
    }

    public function test_note_list_cache_keys_include_page_and_limit(): void
    {
        Note::factory()->count(12)->create();

        $this->getJson('/api/notes?page=1&limit=10')
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonCount(10, 'data.data');
        $this->getJson('/api/notes?page=2&limit=10')
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonCount(2, 'data.data');
        $this->getJson('/api/notes?page=1&limit=5')
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonCount(5, 'data.data');
    }

    public function test_paginated_note_cache_can_be_serialized_without_php_objects(): void
    {
        config([
            'cache.default' => 'file',
            'cache.stores.file.path' => sys_get_temp_dir().'/ai-notes-manager-cache-test',
        ]);
        Note::factory()->count(2)->create();

        $this->getJson('/api/notes?page=1&limit=10')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
        $this->getJson('/api/notes?page=1&limit=10')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        cache()->store('file')->flush();
    }

    public function test_cache_failover_uses_file_store_if_redis_is_unavailable(): void
    {
        config([
            'cache.default' => 'failover',
            'cache.stores.failover.stores' => ['redis', 'file'],
            'database.redis.client' => 'phpredis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,
            'database.redis.default.max_retries' => 0,
        ]);

        cache()->put('redis-failover-check', 'available', 10);

        $this->assertSame('available', cache()->get('redis-failover-check'));
    }

    public function test_async_summary_endpoint_queues_job_and_exposes_status(): void
    {
        Queue::fake();
        $note = Note::create(['title' => 'Queue me', 'content' => 'Summary content']);

        $this->postJson("/api/notes/{$note->id}/summary/async")
            ->assertAccepted()
            ->assertJsonPath('data.note_id', $note->id)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(GenerateNoteSummary::class, fn ($job) => $job->noteId === $note->id);
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'summary_status' => 'pending']);
        $this->postJson("/api/notes/{$note->id}/summary/async")
            ->assertStatus(409);
        $this->getJson("/api/notes/{$note->id}/summary/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.summary');
    }

    public function test_summary_job_saves_generated_summary_and_marks_completion(): void
    {
        $note = Note::create(['title' => 'Complete me', 'content' => 'Some note text', 'summary_status' => 'pending']);
        $aiService = $this->mock(AIService::class);
        $aiService->shouldReceive('generateSummary')->once()->with('Some note text')->andReturn('Generated summary');

        (new GenerateNoteSummary($note->id))->handle($aiService, app(NoteCacheService::class));

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'summary' => 'Generated summary',
            'summary_status' => 'completed',
        ]);
        $this->getJson("/api/notes/{$note->id}/summary/status")
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.summary', 'Generated summary');
    }

    public function test_failed_summary_job_marks_note_failed_without_leaking_provider_error(): void
    {
        $note = Note::create(['title' => 'Fail me', 'content' => 'Some note text', 'summary_status' => 'processing']);

        (new GenerateNoteSummary($note->id))->failed(new RuntimeException('secret provider detail'));

        $this->assertDatabaseHas('notes', ['id' => $note->id, 'summary_status' => 'failed']);
        $this->getJson("/api/notes/{$note->id}/summary/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonMissingPath('data.summary')
            ->assertDontSee('secret provider detail');
        $this->assertSame(3, (new GenerateNoteSummary($note->id))->tries);
        $this->assertSame([5, 30], (new GenerateNoteSummary($note->id))->backoff);
    }
}
