<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\Models\Note;
use App\Services\Providers\OpenAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai.provider' => 'openai',
            'services.openai.api_key' => 'test-openai-secret',
            'services.openai.model' => 'test-openai-model',
        ]);
    }

    public function test_openai_provider_uses_responses_api_and_extracts_summary_text(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://api.openai.com/v1/responses', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-openai-secret'));
            $this->assertStringNotContainsString('test-openai-secret', $request->url());
            $this->assertSame('test-openai-model', $request->data()['model']);
            $this->assertSame('Summarize this note.', $request->data()['input']);

            return Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'A concise OpenAI summary.',
                    ]],
                ]],
            ]);
        });

        $provider = $this->app->make(AIProviderInterface::class);

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
        $this->assertSame('A concise OpenAI summary.', $provider->generateSummary('Summarize this note.'));
    }

    public function test_openai_provider_failures_keep_the_summary_api_response_safe(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Private provider error test-openai-secret'],
            ], 500),
        ]);
        Log::spy();
        $note = Note::create([
            'title' => 'OpenAI failure test',
            'content' => 'A note used to verify sanitized summary errors.',
        ]);

        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to generate summary at this time.')
            ->assertDontSee('test-openai-secret')
            ->assertDontSee('Private provider error');

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($message, $context) => $message === 'Note summary generation failed.'
                && $context === [
                    'note_id' => $note->id,
                    'exception' => RequestException::class,
                ]
        );
    }
}
