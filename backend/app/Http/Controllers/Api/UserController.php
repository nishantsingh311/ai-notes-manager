<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;

#[Group(name: 'Authentication', description: 'Authenticated account endpoints.', weight: 5)]
class UserController extends Controller
{
    #[Endpoint(title: 'Get the authenticated user', description: 'Returns the current Sanctum-authenticated user. Requires a bearer token. Allows 60 requests per minute per client IP.')]
    #[Response(status: 200, description: 'Authenticated user profile. Password and remember token are hidden.', type: '\\App\\Models\\User', examples: [['id' => 7, 'name' => 'Avery Morgan', 'email' => 'avery@example.test', 'email_verified_at' => null, 'created_at' => '2026-11-16T10:00:00.000000Z', 'updated_at' => '2026-11-16T10:00:00.000000Z']])]
    #[Response(status: 401, description: 'A valid Sanctum bearer token is required.', type: 'array{message: string}', examples: [['message' => 'Unauthenticated.']])]
    #[Response(status: 429, description: 'Per-IP API rate limit exceeded.', type: 'array{success: bool, message: string}', examples: [['success' => false, 'message' => 'Too many requests. Please try again shortly.']])]
    #[Header(name: 'Retry-After', status: 429, type: 'integer', description: 'Seconds to wait before retrying.')]
    public function __invoke(Request $request): User
    {
        return $request->user();
    }
}
