<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OcrService — Reads invoice/receipt images using Google Gemini Vision API.
 * Falls back to text extraction if no image is provided (plain text / PDF text).
 */
class OcrService
{
    protected string $apiKey;
    protected string $visionModel;
    protected string $textModel;
    protected string $baseUrl;
    protected int    $timeout;

    public function __construct()
    {
        $this->apiKey      = config('gemini.api_key', '');
        $this->visionModel = config('gemini.vision_model', 'gemini-2.5-flash');
        $this->textModel   = config('gemini.text_model', 'gemini-2.5-flash');
        $this->baseUrl     = config('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout     = (int) config('gemini.timeout', 120);
    }

    /**
     * Analyze an uploaded image file and extract structured invoice data.
     */
    public function analyzeImage(string $imagePath): array
    {
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType  = mime_content_type($imagePath) ?: 'image/jpeg';

        try {
            $rawText = $this->callGemini(
                model: $this->visionModel,
                prompt: $this->buildImagePrompt(),
                imageBase64: $imageData,
                imageMimeType: $mimeType,
            );

            $result = $this->parseWithRetry($rawText, function () use ($imageData, $mimeType) {
                return $this->callGemini(
                    model: $this->visionModel,
                    prompt: $this->buildRetryPrompt(),
                    imageBase64: $imageData,
                    imageMimeType: $mimeType,
                );
            });

            return $result;

        } catch (\Exception $e) {
            Log::error('OCR service error', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Lỗi OCR: ' . $e->getMessage());
        }
    }

    /**
     * Extract invoice data from plain text (e.g. copied from PDF).
     */
    public function analyzeText(string $text): array
    {
        try {
            $prompt = $this->buildTextPrompt() . "\n\n--- BẮT ĐẦU NỘI DUNG HÓA ĐƠN ---\n" . $text . "\n--- KẾT THÚC NỘI DUNG HÓA ĐƠN ---";

            $rawText = $this->callGemini(
                model: $this->textModel,
                prompt: $prompt,
            );

            $result = $this->parseWithRetry($rawText, function () use ($text) {
                $retryPrompt = $this->buildRetryPrompt() . "\n\n--- BẮT ĐẦU NỘI DUNG HÓA ĐƠN ---\n" . $text . "\n--- KẾT THÚC NỘI DUNG HÓA ĐƠN ---";
                return $this->callGemini(
                    model: $this->textModel,
                    prompt: $retryPrompt,
                );
            });

            return $result;

        } catch (\Exception $e) {
            Log::error('OCR text analysis error', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Lỗi phân tích văn bản: ' . $e->getMessage());
        }
    }

    /**
     * Check Gemini API availability.
     */
    public function availableModels(): array
    {
        if (empty($this->apiKey)) {
            return [
                'vision'       => false,
                'text'         => false,
                'models'       => [],
                'vision_model' => $this->visionModel,
                'text_model'   => $this->textModel,
                'error'        => 'GEMINI_API_KEY chưa được cấu hình',
            ];
        }

        try {
            $res = Http::timeout(10)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->get("{$this->baseUrl}/models");

            if (!$res->ok()) {
                return [
                    'vision' => false,
                    'text'   => false,
                    'models' => [],
                    'error'  => 'API trả về lỗi: ' . $res->status(),
                ];
            }

            $models = collect($res->json('models', []))->pluck('name')->map(fn($n) => str_replace('models/', '', $n))->all();

            return [
                'vision'       => true,
                'text'         => true,
                'models'       => $models,
                'vision_model' => $this->visionModel,
                'text_model'   => $this->textModel,
            ];
        } catch (\Exception $e) {
            return ['vision' => false, 'text' => false, 'models' => [], 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Core: Gemini API call
    // ─────────────────────────────────────────────────────────────

    private function callGemini(
        string  $model,
        string  $prompt,
        ?string $imageBase64 = null,
        ?string $imageMimeType = null,
    ): string {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY chưa được cấu hình. Vui lòng thêm vào file .env');
        }

        // Build parts
        $parts = [];

        // Add image part first if present
        if ($imageBase64 && $imageMimeType) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $imageMimeType,
                    'data'      => $imageBase64,
                ],
            ];
        }

        // Add text prompt
        $parts[] = ['text' => $prompt];

        $payload = [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0,
                'maxOutputTokens' => 4096,
                'topP'            => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = "{$this->baseUrl}/models/{$model}:generateContent";

        Log::debug('OCR calling Gemini', [
            'model'         => $model,
            'url'           => $url,
            'has_image'     => $imageBase64 !== null,
            'prompt_length' => strlen($prompt),
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type'   => 'application/json',
            ])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::warning('Gemini API call failed', [
                'model'  => $model,
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 1000),
            ]);
            throw new \RuntimeException('Gemini API trả về lỗi HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 300));
        }

        // Extract text from Gemini response
        $candidates = $response->json('candidates', []);
        if (empty($candidates)) {
            Log::warning('Gemini returned no candidates', ['body' => substr($response->body(), 0, 500)]);
            throw new \RuntimeException('Gemini không trả về kết quả');
        }

        $content = $candidates[0]['content']['parts'][0]['text'] ?? '';

        Log::debug('OCR raw response', ['raw' => substr($content, 0, 1500)]);

        return $content;
    }

    // ─────────────────────────────────────────────────────────────
    // Prompts — optimized for Gemini with few-shot example
    // ─────────────────────────────────────────────────────────────

    private function buildImagePrompt(): string
    {
        return <<<'PROMPT'
Bạn là chuyên gia OCR hóa đơn Việt Nam. Hãy đọc kỹ ảnh hóa đơn/phiếu nhập kho này và trích xuất CHÍNH XÁC toàn bộ dữ liệu.

YÊU CẦU BẮT BUỘC:
1. Đọc TẤT CẢ các dòng sản phẩm trong bảng, KHÔNG được bỏ sót dòng nào
2. Số tiền phải là số nguyên KHÔNG có dấu phân cách: "120.000" → 120000, "6.000.000" → 6000000
3. Ngày phải ở format YYYY-MM-DD: "15/04/2024" → "2024-04-15"
4. Đọc chính xác tên nhà cung cấp, mã hóa đơn, mã sản phẩm (SKU)
5. Nếu không tìm thấy thông tin, dùng null

Trả về JSON với cấu trúc CHÍNH XÁC sau:
{
  "vendor_name": "tên nhà cung cấp / công ty",
  "invoice_number": "số hóa đơn / phiếu",
  "invoice_date": "YYYY-MM-DD",
  "notes": "ghi chú nếu có",
  "subtotal": 0,
  "tax_amount": 0,
  "total_amount": 0,
  "currency": "VND",
  "items": [
    {
      "name": "tên sản phẩm",
      "sku": "mã SP",
      "quantity": 0,
      "unit": "đơn vị tính",
      "unit_price": 0,
      "total_price": 0
    }
  ]
}

VÍ DỤ: Nếu hóa đơn có dòng "SP001 | Nước Giải Khát | Thùng | 50 | 120.000 | 6.000.000" thì item là:
{"name": "Nước Giải Khát", "sku": "SP001", "quantity": 50, "unit": "Thùng", "unit_price": 120000, "total_price": 6000000}

QUAN TRỌNG: Chỉ trả về JSON, không giải thích gì thêm.
PROMPT;
    }

    private function buildTextPrompt(): string
    {
        return <<<'PROMPT'
Bạn là chuyên gia trích xuất dữ liệu hóa đơn Việt Nam. Hãy đọc nội dung hóa đơn bên dưới và trích xuất CHÍNH XÁC toàn bộ dữ liệu.

YÊU CẦU:
1. Trích xuất TẤT CẢ sản phẩm, KHÔNG bỏ sót
2. Số tiền là số nguyên không dấu phân cách: "120.000" → 120000
3. Ngày format YYYY-MM-DD: "15/04/2024" → "2024-04-15"
4. Nếu không tìm thấy, dùng null

Trả về JSON:
{
  "vendor_name": "tên NCC",
  "invoice_number": "số HĐ",
  "invoice_date": "YYYY-MM-DD",
  "notes": null,
  "subtotal": 0,
  "tax_amount": 0,
  "total_amount": 0,
  "currency": "VND",
  "items": [{"name": "", "sku": "", "quantity": 0, "unit": "", "unit_price": 0, "total_price": 0}]
}

Chỉ trả JSON, không giải thích.

Nội dung hóa đơn:
PROMPT;
    }

    private function buildRetryPrompt(): string
    {
        return <<<'PROMPT'
JSON trước đó không hợp lệ. Hãy trả về JSON hợp lệ với các trường:
vendor_name, invoice_number, invoice_date (YYYY-MM-DD), notes, subtotal, tax_amount, total_amount, currency ("VND"), items (mảng gồm name, sku, quantity, unit, unit_price, total_price).
Số tiền phải là số nguyên không dấu: 120000 không phải "120.000".
Chỉ trả JSON:
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────
    // JSON parsing with retry
    // ─────────────────────────────────────────────────────────────

    private function parseWithRetry(string $rawText, \Closure $retryFn): array
    {
        $result = $this->parseInvoiceJson($rawText);
        if ($result !== null) {
            return $result;
        }

        Log::warning('OCR JSON parse failed on first attempt, retrying', ['raw' => substr($rawText, 0, 800)]);

        try {
            $retryText = $retryFn();
            $result    = $this->parseInvoiceJson($retryText);
            if ($result !== null) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('OCR retry also failed', ['error' => $e->getMessage()]);
        }

        Log::error('OCR could not produce valid JSON after retry', ['raw' => substr($rawText, 0, 800)]);
        return $this->emptySkeleton();
    }

    private function parseInvoiceJson(string $rawText): ?array
    {
        // Strip markdown code fences if present
        $text = preg_replace('/```(?:json)?\s*/i', '', $rawText);
        $text = preg_replace('/```/', '', $text);
        $text = trim($text);

        // Extract the first {...} block
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                return $this->sanitize($data);
            }
        }

        return null;
    }

    private function sanitize(array $data): array
    {
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            // Skip empty/useless items
            $name = trim($item['name'] ?? '');
            if ($name === '' && empty($item['sku'])) {
                continue;
            }

            $items[] = [
                'name'        => $name,
                'sku'         => !empty($item['sku']) ? trim($item['sku']) : null,
                'quantity'    => $this->cleanNumber($item['quantity'] ?? 1, 1),
                'unit'        => trim($item['unit'] ?? 'cái'),
                'unit_price'  => $this->cleanNumber($item['unit_price'] ?? 0),
                'total_price' => $this->cleanNumber($item['total_price'] ?? 0),
            ];
        }

        return [
            'vendor_name'    => $this->cleanString($data['vendor_name'] ?? null),
            'invoice_number' => $this->cleanString($data['invoice_number'] ?? null),
            'invoice_date'   => $this->cleanDate($data['invoice_date'] ?? null),
            'notes'          => $this->cleanString($data['notes'] ?? null),
            'subtotal'       => $this->cleanNumber($data['subtotal'] ?? 0),
            'tax_amount'     => $this->cleanNumber($data['tax_amount'] ?? 0),
            'total_amount'   => $this->cleanNumber($data['total_amount'] ?? 0),
            'currency'       => $data['currency'] ?? 'VND',
            'items'          => $items,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Value cleaners — handle common LLM formatting mistakes
    // ─────────────────────────────────────────────────────────────

    /**
     * Clean a number that might be a string with Vietnamese formatting.
     * "50.000" → 50000, "1,500,000" → 1500000, "5.000đ" → 5000
     */
    private function cleanNumber($value, float $default = 0): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return $default;
        }

        // Remove currency symbols and whitespace
        $cleaned = preg_replace('/[đ₫VNDvnd\s]/', '', $value);

        // Detect Vietnamese formatting: "50.000" (dot as thousand separator)
        // If string has dots but no commas, and ends with 3 digits after last dot → thousand separator
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
        }
        // If string uses commas as thousand separators: "1,500,000"
        elseif (preg_match('/^\d{1,3}(,\d{3})+$/', $cleaned)) {
            $cleaned = str_replace(',', '', $cleaned);
        }

        return is_numeric($cleaned) ? (float) $cleaned : $default;
    }

    /**
     * Clean a string value — return null if empty or literal "null".
     */
    private function cleanString($value): ?string
    {
        if ($value === null || $value === '' || strtolower(trim((string)$value)) === 'null') {
            return null;
        }
        return trim((string)$value);
    }

    /**
     * Clean a date value — try to parse various formats into YYYY-MM-DD.
     */
    private function cleanDate($value): ?string
    {
        if (empty($value) || strtolower(trim((string)$value)) === 'null') {
            return null;
        }

        $value = trim((string)$value);

        // Already YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // DD/MM/YYYY or DD-MM-YYYY (common Vietnamese format)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // Try PHP date parse as fallback
        $ts = strtotime($value);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    private function emptySkeleton(): array
    {
        return [
            'vendor_name'    => null,
            'invoice_number' => null,
            'invoice_date'   => null,
            'notes'          => null,
            'subtotal'       => 0,
            'tax_amount'     => 0,
            'total_amount'   => 0,
            'currency'       => 'VND',
            'items'          => [],
        ];
    }
}
