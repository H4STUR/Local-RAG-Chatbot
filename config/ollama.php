<?php

return [
    'base_url'   => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'chat_model' => env('OLLAMA_CHAT_MODEL', 'llama3.2'),
    'embed_model' => env('OLLAMA_EMBED_MODEL', 'embeddinggemma'),
    'timeout'    => (int) env('OLLAMA_TIMEOUT', 120),
    'chunk_size' => (int) env('RAG_CHUNK_SIZE', 800),
    'top_k'      => (int) env('RAG_TOP_K', 3),
];
