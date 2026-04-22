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
            throw new \RuntimeException('No embedding returned: ' . $response->body());
        }

        return $embedding;
    }

    /**
     * Non-streaming generate — used internally (e.g. embed-document smoke test).
     *
     * @throws \RuntimeException
     */
    public function generate(string $prompt, ?string $model = null, array $options = []): string
    {
        $model ??= config('ollama.chat_model');

        $payload = ['model' => $model, 'prompt' => $prompt, 'stream' => false];
        if (! empty($options)) {
            $payload['options'] = $options;
        }

        $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/generate", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama generate failed [{$response->status()}]: {$response->body()}");
        }

        return trim((string) $response->json('response'));
    }

    /**
     * Lazy generator that streams tokens from Ollama one at a time.
     * The HTTP request is initiated on first iteration (generators are lazy).
     *
     * @throws \RuntimeException on connection failure
     */
    public function streamGenerate(string $prompt, ?string $model = null, array $options = []): \Generator
    {
        $model ??= config('ollama.chat_model');

        $defaults = [
            'num_predict'    => config('ollama.max_tokens'),
            'temperature'    => 0.0,
            'repeat_penalty' => 1.1,
        ];

        $response = Http::withOptions(['stream' => true])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/api/generate", [
                'model'   => $model,
                'prompt'  => $prompt,
                'stream'  => true,
                'options' => array_merge($defaults, $options),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama stream failed [{$response->status()}]: {$response->body()}");
        }

        $body   = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(256);
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;

            while (($nl = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $nl);
                $buffer = substr($buffer, $nl + 1);

                if ($line === '') {
                    continue;
                }

                $data = json_decode($line, true);
                if (! is_array($data)) {
                    continue;
                }

                if (isset($data['response']) && $data['response'] !== '') {
                    yield $data['response'];
                }

                if (! empty($data['done'])) {
                    return;
                }
            }
        }
    }
}
