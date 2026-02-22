<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    /**
     * GET /api/barcode/scan?code=SKU123
     * Lookup a product by barcode/SKU and return full info + inventory.
     */
    public function scan(Request $request)
    {
        $code = trim($request->get('code', ''));
        if (!$code) {
            return response()->json(['success' => false, 'message' => 'Mã barcode không được để trống.'], 422);
        }

        // Search by SKU (exact) first, then partial match on name
        $product = Product::where('sku', $code)->first()
            ?? Product::where('sku', 'like', "%{$code}%")->first()
            ?? Product::where('name', 'like', "%{$code}%")->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "Không tìm thấy sản phẩm với mã: {$code}",
                'code'    => $code,
            ], 404);
        }

        // Aggregate inventory across all warehouses
        $inventories = Inventory::where('product_id', $product->id)
            ->with('wareHouse')
            ->get();

        $totalStock = $inventories->sum('quantity');

        return response()->json([
            'success' => true,
            'data' => [
                'id'            => $product->id,
                'sku'           => $product->sku,
                'name'          => $product->name,
                'category'      => $product->category,
                'unit'          => $product->unit,
                'price'         => $product->price,
                'min_stock'     => $product->min_stock,
                'total_stock'   => $totalStock,
                'stock_status'  => $totalStock <= 0 ? 'out' : ($totalStock <= $product->min_stock ? 'low' : 'ok'),
                'inventories'   => $inventories->map(fn($inv) => [
                    'warehouse_id'   => $inv->warehouse_id,
                    'warehouse_name' => $inv->wareHouse?->name ?? "Kho #{$inv->warehouse_id}",
                    'quantity'       => $inv->quantity,
                ]),
                'barcode_value' => $product->sku,
            ],
        ]);
    }

    /**
     * GET /api/barcode/product/{id}
     * Get barcode data for a specific product.
     */
    public function forProduct(int $id)
    {
        $product = Product::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id'            => $product->id,
                'sku'           => $product->sku,
                'name'          => $product->name,
                'barcode_value' => $product->sku,
                'qr_content'    => json_encode([
                    'id'   => $product->id,
                    'sku'  => $product->sku,
                    'name' => $product->name,
                ]),
            ],
        ]);
    }

    /**
     * POST /api/barcode/batch
     * Get barcode data for multiple products. Body: { ids: [1,2,3] }
     */
    public function batch(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'Danh sách ids không được để trống.'], 422);
        }

        $products = Product::whereIn('id', $ids)->get()->map(fn($p) => [
            'id'            => $p->id,
            'sku'           => $p->sku,
            'name'          => $p->name,
            'barcode_value' => $p->sku,
        ]);

        return response()->json(['success' => true, 'data' => $products]);
    }
}
