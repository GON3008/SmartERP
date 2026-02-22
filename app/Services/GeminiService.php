<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int    $timeout;

    public function __construct()
    {
        $this->apiKey  = config('gemini.api_key', '');
        $this->model   = config('gemini.text_model', 'gemini-2.5-flash');
        $this->baseUrl = config('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout = (int) config('gemini.timeout', 120);
    }

    /**
     * Send a prompt to Gemini and return the response text.
     * @param bool $jsonMode  When true, forces Gemini to return valid JSON.
     */
    public function chat(string $prompt, bool $jsonMode = false): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY chưa được cấu hình. Vui lòng thêm vào file .env');
        }

        try {
            $url = "{$this->baseUrl}/models/{$this->model}:generateContent";

            $generationConfig = [
                'temperature'     => 0,
                'maxOutputTokens' => 4096,
                'topP'            => 0.1,
            ];

            if ($jsonMode) {
                $generationConfig['responseMimeType'] = 'application/json';
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type'   => 'application/json',
                ])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => $generationConfig,
                ]);

            if ($response->failed()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                throw new \RuntimeException('Gemini API returned error: ' . $response->status());
            }

            $candidates = $response->json('candidates', []);
            if (empty($candidates)) {
                throw new \RuntimeException('Gemini không trả về kết quả');
            }

            return $candidates[0]['content']['parts'][0]['text'] ?? '';

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini connection failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Không thể kết nối Gemini API: ' . $e->getMessage());
        }
    }

    /**
     * Check if Gemini API is reachable and return available models.
     */
    public function health(): array
    {
        if (empty($this->apiKey)) {
            return [
                'online'        => false,
                'models'        => [],
                'current_model' => $this->model,
                'error'         => 'GEMINI_API_KEY chưa được cấu hình',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->get("{$this->baseUrl}/models");

            if ($response->ok()) {
                $models = collect($response->json('models', []))
                    ->pluck('name')
                    ->map(fn($n) => str_replace('models/', '', $n))
                    ->values()
                    ->all();

                return ['online' => true, 'models' => $models, 'current_model' => $this->model];
            }

            return ['online' => false, 'models' => [], 'current_model' => $this->model];
        } catch (\Exception $e) {
            return ['online' => false, 'models' => [], 'current_model' => $this->model, 'error' => $e->getMessage()];
        }
    }
}
