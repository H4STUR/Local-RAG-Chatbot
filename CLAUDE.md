# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# First-time setup
composer run setup

# Start all dev servers concurrently (PHP, queue, log watcher, Vite)
composer run dev

# Run all tests
composer run test
# or
php artisan test

# Run a single test
php artisan test --filter TestName

# Lint/format PHP code
./vendor/bin/pint

# Frontend assets
npm run dev    # watch mode
npm run build  # production build

# Database
php artisan migrate
php artisan migrate:fresh  # wipe and re-run all migrations
```

## Architecture

This is a local RAG (Retrieval-Augmented Generation) chatbot built with **Laravel 13** (PHP 8.3). All AI inference runs locally via **Ollama** — no external AI API calls.

### Infrastructure dependencies

- **PostgreSQL 17 + pgvector** (Docker): `docker compose up -d` starts it on port 5432. Adminer runs on port 8080.
- **Ollama** (local, port 11434): Must have two models pulled:
  - `llama3.2` — chat generation
  - `embeddinggemma` — 768-dimension text embeddings

### RAG pipeline

1. **Ingest** (`POST /upload`): PDF uploaded → text extracted via `smalot/pdfparser` → stored in `documents` table.
2. **Chunk** (`GET /chunk-document/{id}`): Document text split into 800-character chunks → stored in `document_chunks`.
3. **Embed** (`GET /embed-document/{id}`): Each chunk sent to Ollama embed API → `vector(768)` stored in `document_chunks.embedding` using raw SQL (`ALTER TABLE ... ADD COLUMN embedding vector(768)`).
4. **Query** (`POST /api/ask`): Question → embed → pgvector `<->` (L2 distance) search → top 3 chunks as context → Ollama `llama3.2` generates answer.

### Key data models

- `Document` — stores title + full extracted PDF text
- `DocumentChunk` — belongs to Document; stores chunk index, content, and `embedding` vector column (managed via raw SQL, not Eloquent, because pgvector types aren't natively supported by Laravel's Blueprint)

### Routes

All application logic lives directly in route files (no controllers):

- [routes/web.php](routes/web.php) — upload, chunking, embedding, debug/test endpoints, chat UI views
- [routes/api.php](routes/api.php) — `POST /api/ask` (the main RAG query endpoint)

### Views

Blade templates in `resources/views/`: `welcome.blade.php`, `chat.blade.php`, `upload.blade.php`, `ask-test.blade.php`. Styled with Tailwind CSS 4 via Vite.

### Testing notes

Tests (`phpunit.xml`) use SQLite in-memory, which means pgvector-specific SQL won't work in tests. Integration tests for the RAG pipeline require the real PostgreSQL stack.
