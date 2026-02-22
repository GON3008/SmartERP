<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OcrService;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockIn;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OcrController extends Controller
{
    public function __construct(protected OcrService $ocrService) {}

    // ─────────────────────────────────────────────────────────────
    // GET /api/ocr/status
    // ─────────────────────────────────────────────────────────────
    public function status(): \Illuminate\Http\JsonResponse
    {
        $models = $this->ocrService->availableModels();
        return response()->json([
            'success' => true,
            'data'    => $models,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/ocr/scan-image
    // Upload an invoice image → extract structured data
    // ─────────────────────────────────────────────────────────────
    public function scanImage(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,bmp|max:10240',
        ]);

        $file     = $request->file('file');
        $path     = $file->store('ocr_uploads', 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $data = $this->ocrService->analyzeImage($fullPath);
            // Match items to existing products by SKU or name
            $data['items'] = $this->matchProducts($data['items']);
            return response()->json(['success' => true, 'data' => $data]);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/ocr/scan-text
    // Paste text (copied from PDF / email) → extract structured data
    // ─────────────────────────────────────────────────────────────
    public function scanText(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:20000',
        ]);

        $data = $this->ocrService->analyzeText($request->input('text'));
        $data['items'] = $this->matchProducts($data['items']);
        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/ocr/create-stock-in
    // Confirm extracted data → create StockIn records
    // ─────────────────────────────────────────────────────────────
    public function createStockIn(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'warehouse_id'   => 'required|integer|exists:ware_houses,id',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date'   => 'nullable|date',
            'vendor_name'    => 'nullable|string|max:200',
            'notes'          => 'nullable|string|max:1000',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $warehouseId = $request->input('warehouse_id');
        $items       = $request->input('items');
        $note        = collect([
            $request->input('vendor_name') ? "NCC: {$request->input('vendor_name')}" : null,
            $request->input('invoice_number') ? "Hóa đơn: {$request->input('invoice_number')}" : null,
            $request->input('notes'),
        ])->filter()->implode(' | ');

        DB::beginTransaction();
        try {
            $created = [];
            foreach ($items as $item) {
                $stockIn = StockIn::create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'quantity'     => $item['quantity'],
                    'note'         => $note ?: null,
                ]);

                // Update inventory
                $inv = Inventory::firstOrCreate(
                    ['product_id' => $item['product_id'], 'warehouse_id' => $warehouseId],
                    ['quantity'   => 0]
                );
                $inv->increment('quantity', $item['quantity']);

                $created[] = $stockIn->id;
            }

            DB::commit();
            return response()->json([
                'success'       => true,
                'message'       => 'Đã nhập kho thành công từ hóa đơn OCR!',
                'stock_in_ids'  => $created,
                'items_count'   => count($created),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Internal: match OCR items to existing products
    // Uses targeted queries instead of loading the entire product table
    // ─────────────────────────────────────────────────────────────
    private function matchProducts(array $items): array
    {
        if (empty($items)) {
            return $items;
        }

        // Collect all SKUs and names from OCR output
        $skus  = collect($items)->pluck('sku')->filter()->unique()->values()->all();
        $names = collect($items)->pluck('name')->filter()->unique()->values()->all();

        // Single query: fetch candidates by SKU OR name (case-insensitive)
        $candidates = Product::query()
            ->select(['id', 'sku', 'name', 'unit', 'price'])
            ->where(function ($q) use ($skus, $names) {
                if (!empty($skus)) {
                    $q->orWhereIn('sku', $skus);
                }
                foreach ($names as $name) {
                    $q->orWhere('name', 'like', '%' . $name . '%');
                }
            })
            ->get();

        $bySku  = $candidates->keyBy('sku');
        $byName = $candidates->keyBy(fn($p) => strtolower($p->name));

        return array_map(function ($item) use ($bySku, $byName, $candidates) {
            $matched = null;

            // 1. Exact SKU match
            if (!empty($item['sku'])) {
                $matched = $bySku->get($item['sku']);
            }

            // 2. Exact name match (case-insensitive)
            if (!$matched && !empty($item['name'])) {
                $matched = $byName->get(strtolower($item['name']));
            }

            // 3. Partial name match from candidates already fetched
            if (!$matched && !empty($item['name'])) {
                $matched = $candidates->first(fn($p) =>
                    str_contains(strtolower($p->name), strtolower($item['name'])) ||
                    str_contains(strtolower($item['name']), strtolower($p->name))
                );
            }

            $item['product_id']   = $matched?->id;
            $item['product_sku']  = $matched?->sku  ?? $item['sku'];
            $item['matched']      = $matched !== null;
            $item['product_name'] = $matched?->name ?? $item['name'];
            return $item;
        }, $items);
    }
}

