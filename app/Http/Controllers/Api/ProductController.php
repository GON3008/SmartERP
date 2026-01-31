<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Services\ActivityLogService;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PhpParser\Node\Expr\FuncCall;

class ProductController extends Controller
{
    protected $productService;
    protected $logService;

    public function __construct(ProductService $productService, ActivityLogService $logService)
    {
        $this->productService = $productService;
        $this->logService = $logService;
    }

    /**
     * Display a listing of products.
     */

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category', 'low_stock', 'sort_by', 'sort_order', 'per_page']);
        $products = $this->productService->getAllProducts($filters);

        return response()->json($products);
    }

    /**
     * Store a newly created product.
     */

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->createProduct($request->validated());

            $this->logService->log('created', 'products', $product->id, "Tạo sản phẩm: {$product->name}");

            return response()->json([
                'message' => 'Create product successfully!',
                'data' => $product,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified product.
     */

    public function show(int $id): JsonResponse
    {
        try {
            $product = $this->productService->getProductById($id);

            return response()->json([
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Product not found!' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified product.
     */

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        try {
            $product = $this->productService->updateProduct($id, $request->validated());

            $this->logService->log('updates', 'products', $product->id, "Cập nhật sản phẩm: {$product->name}");

            return response()->json([
                'message' => 'Update product successfully!',
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified product.
     */

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->productService->deleteProduct($id);

            $this->logService->log('deleted', 'products', $id, "Xóa sản phẩm ID: {$id}");

            return response()->json([
                'message' => 'Delete product successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get low stock products.
     */

    public function lowStock(): JsonResponse
    {
        $products = $this->productService->getLowStockProducts();

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Get product by category.
     */

    public function byCategory(string $category): JsonResponse
    {
        $products = $this->productService->getProductsByCategory($category);

        return response()->json($products);
    }

    /**
     * Search products.
     */

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);

        $products = $this->productService->searchProducts($request->q);

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Get total stock for a product.
     */

    public function totalStock(int $id): JsonResponse
    {
        try {
            $total = $this->productService->getTotalStock($id);

            return response()->json([
                'product_id' => $id,
                'total_stock' => $total,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 404);
        }
    }
}
