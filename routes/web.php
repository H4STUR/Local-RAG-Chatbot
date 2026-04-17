<?php

use App\Http\Controllers\DocumentController;
use App\Services\OllamaService;
use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Views
Route::get('/', fn () => view('welcome'));
Route::get('/chat', fn () => view('chat'));
Route::get('/ask-test', fn () => view('ask-test'));

// Document workflow
Route::get('/documents', [DocumentController::class, 'index']);
Route::get('/upload', [DocumentController::class, 'uploadForm']);
Route::post('/upload', [DocumentController::class, 'upload']);
Route::get('/chunk-document/{id}', [DocumentController::class, 'chunk']);
Route::get('/embed-document/{id}', [DocumentController::class, 'embed']);

// Debug / smoke-test endpoints
Route::get('/ollama-test', function (OllamaService $ollama) {
    try {
        return response()->json(['answer' => $ollama->generate('Say hello in one sentence.')]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/embed-test', function (OllamaService $ollama) {
    try {
        $vec = $ollama->embed('Hello world');
        return response()->json(['dimensions' => count($vec), 'sample' => array_slice($vec, 0, 5)]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Raw retrieval debug — returns chunks + distances without generation
Route::get('/search-test', function (Request $request, OllamaService $ollama) {
    $query = trim((string) $request->query('q', ''));
    if ($query === '') {
        return response()->json(['error' => 'Missing q parameter'], 422);
    }

    try {
        $embedding = $ollama->embed($query);
        $topK = config('ollama.top_k');
        $vector = '[' . implode(',', array_map(fn ($v) => (float) $v, $embedding)) . ']';

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
