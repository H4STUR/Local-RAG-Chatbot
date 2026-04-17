<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('ollama.base_url');
        $this->timeout = config('ollama.timeout');
    }

    /**
     * @throws \RuntimeException
     */
    public function embed(string $text, ?string $model = null): array
    {
        $model ??= config('ollama.embed_model');

        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/api/embed", [
                'model' => $model,
                'input' => $text,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama embed failed [{$response->status()}]: {$response->body()}");
        }

        $embedding = $response->json('embeddings.0');

        if (! is_array($embedding) || empty($embedding)) {
            throw new \RuntimeException('No embedding returned from Ollama: ' . $response->body());
        }

        return $embedding;
    }

    /**
     * @throws \RuntimeException
     */
    public function generate(string $prompt, ?string $model = null, array $options = []): string
    {
        $model ??= config('ollama.chat_model');

        $payload = [
            'model'  => $model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        if (! empty($options)) {
            $payload['options'] = $options;
        }

        $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/generate", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama generate failed [{$response->status()}]: {$response->body()}");
        }

        return trim((string) $response->json('response'));
    }
}
