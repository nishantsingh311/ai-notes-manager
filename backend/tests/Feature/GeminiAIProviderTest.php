<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\Models\Note;
use App\Services\Providers\GeminiAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GeminiAIProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai.provider' => 'gemini',
            'services.gemini.api_key' => 'test-gemini-secret',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.timeout' => 15,
        ]);
    }

    public function test_gemini_provider_uses_official_generate_content_api_and_returns_text_parts(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-model:generateContent',
                $request->url()
            );
            $this->assertTrue($request->hasHeader('x-goog-api-key', 'test-gemini-secret'));
            $this->assertStringNotContainsString('test-gemini-secret', $request->url());
            $this->assertSame(
                'Summarize this note.',
                $request->data()['contents'][0]['parts'][0]['text']
            );
            $this->assertSame(
                'low',
                $request->data()['generationConfig']['thinkingConfig']['thinkingLevel']
            );

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [
                            ['text' => 'A concise summary.'],
                            ['text' => 'Hidden reasoning.', 'thought' => true],
                            ['text' => 'Important detail.'],
                        ],
                    ],
                ]],
            ]);
        });

        $provider = $this->app->make(AIProviderInterface::class);

        $this->assertInstanceOf(GeminiAIProvider::class, $provider);
        $this->assertSame(
            "A concise summary.\nImportant detail.",
            $provider->generateSummary('Summarize this note.')
        );
    }

    public function test_gemini_summary_can_be_generated_through_existing_api_without_changing_response_shape(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Gemini note summary.']]],
                ]],
            ]),
        ]);
        $note = Note::create([
            'title' => 'Gemini integration',
            'content' => 'Text to summarize with Gemini.',
        ]);

        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Summary generated successfully.')
            ->assertJsonPath('data.note_id', $note->id)
            ->assertJsonPath('data.summary', 'Gemini note summary.')
            ->assertJsonMissingPath('data.embedding');

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'summary' => 'Gemini note summary.',
        ]);
    }

    public function test_gemini_rate_limit_errors_are_sanitized(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Provider details test-gemini-secret'],
            ], 429),
        ]);

        try {
            app(GeminiAIProvider::class)->generateSummary('A note.');
            $this->fail('A rate-limited Gemini response should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gemini rate limit reached. Please try again later.', $exception->getMessage());
            $this->assertStringNotContainsString('test-gemini-secret', $exception->getMessage());
            $this->assertStringNotContainsString('Provider details', $exception->getMessage());
        }
    }

    public function test_gemini_authentication_errors_do_not_expose_provider_details_or_api_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Invalid key test-gemini-secret'],
            ], 401),
        ]);

        try {
            app(GeminiAIProvider::class)->generateSummary('A note.');
            $this->fail('An unsuccessful Gemini response should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gemini rejected the request (HTTP 401).', $exception->getMessage());
            $this->assertStringNotContainsString('test-gemini-secret', $exception->getMessage());
            $this->assertStringNotContainsString('Invalid key', $exception->getMessage());
        }
    }

    public function test_gemini_server_errors_do_not_expose_provider_response_body(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Private Google service details'],
            ], 503),
        ]);

        try {
            app(GeminiAIProvider::class)->generateSummary('A note.');
            $this->fail('A Gemini server error should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gemini service is temporarily unavailable.', $exception->getMessage());
            $this->assertStringNotContainsString('Private Google service details', $exception->getMessage());
        }
    }

    public function test_gemini_connection_errors_are_sanitized_without_previous_exception(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        try {
            app(GeminiAIProvider::class)->generateSummary('A note.');
            $this->fail('A connection failure should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gemini service could not be reached or timed out.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('test-gemini-secret', $exception->getMessage());
        }
    }

    public function test_gemini_invalid_response_is_sanitized(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => 'private provider details'])]);

        try {
            app(GeminiAIProvider::class)->generateSummary('A note.');
            $this->fail('An invalid Gemini response should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gemini returned an empty summary.', $exception->getMessage());
            $this->assertStringNotContainsString('private provider details', $exception->getMessage());
        }
    }

    public function test_missing_gemini_configuration_fails_without_sending_a_request(): void
    {
        config(['services.gemini.api_key' => '']);
        Http::fake();

        try {
            app(GeminiAIProvider::class)->generateSummary('A note.');
            $this->fail('Missing Gemini configuration should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gemini configuration is missing.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_unknown_ai_provider_does_not_silently_fall_back_to_mock(): void
    {
        config(['services.ai.provider' => 'gemnii']);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(AIProviderInterface::class);
    }

    public function test_gemini_failure_keeps_the_existing_safe_summary_api_response_and_log(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Private response test-gemini-secret'],
            ], 429),
        ]);
        Log::spy();
        $note = Note::create([
            'title' => 'Gemini failure',
            'content' => 'Text to summarize.',
        ]);

        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to generate summary at this time.')
            ->assertDontSee('test-gemini-secret')
            ->assertDontSee('Private response');

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($message, $context) => $message === 'Note summary generation failed.'
                && $context === [
                    'note_id' => $note->id,
                    'exception' => RuntimeException::class,
                ]
        );
    }
}
