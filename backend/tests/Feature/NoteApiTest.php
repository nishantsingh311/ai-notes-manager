<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NoteApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_notes_pagination_works(): void
    {
        Note::factory()->count(15)->create();

        $response = $this->getJson('/api/notes?page=1&limit=10');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonCount(10, 'data.data');
    }

    public function test_note_summary_can_be_generated(): void
    {
        config(['services.ai.provider' => 'mock']);

        $note = Note::create([
            'title' => 'Laravel',
            'content' => 'Laravel is a PHP framework for web development.',
        ]);

        $response = $this->postJson(
            "/api/notes/{$note->id}/summary"
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'summary' => '[MOCK AI SUMMARY] Laravel is a PHP framework for web development.',
        ]);
    }

    public function test_semantic_search_finds_related_notes(): void
    {
        Http::fake(function ($request) {
            $input = $request->data()['input'];
            $vector = match ($input) {
                'Solar camping trip Camp off grid' => [0.8, 0.6],
                'Quarterly tax records Accountant budget' => [0.6, 0.8],
                'planning a weekend outdoors' => [1.0, 0.0],
                default => throw new \RuntimeException("Unexpected embedding input: {$input}"),
            };

            return Http::response(['embeddings' => [$vector]], 200);
        });

        $first = $this->postJson('/api/notes', [
            'title' => 'Solar camping trip',
            'content' => 'Camp off grid',
        ])->assertCreated()->assertJsonMissingPath('data.embedding');

        $second = $this->postJson('/api/notes', [
            'title' => 'Quarterly tax records',
            'content' => 'Accountant budget',
        ])->assertCreated();

        $response = $this->getJson(
            '/api/notes/search?q=planning+a+weekend+outdoors&limit=2'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.note.id', $first->json('data.id'))
            ->assertJsonPath('data.0.similarity_score', 0.8)
            ->assertJsonPath('data.1.note.id', $second->json('data.id'))
            ->assertJsonMissingPath('data.0.note.embedding');
    }

    public function test_semantic_search_excludes_weakly_related_notes(): void
    {
        config(['services.embeddings.min_similarity' => 0.35]);
        Note::create([
            'title' => 'Morning fitness routine',
            'content' => 'Running and exercise each morning',
            'embedding' => [0.1, 0.994987437],
        ]);

        Http::fake([
            '*' => Http::response(['embeddings' => [[1.0, 0.0]]], 200),
        ]);

        $this->getJson('/api/notes/search?q=buy+a+car')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_note_creation_and_update_store_provider_embeddings(): void
    {
        Http::fake(function ($request) {
            return Http::response([
                'embeddings' => [$request->data()['input'] === 'Before title Before content'
                    ? [1.0, 0.0]
                    : [0.0, 1.0]],
            ], 200);
        });

        $created = $this->postJson('/api/notes', [
            'title' => 'Before title',
            'content' => 'Before content',
        ])->assertCreated()->assertJsonMissingPath('data.embedding');

        $noteId = $created->json('data.id');
        $this->assertDatabaseHas('notes', [
            'id' => $noteId,
            'embedding' => json_encode([1.0, 0.0]),
        ]);

        $this->putJson("/api/notes/{$noteId}", [
            'title' => 'After title',
        ])->assertOk()->assertJsonMissingPath('data.embedding');

        $this->assertDatabaseHas('notes', [
            'id' => $noteId,
            'embedding' => json_encode([0.0, 1.0]),
        ]);
    }

    public function test_semantic_search_backfills_notes_with_null_embeddings(): void
    {
        $note = Note::create([
            'title' => 'Older note',
            'content' => 'No embedding stored yet',
        ]);

        Http::fake(function ($request) {
            $vector = $request->data()['input'] === 'search phrase'
                ? [1.0, 0.0]
                : [0.8, 0.6];

            return Http::response(['embeddings' => [$vector]], 200);
        });

        $this->getJson('/api/notes/search?q=search+phrase')
            ->assertOk()
            ->assertJsonPath('data.0.note.id', $note->id)
            ->assertJsonMissingPath('data.0.note.embedding');

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'embedding' => json_encode([0.8, 0.6]),
        ]);
    }

    public function test_embedding_provider_failure_returns_service_unavailable_without_fallback(): void
    {
        Http::fake(['*' => Http::response(['error' => 'model missing'], 404)]);

        $this->postJson('/api/notes', [
            'title' => 'Cannot be embedded',
            'content' => 'Provider is unavailable',
        ])->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The embedding service is temporarily unavailable.')
            ->assertDontSee('nomic-embed-text');

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_search_provider_failure_returns_safe_service_unavailable_response(): void
    {
        Http::fake(['*' => Http::response(['error' => 'internal host details'], 503)]);

        $this->getJson('/api/notes/search?q=provider+failure')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The embedding service is temporarily unavailable.')
            ->assertDontSee('internal host details')
            ->assertDontSee('127.0.0.1:11434');
    }

    public function test_semantic_search_requires_query(): void
    {
        $response = $this->getJson('/api/notes/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_note_and_search_inputs_are_validated(): void
    {
        $this->postJson('/api/notes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);

        $this->postJson('/api/notes', [
            'title' => str_repeat('t', 256),
            'content' => 'Valid content',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title']);

        $this->postJson('/api/notes', [
            'title' => 'Valid title',
            'content' => str_repeat('c', 50001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['content']);

        $note = Note::create(['title' => 'Existing note', 'content' => 'Existing content']);
        $this->putJson("/api/notes/{$note->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);

        $this->getJson('/api/notes?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);

        $this->getJson('/api/notes/search?q=x&limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'limit']);
    }

    public function test_api_rate_limits_return_clean_json_and_stricter_routes_are_limited(): void
    {
        config([
            'rate_limits.api' => 2,
            'rate_limits.semantic_search' => 1,
            'rate_limits.ai_summary' => 1,
        ]);
        Http::fake(['*' => Http::response(['embeddings' => [[1.0, 0.0]]], 200)]);

        $this->getJson('/api/notes/stats')->assertOk();
        $this->getJson('/api/notes/stats')->assertOk();
        $this->getJson('/api/notes/stats')
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please try again shortly.')
            ->assertHeader('Retry-After');

        $this->getJson('/api/notes/search?q=rate+limit')->assertOk();
        $this->getJson('/api/notes/search?q=rate+limit')
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        config(['services.ai.provider' => 'mock']);
        $note = Note::create(['title' => 'Rate limit', 'content' => 'Summary test']);
        $this->postJson("/api/notes/{$note->id}/summary")->assertOk();
        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_search_treats_sql_injection_like_query_as_plain_text(): void
    {
        $note = Note::create([
            'title' => 'Safe note',
            'content' => 'Stored note text',
            'embedding' => [1.0, 0.0],
        ]);
        Http::fake(['*' => Http::response(['embeddings' => [[1.0, 0.0]]], 200)]);

        $query = "' OR 1=1 --";
        $this->getJson('/api/notes/search?'.http_build_query(['q' => $query]))
            ->assertOk()
            ->assertJsonPath('data.0.note.id', $note->id);

        $this->assertDatabaseCount('notes', 1);
    }

    public function test_summary_provider_failure_is_safe_for_clients_and_logs(): void
    {
        config([
            'services.ai.provider' => 'openai',
            'services.openai.api_key' => 'sk-test-secret',
        ]);
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => ['message' => 'Provider failure sk-test-secret'],
            ], 500),
        ]);
        Log::spy();
        $note = Note::create(['title' => 'Provider failure', 'content' => 'Summary content']);

        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to generate summary at this time.')
            ->assertDontSee('sk-test-secret')
            ->assertDontSee('Provider failure');

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($message, $context) => $message === 'Note summary generation failed.'
                && $context === [
                    'note_id' => $note->id,
                    'exception' => RequestException::class,
                ]
        );
    }

    public function test_unexpected_api_errors_do_not_expose_exception_messages(): void
    {
        Route::get('/api/security-test-error', fn () => throw new \RuntimeException('database password leaked'));

        $this->getJson('/api/security-test-error')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'An unexpected server error occurred.')
            ->assertDontSee('database password leaked');
    }

    public function test_api_not_found_and_authentication_errors_keep_safe_status_codes(): void
    {
        $this->getJson('/api/notes/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertDontSee('App\\Models\\Note');

        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_show_and_delete_crud_endpoints_remain_functional(): void
    {
        $note = Note::create([
            'title' => 'Protected embedding',
            'content' => 'CRUD remains available',
            'embedding' => [0.25, 0.75],
        ]);

        $this->getJson("/api/notes/{$note->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Protected embedding')
            ->assertJsonMissingPath('data.embedding');

        $this->deleteJson("/api/notes/{$note->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_cors_allows_only_configured_frontend_origin(): void
    {
        config(['cors.allowed_origins' => ['https://notes.example.test']]);

        $this->withHeaders([
            'Origin' => 'https://notes.example.test',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/notes')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://notes.example.test');

        $this->withHeaders([
            'Origin' => 'https://attacker.example.test',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/notes')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://notes.example.test')
            ->assertDontSee('https://attacker.example.test');
    }
}
