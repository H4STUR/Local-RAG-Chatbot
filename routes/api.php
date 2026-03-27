<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::post('/ask', function (Request $request) {
    $question = trim((string) $request->input('question', ''));

    if ($question === '') {
        return response()->json(['error' => 'Question is required'], 422);
    }

    $embedResponse = Http::timeout(120)->post('http://localhost:11434/api/embed', [
        'model' => 'embeddinggemma',
        'input' => $question,
    ]);

    if (! $embedResponse->successful()) {
        return response()->json([
            'error' => 'Embedding request failed',
            'status' => $embedResponse->status(),
            'body' => $embedResponse->body(),
        ], 500);
    }

    $embedding = $embedResponse->json('embeddings.0');

    if (! is_array($embedding) || empty($embedding)) {
        return response()->json([
            'error' => 'No embedding returned',
            'json' => $embedResponse->json(),
        ], 500);
    }

    $vector = '[' . implode(',', array_map(fn ($v) => (float) $v, $embedding)) . ']';

    $results = DB::select(
        '
        SELECT
            id,
            document_id,
            chunk_index,
            content,
            embedding <-> ?::vector AS distance
        FROM document_chunks
        WHERE embedding IS NOT NULL
        ORDER BY embedding <-> ?::vector
        LIMIT 3
        ',
        [$vector, $vector]
    );

    if (empty($results)) {
        return response()->json([
            'question' => $question,
            'answer' => 'I could not find any relevant information in the knowledge base.',
            'sources' => [],
        ]);
    }

    $context = collect($results)
        ->map(fn ($row, $i) => "Source " . ($i + 1) . ":\n" . $row->content)
        ->implode("\n\n");

    $prompt = <<<PROMPT
You are a helpful website assistant.

Answer the user's question using ONLY the provided context.
If the answer is not in the context, say clearly that you do not know based on the provided information.
Keep the answer concise and natural.

Context:
$context

User question:
$question
PROMPT;

    $chatResponse = Http::timeout(120)->post('http://localhost:11434/api/generate', [
        'model' => 'llama3.2',
        'prompt' => $prompt,
        'stream' => false,
    ]);

    if (! $chatResponse->successful()) {
        return response()->json([
            'error' => 'Chat generation failed',
            'status' => $chatResponse->status(),
            'body' => $chatResponse->body(),
        ], 500);
    }

    return response()->json([
        'question' => $question,
        'answer' => trim((string) $chatResponse->json('response')),
        'sources' => $results,
    ]);
});