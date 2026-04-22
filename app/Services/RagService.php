<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RagService
{
    public function __construct(private OllamaService $ollama) {}

    // ── Document pipeline ────────────────────────────────────────────────────

    public function chunkDocument(Document $document): array
    {
        $chunkSize = config('ollama.chunk_size');
        $text = trim(preg_replace('/\s+/', ' ', $document->content));

        if ($text === '') {
            return [];
        }

        $document->chunks()->delete();

        $chunks = [];
        $offset = 0;
        $length = mb_strlen($text);

        while ($offset < $length) {
            $chunk = trim(mb_substr($text, $offset, $chunkSize));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
            $offset += $chunkSize;
        }

        foreach ($chunks as $index => $chunk) {
            DocumentChunk::create([
                'document_id' => $document->id,
                'chunk_index' => $index,
                'content'     => $chunk,
            ]);
        }

        return $chunks;
    }

    public function embedDocument(Document $document): array
    {
        $chunks    = $document->chunks()->get();
        $processed = 0;
        $errors    = [];

        foreach ($chunks as $chunk) {
            try {
                $embedding = $this->ollama->embed($chunk->content);
                $vector    = '[' . implode(',', array_map(fn ($v) => (float) $v, $embedding)) . ']';

                DB::update(
                    'UPDATE document_chunks SET embedding = ?::vector WHERE id = ?',
                    [$vector, $chunk->id]
                );

                $processed++;
            } catch (\RuntimeException $e) {
                $errors[] = ['chunk_id' => $chunk->id, 'error' => $e->getMessage()];
            }
        }

        return [
            'document_id'     => $document->id,
            'chunks_total'    => $chunks->count(),
            'chunks_embedded' => $processed,
            'errors'          => $errors,
        ];
    }

    // ── Ask (streaming) ──────────────────────────────────────────────────────

    /**
     * Embed the question and retrieve matching chunks (blocking, fast).
     * Returns a StreamedResponse that pipes generation tokens to the client
     * as newline-delimited JSON (NDJSON).
     *
     * Events sent:
     *   {"type":"sources","sources":[...],"timing_ms":{"embed":N,"retrieval":N}}
     *   {"type":"token","token":"..."} × many
     *   {"type":"done","timing_ms":{"generate":N,"total":N}}
     *   {"type":"error","error":"..."} on failure
     *
     * @throws \RuntimeException if embedding fails (caught by RagController)
     */
    public function stream(string $question): StreamedResponse
    {
        $t0 = microtime(true);

        $embedding = $this->ollama->embed($question); // throws on failure
        $tEmbed    = microtime(true);

        $topK   = config('ollama.top_k');
        $vector = '[' . implode(',', array_map(fn ($v) => (float) $v, $embedding)) . ']';

        $results = DB::select(
            'SELECT id, document_id, chunk_index, content, embedding <-> ?::vector AS distance
             FROM document_chunks
             WHERE embedding IS NOT NULL
             ORDER BY embedding <-> ?::vector
             LIMIT ?',
            [$vector, $vector, $topK]
        );
        $tRetrieve = microtime(true);

        $context   = $this->buildContext($results);
        $prompt    = $this->buildPrompt($question, $context);
        $generator = $this->ollama->streamGenerate($prompt); // lazy — no HTTP yet

        return response()->stream(
            function () use ($results, $generator, $t0, $tEmbed, $tRetrieve) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                $ms = static fn ($a, $b) => (int) round(($b - $a) * 1000);

                // ── 1. sources event (instant) ────────────────────────────
                echo json_encode([
                    'type'      => 'sources',
                    'sources'   => $results,
                    'timing_ms' => [
                        'embed'     => $ms($t0, $tEmbed),
                        'retrieval' => $ms($tEmbed, $tRetrieve),
                    ],
                ]) . "\n";
                flush();

                if (empty($results)) {
                    $noInfo = "I don't have that information in the provided documents.";
                    echo json_encode(['type' => 'token', 'token' => $noInfo]) . "\n";
                    echo json_encode([
                        'type'      => 'done',
                        'timing_ms' => ['generate' => 0, 'total' => $ms($t0, microtime(true))],
                    ]) . "\n";
                    flush();

                    return;
                }

                // ── 2. token stream ───────────────────────────────────────
                $tGen = microtime(true);

                try {
                    foreach ($generator as $token) {
                        echo json_encode(['type' => 'token', 'token' => $token]) . "\n";
                        flush();
                    }
                } catch (\Throwable $e) {
                    echo json_encode(['type' => 'error', 'error' => $e->getMessage()]) . "\n";
                    flush();

                    return;
                }

                // ── 3. done event ─────────────────────────────────────────
                $tDone = microtime(true);
                echo json_encode([
                    'type'      => 'done',
                    'timing_ms' => [
                        'generate' => $ms($tGen, $tDone),
                        'total'    => $ms($t0, $tDone),
                    ],
                ]) . "\n";
                flush();
            },
            200,
            [
                'Content-Type'           => 'application/x-ndjson',
                'X-Accel-Buffering'      => 'no',
                'Cache-Control'          => 'no-cache, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildContext(array $results): string
    {
        $maxChars = config('ollama.max_context_chars');
        $parts    = [];
        $total    = 0;

        foreach ($results as $row) {
            $text = trim($row->content);
            $len  = mb_strlen($text);

            if ($total + $len > $maxChars) {
                $remaining = $maxChars - $total;
                if ($remaining > 80) {
                    $parts[] = mb_substr($text, 0, $remaining);
                }
                break;
            }

            $parts[] = $text;
            $total  += $len;
        }

        return implode("\n---\n", $parts);
    }

    private function buildPrompt(string $question, string $context): string
    {
        return <<<PROMPT
You are a document Q&A assistant.

Rules:
- Answer using ONLY the text provided in the context below.
- Respond in the same language as the question, even if the context is in a different language.
- If the answer is not explicitly in the context, say only: "I don't have that information in the provided documents."
- Do not guess, infer, or add any information beyond what is written in the context.
- Be concise.

Context:
{$context}

Question: {$question}
Answer:
PROMPT;
    }
}
