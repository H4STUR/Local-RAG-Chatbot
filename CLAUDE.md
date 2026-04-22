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

Local RAG chatbot built with **Laravel 13** (PHP 8.3). All AI inference runs locally via **Ollama** — no external API calls.

### Infrastructure dependencies

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
| `RAG_MAX_CONTEXT_CHARS` | `1500` | Max context chars fed to prompt |
| `RAG_MAX_TOKENS` | `256` | Max tokens generated per answer |

### RAG pipeline

1. **Upload** (`POST /api/documents`): PDF → text via `smalot/pdfparser` → `documents` table.
2. **Chunk** (`GET /api/documents/{id}/chunk`): splits text into `RAG_CHUNK_SIZE`-char chunks → `document_chunks`.
3. **Embed** (`GET /api/documents/{id}/embed`): each chunk → Ollama embed API → `vector(768)` in `document_chunks.embedding`.
4. **Process** (`GET /api/documents/{id}/process`): chunk + embed in one request — the normal flow.
5. **Ask** (`POST /api/ask`): question → embed → pgvector `<->` L2 search → streaming NDJSON response.

### Streaming response format (`POST /api/ask`)

The endpoint returns `application/x-ndjson`. Each line is a JSON event:

```
{"type":"sources","sources":[...],"timing_ms":{"embed":N,"retrieval":N}}
{"type":"token","token":"Hello"}   ← many of these
{"type":"done","timing_ms":{"generate":N,"total":N}}
{"type":"error","error":"..."}     ← only on failure
```

### Code structure

```
app/Services/
  OllamaService.php    — embed(), generate(), streamGenerate() (lazy Generator)
  RagService.php       — chunkDocument(), embedDocument(), stream()
app/Http/Controllers/
  DocumentController.php — index, upload, chunk, embed, process, delete, deleteAll
  RagController.php      — ask() (returns StreamedResponse)
config/ollama.php        — all Ollama + RAG config (reads env)
routes/web.php           — page views only (/, /chat, /ask-test, /upload)
routes/api.php           — all data routes + /api/debug/*
```

### Data models

- `Document` — title + full PDF text
- `DocumentChunk` — belongs to Document; chunk index, content, `embedding vector(768)` (managed via raw SQL — pgvector types not supported by Laravel Blueprint)

### Routes at a glance

| Method | Path | Purpose |
|---|---|---|
| GET | `/chat` | Main UI (upload + chat on one page) |
| GET | `/ask-test` | Streaming debug — shows raw NDJSON events |
| POST | `/api/ask` | Streaming RAG answer |
| GET | `/api/documents` | List with chunk/embed counts |
| POST | `/api/documents` | Upload PDF |
| GET | `/api/documents/{id}/process` | Chunk + embed |
| DELETE | `/api/documents/{id}` | Delete one document |
| DELETE | `/api/documents` | Clear all data |
| GET | `/api/debug/ollama` | Smoke-test generation |
| GET | `/api/debug/embed` | Smoke-test embedding |
| GET | `/api/debug/search?q=...` | Raw retrieval (no generation) |

### Testing notes

- Unit tests use SQLite in-memory → pgvector SQL won't work there.
- Integration tests for the RAG pipeline require the real PostgreSQL stack.
- For manual testing: use `/chat` for the full UI, `/ask-test` for raw stream events.
