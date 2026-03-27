<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/ollama-test', function () {
    $response = Http::timeout(120)->post('http://localhost:11434/api/generate', [
        'model' => 'llama3.2',
        'prompt' => 'Explain in one short sentence what RAG is.',
        'stream' => false,
    ]);

    return $response->json();
});
