<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

class RagService
{
    public function __construct(private OllamaService $ollama) {}

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
        $chunks = $document->chunks()->get();
        $processed = 0;
        $errors = [];

        foreach ($chunks as $chunk) {
            try {
                $embedding = $this->ollama->embed($chunk->content);
                $vector = '[' . implode(',', array_map(fn ($v) => (float) $v, $embedding)) . ']';

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
            'document_id'    => $document->id,
            'chunks_total'   => $chunks->count(),
            'chunks_embedded' => $processed,
            'errors'         => $errors,
        ];
    }

    public function ask(string $question): array
    {
        $topK = config('ollama.top_k');
        $t0 = microtime(true);

        $embedding = $this->ollama->embed($question);
        $tEmbed = microtime(true);

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

        $timingMs = static fn ($a, $b) => (int) round(($b - $a) * 1000);

        if (empty($results)) {
            return [
                'question' => $question,
                'answer'   => 'I could not find any relevant information in the knowledge base.',
                'sources'  => [],
                'timing_ms' => [
                    'embed'    => $timingMs($t0, $tEmbed),
                    'retrieval' => $timingMs($tEmbed, $tRetrieve),
                    'generate' => 0,
                    'total'    => $timingMs($t0, $tRetrieve),
                ],
            ];
        }

        $context = collect($results)
            ->map(fn ($row) => trim($row->content))
            ->implode("\n---\n");

        $answer = $this->ollama->generate(
            $this->buildPrompt($question, $context),
            null,
            ['num_predict' => 512, 'temperature' => 0.1]
        );
        $tGenerate = microtime(true);

        return [
            'question'  => $question,
            'answer'    => $answer,
            'sources'   => $results,
            'timing_ms' => [
                'embed'    => $timingMs($t0, $tEmbed),
                'retrieval' => $timingMs($tEmbed, $tRetrieve),
                'generate' => $timingMs($tRetrieve, $tGenerate),
                'total'    => $timingMs($t0, $tGenerate),
            ],
        ];
    }

    private function buildPrompt(string $question, string $context): string
    {
        return <<<PROMPT
You are a helpful assistant.

Answer the user's question using ONLY the provided context below.
Always respond in the same language as the user's question, even if the context is in a different language.
If the answer cannot be found in the context, say so clearly in the user's language.
Be concise and direct.

Context:
{$context}

Question: {$question}
PROMPT;
    }
}
