# Local RAG Chatbot

A locally-hosted Retrieval-Augmented Generation (RAG) chatbot built with Laravel. Upload PDF documents and ask questions about them — all AI inference runs on your machine via Ollama, with no external API calls.

## Requirements

- PHP 8.3+
- Composer
- Node.js & npm
- Docker (for PostgreSQL + pgvector)
- [Ollama](https://ollama.com) with the following models pulled:
  ```bash
  ollama pull llama3.2
  ollama pull embeddinggemma
  ```

## Setup

**1. Start the database**
```bash
docker compose up -d
```
This starts PostgreSQL 17 with the pgvector extension on port 5432. Adminer (DB UI) is available at `http://localhost:8080`.

**2. Install dependencies and configure**
```bash
composer run setup
```
This runs `composer install`, creates `.env` from `.env.example`, generates the app key, runs migrations, and builds frontend assets.

**3. Start the development server**
```bash
composer run dev
```
Opens the app at `http://localhost:8000`.

## Usage

### Ingest a document

1. Go to `http://localhost:8000/upload` and upload a PDF (max 10 MB).
2. Note the document ID returned.
3. Chunk the document: `GET /chunk-document/{id}`
4. Embed the chunks: `GET /embed-document/{id}`

### Chat

Go to `http://localhost:8000/chat` and ask questions about your uploaded documents.

The API endpoint is also available directly:
```bash
curl -X POST http://localhost:8000/api/ask \
  -H "Content-Type: application/json" \
  -d '{"question": "What is this document about?"}'
```

## How it works

1. **Upload** — PDFs are parsed with `smalot/pdfparser` and stored as text in the `documents` table.
2. **Chunk** — Document text is split into 800-character chunks stored in `document_chunks`.
3. **Embed** — Each chunk is sent to Ollama (`embeddinggemma`) to generate a 768-dimension vector, stored in PostgreSQL using the pgvector extension.
4. **Query** — A user question is embedded, then the 3 nearest chunks are retrieved via L2 distance (`<->`), and passed as context to `llama3.2` to generate an answer.

## Development

```bash
composer run test          # run tests
./vendor/bin/pint          # format PHP code
php artisan migrate:fresh  # reset database
```

Debug endpoints (development only):
- `GET /ollama-test` — verify Ollama connectivity
- `GET /embed-test` — verify embedding model
- `GET /search-test?q=your+query` — test vector search
