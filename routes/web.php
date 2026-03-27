<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

function chunkTextByLength(string $text, int $maxLength = 800): array
{
    $text = trim(preg_replace('/\s+/', ' ', $text));

    if ($text === '') {
        return [];
    }

    $chunks = [];
    $offset = 0;
    $length = mb_strlen($text);

    while ($offset < $length) {
        $chunk = mb_substr($text, $offset, $maxLength);
        $chunks[] = trim($chunk);
        $offset += $maxLength;
    }

    return array_values(array_filter($chunks));
}


Route::get('/chunk-document/{id}', function ($id) {
    $document = Document::findOrFail($id);

    $document->chunks()->delete();

    $chunks = chunkTextByLength($document->content, 800);

    foreach ($chunks as $index => $chunk) {
        DocumentChunk::create([
            'document_id' => $document->id,
            'chunk_index' => $index,
            'content' => $chunk,
        ]);
    }

    return response()->json([
        'document_id' => $document->id,
        'chunks_created' => count($chunks),
        'sample_chunks' => array_slice($chunks, 0, 3),
    ]);
});

Route::get('/documents', function () {
    return Document::select('id', 'title')->get();
});

Route::get('/upload', function () {
    return view('upload');
});

Route::post('/upload', function (Request $request) {
    $request->validate([
        'file' => 'required|file|mimes:pdf|max:10240',
    ]);

    $file = $request->file('file');
    $path = $file->store('documents'); // saved on default disk

    $fullPath = Storage::path($path);

    if (! file_exists($fullPath)) {
        abort(500, 'File not found: ' . $fullPath);
    }

    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($fullPath);
    $text = $pdf->getText();

    Document::create([
        'title' => $file->getClientOriginalName(),
        'content' => $text,
    ]);

    return 'Uploaded and parsed!';
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/ollama-test', function () {
    $response = Http::timeout(120)->post('http://localhost:11434/api/generate', [
        'model' => 'llama3.2',
        'prompt' => 'hi there',
        'stream' => false,
    ]);
    return $response->json();
});


Route::get('/embed-test', function () {
    $response = Http::timeout(120)->post('http://localhost:11434/api/embed', [
        'model' => 'embeddinggemma',
        'input' => 'Hello world',
    ]);

    return response()->json([
        'status' => $response->status(),
        'successful' => $response->successful(),
        'raw' => $response->json(),
        'body' => $response->body(),
    ]);
});



Route::get('/embed-document/{id}', function ($id) {
    $document = Document::with('chunks')->findOrFail($id);

    $processed = 0;
    $errors = [];

    foreach ($document->chunks as $chunk) {
        $response = Http::timeout(120)->post('http://localhost:11434/api/embed', [
            'model' => 'embeddinggemma',
            'input' => $chunk->content,
        ]);

        if (! $response->successful()) {
            $errors[] = [
                'chunk_id' => $chunk->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ];
            continue;
        }

        $embedding = $response->json('embeddings.0');

        if (! is_array($embedding) || empty($embedding)) {
            $errors[] = [
                'chunk_id' => $chunk->id,
                'error' => 'No embedding returned',
                'json' => $response->json(),
            ];
            continue;
        }

        $vector = '[' . implode(',', array_map(fn ($v) => (float) $v, $embedding)) . ']';

        DB::update(
            'UPDATE document_chunks SET embedding = ?::vector WHERE id = ?',
            [$vector, $chunk->id]
        );

        $processed++;
    }

    return response()->json([
        'document_id' => $document->id,
        'chunks_total' => $document->chunks->count(),
        'chunks_embedded' => $processed,
        'errors' => $errors,
    ]);
});

Route::get('/search-test', function (\Illuminate\Http\Request $request) {
    $query = trim((string) $request->query('q', ''));

    if ($query === '') {
        return response()->json(['error' => 'Missing q parameter'], 422);
    }

    $response = Http::timeout(120)->post('http://localhost:11434/api/embed', [
        'model' => 'embeddinggemma',
        'input' => $query,
    ]);

    if (! $response->successful()) {
        return response()->json([
            'error' => 'Embedding request failed',
            'status' => $response->status(),
            'body' => $response->body(),
        ], 500);
    }

    $embedding = $response->json('embeddings.0');

    if (! is_array($embedding) || empty($embedding)) {
        return response()->json([
            'error' => 'No embedding returned',
            'json' => $response->json(),
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
        LIMIT 5
        ',
        [$vector, $vector]
    );

    return response()->json([
        'query' => $query,
        'results' => $results,
    ]);
});

Route::get('/ask-test', function () {
    return view('ask-test');
});

Route::get('/chat', function () {
    return view('chat');
});