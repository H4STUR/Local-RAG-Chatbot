<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RagController;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ── RAG ──────────────────────────────────────────────────────────────────────
// Returns NDJSON stream: sources → tokens → done
Route::post('/ask', [RagController::class, 'ask']);

// ── Documents ────────────────────────────────────────────────────────────────
Route::get('/documents', [DocumentController::class, 'index']);
Route::post('/documents', [DocumentController::class, 'upload']);
Route::get('/documents/{id}/chunk', [DocumentController::class, 'chunk']);
Route::get('/documents/{id}/embed', [DocumentController::class, 'embed']);
Route::get('/documents/{id}/process', [DocumentController::class, 'process']); // chunk + embed
Route::delete('/documents/{id}', [DocumentController::class, 'delete']);
Route::delete('/documents', [DocumentController::class, 'deleteAll']);

// ── Debug / smoke-tests ──────────────────────────────────────────────────────
Route::get('/debug/ollama', function (OllamaService $ollama) {
    try {
        return response()->json(['answer' => $ollama->generate('Say hello in one sentence.')]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug/embed', function (OllamaService $ollama) {
    try {
        $vec = $ollama->embed('Hello world');
        return response()->json(['dimensions' => count($vec), 'sample' => array_slice($vec, 0, 5)]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Raw retrieval — returns matching chunks + distances, no generation
Route::get('/debug/search', function (Request $request, OllamaService $ollama) {
    $query = trim((string) $request->query('q', ''));
    if ($query === '') {
        return response()->json(['error' => 'Missing q parameter'], 422);
    }

    try {
        $embedding = $ollama->embed($query);
        $topK      = config('ollama.top_k');
        $vector    = '[' . implode(',', array_map(fn ($v) => (float) $v, $embedding)) . ']';

        $results = DB::select(
            'SELECT id, document_id, chunk_index, content, embedding <-> ?::vector AS distance
             FROM document_chunks WHERE embedding IS NOT NULL
             ORDER BY embedding <-> ?::vector LIMIT ?',
            [$vector, $vector, $topK]
        );

        return response()->json(['query' => $query, 'results' => $results]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
