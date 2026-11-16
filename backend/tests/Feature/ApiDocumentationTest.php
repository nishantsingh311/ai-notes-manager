<?php

namespace Tests\Feature;

use App\Documentation\AddNotePatchOperation;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentation_ui_and_openapi_spec_are_available(): void
    {
        $this->app['env'] = 'local';
        $this->assertContains(AddNotePatchOperation::class, Scramble::configure()->afterOpenApiGenerated());

        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('AI Notes Manager API');

        $response = $this->getJson('/docs/api.json')->assertOk();
        $spec = $response->json();

        $this->assertStringStartsWith('3.1', $spec['openapi']);
        $this->assertSame('AI Notes Manager API', $spec['info']['title'] ?? null);

        $expectedOperations = [
            '/notes' => ['get', 'post'],
            '/notes/{note}' => ['get', 'put', 'patch', 'delete'],
            '/notes/search' => ['get'],
            '/notes/stats' => ['get'],
            '/notes/{note}/summary' => ['post'],
            '/notes/{note}/summary/async' => ['post'],
            '/notes/{note}/summary/status' => ['get'],
            '/user' => ['get'],
        ];

        foreach ($expectedOperations as $path => $methods) {
            $this->assertArrayHasKey($path, $spec['paths']);
            foreach ($methods as $method) {
                $this->assertArrayHasKey($method, $spec['paths'][$path]);
            }
        }

        $tags = array_column($spec['tags'], 'name');
        foreach (['Authentication', 'Notes', 'Search', 'Statistics', 'AI'] as $tag) {
            $this->assertContains($tag, $tags);
        }

        $search = $spec['paths']['/notes/search']['get'];
        $parameterNames = array_column($search['parameters'], 'name');
        $this->assertContains('q', $parameterNames);
        $this->assertContains('limit', $parameterNames);
        $this->assertArrayHasKey('429', $search['responses']);
        $this->assertArrayHasKey('Retry-After', $search['responses']['429']['headers']);

        $this->assertArrayHasKey('429', $spec['paths']['/notes']['get']['responses']);
        $this->assertArrayHasKey('503', $spec['paths']['/notes']['post']['responses']);
        $this->assertArrayHasKey('502', $spec['paths']['/notes/{note}/summary']['post']['responses']);
        $this->assertArrayHasKey('202', $spec['paths']['/notes/{note}/summary/async']['post']['responses']);
        $this->assertArrayHasKey('429', $spec['paths']['/notes/{note}/summary/status']['get']['responses']);
        $this->assertStringNotContainsString('"embedding"', json_encode($spec['components']['schemas'] ?? [], JSON_THROW_ON_ERROR));
        $this->assertSame('bearer', $spec['components']['securitySchemes']['http']['scheme']);
    }
}
