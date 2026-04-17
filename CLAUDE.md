# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Commands

```bash
# First-time setup
composer run setup

# Start all dev servers (PHP, queue, log watcher, Vite)
composer run dev

# Run tests
php artisan test
php artisan test --filter TestName

# Lint PHP
./vendor/bin/pint

# Frontend
npm run dev    # watch
npm run build  # production

# Database
php artisan migrate
php artisan migrate:fresh
```

## Architecture

Local RAG chatbot built with **Laravel 13** (PHP 8.3). All AI inference runs locally via **Ollama** — no external API calls.

### Infrastructure

- **PostgreSQL 17 + pgvector** (Docker): `docker compose up -d` → port 5432. Adminer on port 8080.
- **Ollama** (port 11434): requires two models:
  - `llama3.2` (or `OLLAMA_CHAT_MODEL`) — chat generation
  - `embeddinggemma` (or `OLLAMA_EMBED_MODEL`) — 768-dim embeddings

### Key env vars (see `.env.example`)

| Variable | Default | Purpose |
|---|---|---|
| `OLLAMA_BASE_URL` | `http://localhost:11434` | Ollama endpoint |
| `OLLAMA_CHAT_MODEL` | `llama3.2` | Generation model |
| `OLLAMA_EMBED_MODEL` | `embeddinggemma` | Embedding model |
| `RAG_CHUNK_SIZE` | `800` | Characters per chunk |
| `RAG_TOP_K` | `3` | Chunks retrieved per query |

### RAG pipeline

1. **Upload** (`POST /upload`): PDF → text via `smalot/pdfparser` → `documents` table.
2. **Chunk** (`GET /chunk-document/{id}`): splits text into `RAG_CHUNK_SIZE`-char chunks → `document_chunks`.
3. **Embed** (`GET /embed-document/{id}`): each chunk → Ollama embed API → `vector(768)` in `document_chunks.embedding`.
4. **Ask** (`POST /api/ask`): question → embed → pgvector `<->` L2 search (top `RAG_TOP_K`) → prompt → Ollama generate → answer + timing metrics.

Response from `/api/ask` includes `timing_ms` with `embed`, `retrieval`, `generate`, and `total` in milliseconds.

### Code structure

```
app/Services/OllamaService.php   — embed() and generate() HTTP calls
app/Services/RagService.php      — chunkDocument(), embedDocument(), ask()
app/Http/Controllers/
  DocumentController.php         — upload/chunk/embed routes
  RagController.php              — /api/ask
config/ollama.php                — all Ollama + RAG config (reads env)
routes/web.php                   — document workflow + debug endpoints (thin closures)
routes/api.php                   — POST /api/ask
```

### Data models

- `Document` — title + full PDF text
- `DocumentChunk` — belongs to Document; chunk index, content, and `embedding vector(768)` column (managed via raw SQL — pgvector types not supported by Laravel Blueprint)

### Testing notes

- Unit tests use SQLite in-memory → pgvector SQL won't work there.
- Integration tests for the RAG pipeline require the real PostgreSQL stack.
- Use `/ask-test` for quick manual testing with full timing output in JSON.
- Use `/search-test?q=your+query` to debug retrieval quality (returns raw chunks + distances, no generation).
- Use `/ollama-test` and `/embed-test` to smoke-test Ollama connectivity.
