<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryRecommendation;
use App\Models\OrderItem;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiSuggestionController extends Controller
{
    public function __construct(protected OllamaService $ollama) {}

    // GET /api/ai/suggestions  →  Return saved suggestions
    public function index(Request $request)
    {
        $query = InventoryRecommendation::with('product')
            ->orderByDesc('created_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $suggestions = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $suggestions->map(fn($r) => $this->formatRecommendation($r)),
            'meta'    => [
                'total'        => $suggestions->total(),
                'per_page'     => $suggestions->perPage(),
                'current_page' => $suggestions->currentPage(),
                'last_page'    => $suggestions->lastPage(),
            ],
        ]);
    }

    // POST /api/ai/suggestions/generate  →  Run Ollama analysis
    public function generate(Request $request)
    {
        $forecastDays  = (int) $request->get('forecast_days', 30);
        $analysisRange = (int) $request->get('analysis_days', 90);   // past N days for sales
        $minStockOnly  = $request->boolean('low_stock_only', false);

        //Collect product + inventory + sales data
        $productsQuery = Product::with([
            'inventories',
            'orderItems' => fn($q) => $q->whereHas(
                'order',
                fn($o) => $o
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays($analysisRange))
            ),
        ]);

        if ($minStockOnly) {
            $productsQuery->whereHas('inventories', function ($q) {
                $q->whereRaw('quantity <= (SELECT min_stock FROM products WHERE products.id = inventories.product_id)');
            });
        }

        $products = $productsQuery->get();

        if ($products->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Không có sản phẩm nào phù hợp.'], 422);
        }

        //Build concise data summary for the prompt
        $productLines = $products->map(function ($p) use ($analysisRange, $forecastDays) {
            $currentStock = $p->inventories->sum('quantity');
            $totalSold    = $p->orderItems->sum('quantity');
            $avgDaily     = $analysisRange > 0 ? round($totalSold / $analysisRange, 2) : 0;
            $projected    = round($avgDaily * $forecastDays);
            $deficit      = max(0, $projected + $p->min_stock - $currentStock);

            return [
                'id'            => $p->id,
                'name'          => $p->name,
                'sku'           => $p->sku,
                'category'      => $p->category,
                'unit'          => $p->unit,
                'price'         => $p->price,
                'min_stock'     => $p->min_stock,
                'current_stock' => $currentStock,
                'sold_in_period' => $totalSold,
                'avg_daily'     => $avgDaily,
                'projected_need' => $projected,
                'deficit'       => $deficit,
            ];
        });

        //Build Ollama prompt
        $productJson = $productLines->take(30)->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Bạn là chuyên gia quản lý kho hàng cho hệ thống ERP. Dữ liệu sản phẩm bên dưới gồm:
- Tồn kho hiện tại (current_stock)
- Mức tồn kho tối thiểu (min_stock)
- Số lượng bán trung bình mỗi ngày (avg_daily) trong {$analysisRange} ngày qua
- Dự báo cần dùng trong {$forecastDays} ngày tới (projected_need)
- Thiếu hụt ước tính (deficit = projected_need + min_stock - current_stock)

Dữ liệu sản phẩm (JSON):
{$productJson}

Nhiệm vụ:
1. Phân tích và xác định các sản phẩm cần nhập hàng urgently (deficit > 0 hoặc tồn kho < min_stock).
2. Gợi ý số lượng nhập hàng hợp lý cho MỖI sản phẩm cần nhập (bằng tiếng Việt).
3. Ưu tiên sản phẩm bán chạy và thiếu hụt lớn.
4. Trả về JSON hợp lệ theo cấu trúc sau, KHÔNG thêm text nào ngoài JSON:
[
  {
    "product_id": <id>,
    "recommended_quantity": <số lượng đề xuất nhập>,
    "priority": "high" | "medium" | "low",
    "reason": "<lý do ngắn gọn bằng tiếng Việt>"
  },
  ...
]
Chỉ bao gồm các sản phẩm thực sự cần nhập hàng.
PROMPT;

        //Call Ollama
        try {
            $rawResponse = $this->ollama->chat($prompt);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        //Extract JSON from response (Ollama sometimes wraps in markdown)
        $suggestions = $this->parseJsonFromResponse($rawResponse);

        if ($suggestions === null) {
            Log::warning('Ollama returned non-JSON', ['raw' => $rawResponse]);
            return response()->json([
                'success'      => false,
                'message'      => 'Ollama không trả về JSON hợp lệ. Thử lại hoặc đổi model.',
                'raw_response' => $rawResponse,
            ], 422);
        }

        // 6. Save to DB
        $productMap = $productLines->keyBy('id');
        $saved      = [];

        foreach ($suggestions as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if (!isset($productMap[$pid])) continue;

            $meta = $productMap[$pid];

            $rec = InventoryRecommendation::updateOrCreate(
                ['product_id'   => $pid],
                [
                    'avg_daily_sales'      => $meta['avg_daily'],
                    'forecast_days'        => $forecastDays,
                    'recommended_quantity' => (int) ($item['recommended_quantity'] ?? $meta['deficit']),
                    'ai_summary'           => $item['reason'] ?? '',
                ]
            );

            $saved[] = $this->formatRecommendation(
                $rec->load('product'),
                $meta,
                $item['priority'] ?? 'medium'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã phân tích và tạo ' . count($saved) . ' đề xuất nhập hàng.',
            'data'    => $saved,
        ]);
    }


    // GET /api/ai/health  →  Check Ollama status
    public function health()
    {
        return response()->json($this->ollama->health());
    }

    // DELETE /api/ai/suggestions/{id}
    public function destroy(int $id)
    {
        InventoryRecommendation::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa đề xuất.']);
    }


    private function formatRecommendation(InventoryRecommendation $rec, array $meta = [], string $priority = 'medium'): array
    {
        return [
            'id'                    => $rec->id,
            'product_id'            => $rec->product_id,
            'product_name'          => $rec->product?->name,
            'product_sku'           => $rec->product?->sku,
            'category'              => $rec->product?->category,
            'unit'                  => $rec->product?->unit,
            'price'                 => $rec->product?->price,
            'current_stock'         => $meta['current_stock'] ?? null,
            'min_stock'             => $meta['min_stock'] ?? $rec->product?->min_stock,
            'avg_daily_sales'       => $rec->avg_daily_sales,
            'forecast_days'         => $rec->forecast_days,
            'recommended_quantity'  => $rec->recommended_quantity,
            'estimated_cost'        => ($rec->product?->price ?? 0) * $rec->recommended_quantity,
            'ai_summary'            => $rec->ai_summary,
            'priority'              => $priority,
            'created_at'            => $rec->created_at?->toDateTimeString(),
        ];
    }

    private function parseJsonFromResponse(string $raw): ?array
    {
        // Strip markdown code fences
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)```/i', '$1', $raw);
        $clean = trim($clean);

        // Find first [ ... ]
        $start = strpos($clean, '[');
        $end   = strrpos($clean, ']');
        if ($start !== false && $end !== false) {
            $clean = substr($clean, $start, $end - $start + 1);
        }

        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }
}
