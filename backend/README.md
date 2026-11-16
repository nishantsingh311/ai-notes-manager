<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Semantic search embeddings

Semantic search uses genuine text embeddings from a configurable provider. The default is the local Ollama provider, which does not require OpenAI credentials or paid API credits. Note title and content are embedded when a note is created or updated. Search embeds the query, compares vectors with cosine similarity, and returns the closest notes first. Old notes with null or legacy sparse hash vectors are re-embedded when they are searched. Embedding vectors are hidden from all note API responses.

### Run Ollama locally

Install Ollama from [ollama.com/download](https://ollama.com/download) (macOS 14 or later for the macOS app), start the Ollama application or server, then pull the embedding model:

```bash
ollama pull nomic-embed-text
```

Ollama must be reachable from Laravel at `http://127.0.0.1:11434`. Verify the model is installed with `ollama list`, then check the embedding endpoint:

```bash
curl http://127.0.0.1:11434/api/embed \
  -H 'Content-Type: application/json' \
  -d '{"model":"nomic-embed-text","input":"A note about planning a weekend outdoors"}'
```

The endpoint should return an `embeddings` array containing a dense vector. Ollama's `/api/embed` endpoint and `nomic-embed-text` model are documented in the [Ollama model library](https://ollama.com/library/nomic-embed-text).

Configure the Laravel backend in `.env`:

```dotenv
EMBEDDING_PROVIDER=ollama
EMBEDDING_MODEL=nomic-embed-text
EMBEDDING_BASE_URL=http://127.0.0.1:11434
EMBEDDING_TIMEOUT=30
EMBEDDING_MIN_SIMILARITY=0.35
```

Run `php artisan config:clear` after changing these values if configuration is cached. The API keeps the existing search response shape:

```http
GET /api/notes/search?q=planning+a+weekend+outdoors&limit=5
```

Successful responses contain `success`, `message`, and `data`; each data item contains a note and its `similarity_score`. Set `limit` between 1 and 100. Results below `EMBEDDING_MIN_SIMILARITY` are excluded; the default cutoff is `0.35` and can be adjusted for your model and notes. If Ollama is stopped or the model is missing, create, update, and search requests that need embeddings return HTTP 503 with a setup message. The application does not switch to hash vectors. If you change embedding models, re-embed the existing notes before relying on search results, especially when the models produce vectors with the same dimensions.

To use a different embedding backend, implement `App\Contracts\EmbeddingProviderInterface` and register it for the corresponding `EMBEDDING_PROVIDER` value in `AppServiceProvider`.

## AI summary providers

Summary generation uses the existing `AIProviderInterface`. Select a provider with `AI_PROVIDER`:

- `mock` (default) returns a deterministic preview marked `[MOCK AI SUMMARY]`; it is not model-generated.
- `openai` uses the existing OpenAI provider and `OPENAI_API_KEY` / `OPENAI_MODEL` settings.
- `gemini` uses Google's Gemini `generateContent` REST API.

To enable Gemini, create an API key in [Google AI Studio](https://aistudio.google.com/app/apikey) and set these values in the backend `.env`:

```dotenv
AI_PROVIDER=gemini
GEMINI_API_KEY=your-server-side-api-key
GEMINI_MODEL=gemini-3.8-flash
GEMINI_TIMEOUT=120
```

The provider sends the key in Google's `x-goog-api-key` request header. It reads summary text from Gemini's candidate content parts, enforces a request timeout, and converts network, rate-limit, provider, and invalid-response failures to safe exceptions. API responses and application logs do not include the API key or provider response body. The official API request format is documented in Google's [Generate content REST reference](https://ai.google.dev/api/generate-content) and the available model IDs are listed in its [models guide](https://ai.google.dev/gemini-api/docs/models).

After changing `.env`, run `php artisan config:clear`. With Docker Compose, set `AI_PROVIDER`, `GEMINI_API_KEY`, `GEMINI_MODEL`, and `GEMINI_TIMEOUT` in `.env.docker`, then recreate the backend and queue containers:

```bash
docker compose --env-file .env.docker up -d --force-recreate backend queue
```

Ollama remains the separate embedding provider for semantic search; changing `AI_PROVIDER` only selects the summary provider.

## API security and limits

The API uses Laravel's named rate limiters, keyed by client IP:

- Notes CRUD, stats, and the authenticated user endpoint: 60 requests per minute.
- Semantic search: 10 requests per minute.
- AI summary generation: 5 requests per minute.

Limits are configurable with `API_RATE_LIMIT_PER_MINUTE`, `SEMANTIC_SEARCH_RATE_LIMIT_PER_MINUTE`, and `AI_SUMMARY_RATE_LIMIT_PER_MINUTE`. A blocked request returns HTTP 429 JSON with a generic message and a `Retry-After` header.

Note create and update use Form Requests. Titles are limited to 255 characters, note content to 50,000 characters, search text to 500 characters, and pagination/search limits to 1–100. Updates must include at least one field. Database access uses Eloquent; search input is sent to the embedding provider as text and is not interpolated into SQL. Embedding vectors are hidden by the Note model.

CORS applies to API paths and allows only origins listed in `CORS_ALLOWED_ORIGINS` (comma-separated for multiple exact origins), falling back to `FRONTEND_ORIGIN`. For local Vite development, set `FRONTEND_ORIGIN=http://localhost:5173`; in deployment, set it to the exact React site origin. Credentials are disabled. `.env` is ignored by Git; keep API keys and other secrets there, not in source files. Provider failures return generic client messages, while summary logs record the note ID and exception class without the exception text.

## OpenAPI documentation

The API reference is generated from Laravel routes, Form Requests, Scramble attributes, and controller response types. In the local environment, start Laravel with `php artisan serve` and open:

- Interactive API reference: `http://127.0.0.1:8000/docs/api`
- OpenAPI 3.1 JSON: `http://127.0.0.1:8000/docs/api.json`

The documentation includes note CRUD, semantic search, statistics, AI summaries, and the authenticated `/api/user` endpoint. `PUT` and `PATCH` are both shown for note updates. Request validation is inferred from Form Requests; endpoint descriptions, response examples, status codes, tags, and rate-limit headers are maintained with Scramble attributes on the controllers. The OpenAPI document does not include API keys or embedding vectors.

Scramble exposes its UI and JSON only in `APP_ENV=local` by default. For another environment, define a `viewApiDocs` Gate with the access policy appropriate to that deployment. To export a checked-in or CI artifact, run `php artisan scramble:export --path=storage/app/openapi.json`. Scramble also provides `php artisan scramble:cache` and `php artisan scramble:clear` for deployment cache management.

## Redis caching and background summaries

Laravel's default `failover` cache tries Redis then Laravel's local file cache. The database queue remains the local default so background jobs continue working if Redis is stopped. To move queue jobs to Redis, set `QUEUE_CONNECTION=redis` in `.env` after starting Redis. Redis host, port, username, password, database IDs, and URL are configured only through `.env` (`REDIS_*`).

### Install and start Redis on macOS

With Homebrew installed:

```bash
brew install redis
brew services start redis
redis-cli ping
```

`redis-cli ping` should print `PONG`. The Homebrew service starts Redis in the background. Set `CACHE_STORE=failover` in `.env` (the `.env.example` default). Keep `QUEUE_CONNECTION=database` for local work without Redis, or set `QUEUE_CONNECTION=redis` to use Redis for queued jobs. Then clear cached Laravel configuration:

```bash
php artisan config:clear
```

This project uses Laravel's pure-PHP Predis client, so a PHP Redis extension is not required. Composer installs Predis with the backend dependencies; `REDIS_CLIENT=predis` selects it. If you choose the faster native client instead, install and enable `phpredis` for the PHP binary used by Artisan, then set `REDIS_CLIENT=phpredis`.

Notes list responses are cached for 60 seconds per page and limit; note statistics are cached for 30 seconds. Creating, updating, or deleting a note invalidates all cached note-list and statistics results immediately. Summary completion also invalidates them.

### Queue an AI summary

The existing synchronous `POST /api/notes/{id}/summary` endpoint remains available. For long-running providers, submit to `POST /api/notes/{id}/summary/async`; it returns HTTP 202 and a `pending` status. Poll `GET /api/notes/{id}/summary/status` for `pending`, `processing`, `completed`, or `failed`. The summary text is returned only after completion. Provider failures are retried three times with 5- and 30-second backoff, then the status becomes `failed`; client responses and logs do not include provider secrets or exception messages. The existing React UI continues to use the synchronous endpoint.

Start Laravel and the queue worker in separate terminals:

```bash
php artisan serve
php artisan queue:work --tries=3
```

The worker uses the queue connection selected by `QUEUE_CONNECTION`. The existing `jobs` migration provides the local database queue. The summary job itself retries up to three times with 5- and 30-second backoff.

## Docker Compose (complete project)

For the concise run guide, see [`DOCKER-SETUP.md`](../DOCKER-SETUP.md). The Docker setup lives at the project root. It runs Laravel on PHP 8.3/Apache, React/Vite built with Node 20, MySQL 8.4, Redis 7, and an Nginx frontend that serves the React build and proxies `/api` and `/docs` to Laravel. Docker uses a separate MySQL volume; it does not alter or import your existing host MySQL database. The backend image does not contain `.env` files or secrets.

From the project root, prepare Docker-only settings and generate an application key:

```bash
cp .env.docker.example .env.docker
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Copy the generated key into `APP_KEY` in `.env.docker`. Set unique `DB_PASSWORD` and `DB_ROOT_PASSWORD` values there. Keep this file private; it is Git-ignored. You can keep `AI_PROVIDER=mock`, or configure `AI_PROVIDER=openai` with `OPENAI_API_KEY` or `AI_PROVIDER=gemini` with `GEMINI_API_KEY` to use a real summary provider.

Build and start all services:

```bash
docker compose --env-file .env.docker up --build -d
```

Migrations are deliberately not run automatically. Once MySQL is healthy, apply pending migrations without resetting existing Docker data:

```bash
docker compose --env-file .env.docker exec backend php artisan migrate --force
```

The frontend is at `http://localhost:8080`. The backend API and docs use the host port configured by `BACKEND_FORWARD_PORT` (8000 by default): `/api` and `/docs/api` respectively. The docs are also proxied at `http://localhost:8080/docs/api`. The UI calls the same-origin `/api` path through Nginx. MySQL is exposed on host port 3307 by default; Redis is private to the Compose network.

Useful lifecycle and diagnostic commands, all from the project root:

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f backend frontend mysql redis queue
docker compose --env-file .env.docker exec backend php artisan migrate:status
docker compose --env-file .env.docker exec backend php artisan about
docker compose --env-file .env.docker down
```

The `queue` service runs `php artisan queue:work redis --tries=3` separately from the web service. Redis credentials and host names are supplied through `.env.docker`; inside Compose Laravel connects to `mysql` and `redis`, not `127.0.0.1`.

### Ollama from Docker on macOS

Ollama is intentionally not installed as a Compose service and models are never downloaded during container startup. Start Ollama on the Mac host and install the model once, if needed:

```bash
ollama pull nomic-embed-text
curl http://127.0.0.1:11434/api/embed \
  -H 'Content-Type: application/json' \
  -d '{"model":"nomic-embed-text","input":"Docker connectivity check"}'
```

The Docker environment uses `EMBEDDING_BASE_URL=http://host.docker.internal:11434`; Docker Desktop for Mac resolves this name to the host. Keep `EMBEDDING_PROVIDER=ollama` and set `EMBEDDING_MODEL` to the model installed on the host. If Ollama is unavailable, embedding-dependent APIs return their existing safe service-unavailable response; no hash fallback is used.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
