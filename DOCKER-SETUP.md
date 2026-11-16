# AI Notes Manager: Docker Setup

Use this guide to run the complete application with Docker Compose.

## URLs

The current `.env.docker` configuration uses these URLs:

- Frontend: <http://localhost:8080>
- Laravel API: <http://localhost:8001/api>
- Swagger / OpenAPI UI: <http://localhost:8001/docs/api>
- Swagger / OpenAPI JSON: <http://localhost:8001/docs/api.json>
- MySQL from the Mac host: `127.0.0.1:3307`
- Redis: private to the Docker Compose network

The backend uses port `8001` because the local Laravel server already uses `8000`. The frontend container proxies `/api` and `/docs` to the Docker backend, so open the Docker frontend at port `8080`; it is already configured to use the Docker backend.

## First-time setup

Open Docker Desktop and wait until its engine is running. From the project root (`ai-notes-manager`), create a Docker environment file if one does not already exist:

```bash
cp .env.docker.example .env.docker
```

Generate a Laravel application key:

```bash
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Copy the generated value into `APP_KEY` in `.env.docker`. Set unique values for `DB_PASSWORD` and `DB_ROOT_PASSWORD`. Keep `.env.docker` private; Git ignores it and Docker does not copy it into the application image.

If port `8000` is free, you can set `BACKEND_FORWARD_PORT=8000`. The current setup uses `BACKEND_FORWARD_PORT=8001` to avoid the existing local Laravel server.

Build and start the services:

```bash
docker compose --env-file .env.docker up --build -d
```

Apply database migrations. This command only runs pending migrations; Compose does not reset or migrate the database automatically:

```bash
docker compose --env-file .env.docker exec backend php artisan migrate --force
```

## Normal start and stop

From the project root:

```bash
docker compose --env-file .env.docker up -d
```

Open <http://localhost:8080> in the browser. To stop the containers while preserving the Docker MySQL and Redis data volumes:

```bash
docker compose --env-file .env.docker down
```

Do not add `-v` to `down` unless you intentionally want to delete the Compose data volumes.

## Check services and logs

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f backend frontend mysql redis queue
```

The `queue` service is already a separate Redis queue worker. You do not need to start another worker terminal. Queue jobs, including asynchronous AI summaries, are handled by that service.

Useful checks:

```bash
curl http://localhost:8001/up
curl http://localhost:8080/api/notes/stats
docker compose --env-file .env.docker exec backend php artisan migrate:status
```

## Ollama embeddings on macOS

Ollama runs on the Mac host, outside Docker. Start Ollama on the host and ensure the configured model is installed:

```bash
ollama pull nomic-embed-text
ollama list
```

In `.env.docker`, use:

```dotenv
EMBEDDING_PROVIDER=ollama
EMBEDDING_MODEL=nomic-embed-text
EMBEDDING_BASE_URL=http://host.docker.internal:11434
```

Docker Desktop for Mac resolves `host.docker.internal` to the host machine. The application does not download models when containers start. If Ollama is stopped or the model is missing, embedding-dependent API calls return the existing safe provider error.

After editing `.env.docker`, recreate the services so they receive the new environment:

```bash
docker compose --env-file .env.docker up -d --force-recreate backend queue
```

## Database note

Docker Compose uses its own MySQL database and persistent volume. It is separate from the MySQL database used by the host/local Laravel setup. Existing host database notes are not copied into Docker automatically. Export and import them separately if you want those notes in the Docker database.

## Local development remains separate

The existing host workflow is unchanged. It commonly uses Vite at `http://localhost:5173` and Laravel at `http://localhost:8000`. The Docker frontend/backend pair is `http://localhost:8080` and `http://localhost:8001` in the current local configuration. Use the matching frontend and backend pair; the Docker frontend calls its backend through the Compose Nginx proxy.
