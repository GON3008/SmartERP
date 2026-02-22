<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $baseUrl;
    protected string $model;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('ollama.base_url', 'http://localhost:11434');
        $this->model   = config('ollama.model', 'llama3.2');
        $this->timeout = (int) config('ollama.timeout', 120);
    }

    /**
     * Send a prompt to Ollama and return the response text.
     */
    public function chat(string $prompt): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/generate", [
                    'model'  => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if ($response->failed()) {
                Log::error('Ollama API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException('Ollama API returned error: ' . $response->status());
            }

            return $response->json('response', '');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Ollama connection failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Không thể kết nối Ollama. Hãy chắc ollama đang chạy: ollama serve');
        }
    }

    /**
     * Check if Ollama server is reachable and return available models.
     */
    public function health(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            if ($response->ok()) {
                $models = collect($response->json('models', []))->pluck('name')->values()->all();
                return ['online' => true, 'models' => $models, 'current_model' => $this->model];
            }
            return ['online' => false, 'models' => [], 'current_model' => $this->model];
        } catch (\Exception $e) {
            return ['online' => false, 'models' => [], 'current_model' => $this->model, 'error' => $e->getMessage()];
        }
    }
}
