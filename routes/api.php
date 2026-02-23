<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AccountController;

/*
|--------------------------------------------------------------------------
| API Routes with RBAC Protection
|--------------------------------------------------------------------------
*/

// ==================== MAINTENANCE STATUS (public, ALWAYS available) ====================
// Uses withoutMiddleware so this route is reachable even when maintenance mode is ON.
// Returns JSON with the real maintenance state so the frontend can act accordingly.
Route::get('maintenance-status', function (\Illuminate\Http\Request $request) {
    $maintenanceFile = storage_path('framework/maintenance.php');
    $enabled = file_exists($maintenanceFile);
    $message = 'Hệ thống đang được nâng cấp, vui lòng quay lại sau...';
    if ($enabled) {
        $payload = @include $maintenanceFile;
        if (!empty($payload['message'])) {
            $message = $payload['message'];
        }
    }
    $origin = $request->header('Origin', '*');
    return response()->json(['enabled' => $enabled, 'message' => $message])
        ->header('Access-Control-Allow-Origin', $origin)
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With');
})->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class);

// ==================== MAINTENANCE CHECK (legacy, kept for compatibility) ====================
Route::get('maintenance-check', function () {
    return response()->json(['status' => 'ok']);
});

// ==================== AUTH ROUTES ====================
Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:otp');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:otp');

    // Refresh token - NO AUTH REQUIRED (access token might be expired)
    Route::post('refresh', [AuthController::class, 'refreshToken'])->middleware('throttle:refresh-token');

    // Protected routes - Cần authentication
    Route::middleware(['auth:api', 'throttle:api-user'])->group(function () {
        // User info & logout
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-current-device', [AuthController::class, 'logoutCurrentDevice']);
        Route::post('change-password', [AuthController::class, 'changePassword']);

        // Token management
        Route::prefix('tokens')->group(function () {
            Route::get('/', [AuthController::class, 'activeTokens']);
            Route::get('info', [AuthController::class, 'tokenInfo']);
            Route::delete('{tokenId}', [AuthController::class, 'revokeToken']);
            Route::delete('/', [AuthController::class, 'revokeOtherTokens']);
        });
    });
});

// ==================== PROTECTED ROUTES ====================
Route::middleware(['auth:api', 'throttle:api-user'])->group(function () {

    // ==================== USER MANAGEMENT ====================
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('permission:view.users');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:create.users');
        Route::get('statistics', [UserController::class, 'statistics'])->middleware('permission:view.users');
        Route::get('{id}', [UserController::class, 'show'])->middleware('permission:view.users');
        Route::put('{id}', [UserController::class, 'update'])->middleware('permission:edit.users');
        Route::delete('{id}', [UserController::class, 'destroy'])->middleware('permission:delete.users');
        Route::post('{id}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:edit.users');
        Route::post('{id}/assign-role', [UserController::class, 'assignRole'])->middleware('permission:edit.users');
        Route::post('{id}/remove-role', [UserController::class, 'removeRole'])->middleware('permission:edit.users');
    });

    // ==================== ROLE MANAGEMENT ====================
    Route::prefix('roles')->middleware('role:Super Admin,Admin')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('{id}', [RoleController::class, 'show']);
        Route::put('{id}', [RoleController::class, 'update']);
        Route::delete('{id}', [RoleController::class, 'destroy']);
        Route::post('{id}/assign-permission', [RoleController::class, 'assignPermission']);
        Route::post('{id}/sync-permissions', [RoleController::class, 'syncPermissions']);
    });

    // ==================== PERMISSION MANAGEMENT ====================
    Route::prefix('permissions')->middleware('role:Super Admin,Admin')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::post('generate-module', [PermissionController::class, 'generateModule']);
    });

    // ==================== PRODUCT MANAGEMENT ====================
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:view.products');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:create.products');
        Route::get('low-stock', [ProductController::class, 'lowStock'])->middleware('permission:view.products');
        Route::get('search', [ProductController::class, 'search'])->middleware('permission:view.products');
        Route::get('category/{category}', [ProductController::class, 'byCategory'])->middleware('permission:view.products');
        Route::get('{id}', [ProductController::class, 'show'])->middleware('permission:view.products');
        Route::put('{id}', [ProductController::class, 'update'])->middleware('permission:edit.products');
        Route::delete('{id}', [ProductController::class, 'destroy'])->middleware('permission:delete.products');
        Route::get('{id}/total-stock', [ProductController::class, 'totalStock'])->middleware('permission:view.products');
    });

    // ==================== ORDER MANAGEMENT ====================
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('permission:view.orders');
        Route::post('/', [OrderController::class, 'store'])->middleware('permission:create.orders');
        Route::get('statistics', [OrderController::class, 'statistics'])->middleware('permission:view.orders');
        Route::get('top-products', [OrderController::class, 'topProducts'])->middleware('permission:view.orders');
        Route::get('{id}', [OrderController::class, 'show'])->middleware('permission:view.orders');
        Route::put('{id}', [OrderController::class, 'update'])->middleware('permission:edit.orders');
        Route::delete('{id}', [OrderController::class, 'destroy'])->middleware('permission:delete.orders');
        Route::post('{id}/process', [OrderController::class, 'process'])->middleware('permission:approve.orders');
        Route::post('{id}/cancel', [OrderController::class, 'cancel'])->middleware('permission:edit.orders');
    });

    // ==================== STOCK MANAGEMENT ====================
    Route::prefix('stock')->group(function () {
        Route::post('in', [StockController::class, 'stockIn'])->middleware('permission:create.stock');
        Route::post('out', [StockController::class, 'stockOut'])->middleware('permission:create.stock');
        Route::post('transfer', [StockController::class, 'transfer'])->middleware('permission:edit.stock');
        Route::get('in/history', [StockController::class, 'stockInHistory'])->middleware('permission:view.stock');
        Route::get('out/history', [StockController::class, 'stockOutHistory'])->middleware('permission:view.stock');
        Route::get('inventory-report/{warehouseId}', [StockController::class, 'inventoryReport'])->middleware('permission:view.inventories');
    });

    // ==================== PRODUCTION MANAGEMENT ====================
    Route::prefix('production')->group(function () {
        Route::get('/', [ProductionController::class, 'index'])->middleware('permission:view.production');
        Route::post('/', [ProductionController::class, 'store'])->middleware('permission:create.production');
        Route::post('check-materials', [ProductionController::class, 'checkMaterials'])->middleware('permission:view.production');
        Route::put('{id}', [ProductionController::class, 'update'])->middleware('permission:edit.production');
        Route::post('{id}/start', [ProductionController::class, 'start'])->middleware('permission:approve.production');
        Route::post('{id}/complete', [ProductionController::class, 'complete'])->middleware('permission:approve.production');
        Route::post('{id}/cancel', [ProductionController::class, 'cancel'])->middleware('permission:edit.production');
    });

    // ==================== EMPLOYEE MANAGEMENT ====================
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:view.employees');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:create.employees');
        Route::post('check-in', [EmployeeController::class, 'checkIn'])->middleware('permission:create.attendances');
        Route::post('check-out', [EmployeeController::class, 'checkOut'])->middleware('permission:create.attendances');
        Route::post('calculate-salary', [EmployeeController::class, 'calculateSalary'])->middleware('permission:create.salaries');
        Route::get('{id}', [EmployeeController::class, 'show'])->middleware('permission:view.employees');
        Route::put('{id}', [EmployeeController::class, 'update'])->middleware('permission:edit.employees');
        Route::delete('{id}', [EmployeeController::class, 'destroy'])->middleware('permission:delete.employees');
        Route::get('{id}/attendance-report', [EmployeeController::class, 'attendanceReport'])->middleware('permission:view.attendances');
    });

    // ==================== CUSTOMER MANAGEMENT ====================
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->middleware('permission:view.customers');
        Route::post('/', [CustomerController::class, 'store'])->middleware('permission:create.customers');
        Route::get('top', [CustomerController::class, 'topCustomers'])->middleware('permission:view.customers');
        Route::get('{id}', [CustomerController::class, 'show'])->middleware('permission:view.customers');
        Route::put('{id}', [CustomerController::class, 'update'])->middleware('permission:edit.customers');
        Route::delete('{id}', [CustomerController::class, 'destroy'])->middleware('permission:delete.customers');
        Route::get('{id}/orders', [CustomerController::class, 'orders'])->middleware('permission:view.orders');
        Route::get('{id}/statistics', [CustomerController::class, 'statistics'])->middleware('permission:view.customers');
    });

    // ==================== WAREHOUSE MANAGEMENT ====================
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->middleware('permission:view.warehouses');
        Route::post('/', [WarehouseController::class, 'store'])->middleware('permission:create.warehouses');
        Route::get('{id}', [WarehouseController::class, 'show'])->middleware('permission:view.warehouses');
        Route::put('{id}', [WarehouseController::class, 'update'])->middleware('permission:edit.warehouses');
        Route::delete('{id}', [WarehouseController::class, 'destroy'])->middleware('permission:delete.warehouses');
        Route::get('{id}/inventory-report', [WarehouseController::class, 'inventoryReport'])->middleware('permission:view.inventories');
        Route::get('{id}/movements', [WarehouseController::class, 'movements'])->middleware('permission:view.stock');
        Route::get('{id}/capacity', [WarehouseController::class, 'capacity'])->middleware('permission:view.warehouses');
    });

    // ==================== DEPARTMENT MANAGEMENT ====================
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:view.departments');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:create.departments');
        Route::get('statistics', [DepartmentController::class, 'statistics'])->middleware('permission:view.departments');
        Route::get('{id}', [DepartmentController::class, 'show'])->middleware('permission:view.departments');
        Route::put('{id}', [DepartmentController::class, 'update'])->middleware('permission:edit.departments');
        Route::delete('{id}', [DepartmentController::class, 'destroy'])->middleware('permission:delete.departments');
        Route::get('{id}/employees', [DepartmentController::class, 'employees'])->middleware('permission:view.employees');
    });

    // ==================== POSITION MANAGEMENT ====================
    Route::prefix('positions')->group(function () {
        Route::get('/', [PositionController::class, 'index'])->middleware('permission:view.positions');
        Route::post('/', [PositionController::class, 'store'])->middleware('permission:create.positions');
        Route::get('statistics', [PositionController::class, 'statistics'])->middleware('permission:view.positions');
        Route::get('{id}', [PositionController::class, 'show'])->middleware('permission:view.positions');
        Route::put('{id}', [PositionController::class, 'update'])->middleware('permission:edit.positions');
        Route::delete('{id}', [PositionController::class, 'destroy'])->middleware('permission:delete.positions');
        Route::get('{id}/employees', [PositionController::class, 'employees'])->middleware('permission:view.employees');
    });

    // ==================== REPORTS & ANALYTICS ====================
    Route::prefix('reports')->middleware(['permission:view.reports', 'throttle:reports'])->group(function () {
        Route::get('dashboard', [ReportController::class, 'dashboard']);
        Route::get('sales', [ReportController::class, 'sales']);
        Route::get('top-products', [ReportController::class, 'topProducts']);
        Route::get('inventory-movement', [ReportController::class, 'inventoryMovement']);
        Route::get('customers', [ReportController::class, 'customers']);
        Route::get('production-efficiency', [ReportController::class, 'productionEfficiency']);
        Route::get('financial-summary', [ReportController::class, 'financialSummary'])
            ->middleware('role:Super Admin,Admin,Accountant');
    });

    // ==================== NOTIFICATIONS ====================
    Route::prefix('notifications')
        ->middleware('throttle:60,1')
        ->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('count', [NotificationController::class, 'count']);
            Route::get('low-stock', [NotificationController::class, 'lowStock']);
            Route::get('pending-orders', [NotificationController::class, 'pendingOrders']);
        });

    // =================== Attendance Management ===================
    Route::prefix('attendances')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->middleware('permission:view.attendances');
        Route::post('/', [AttendanceController::class, 'store'])->middleware('permission:create.attendances');
        Route::get('monthly-summary', [AttendanceController::class, 'monthlySummary'])->middleware('permission:view.attendances');
        Route::get('today-status', [AttendanceController::class, 'todayStatus'])->middleware('permission:view.attendances');
        Route::get('report', [AttendanceController::class, 'report'])->middleware('permission:view.attendances');
        Route::get('late-employees', [AttendanceController::class, 'lateEmployees'])->middleware('permission:view.attendances');
        Route::get('overtime', [AttendanceController::class, 'overtime'])->middleware('permission:view.attendances');
        Route::post('check-in', [AttendanceController::class, 'checkIn'])->middleware('permission:create.attendances');
        Route::post('check-out', [AttendanceController::class, 'checkOut'])->middleware('permission:create.attendances');
        Route::get('{id}', [AttendanceController::class, 'show'])->middleware('permission:view.attendances');
        Route::put('{id}', [AttendanceController::class, 'update'])->middleware('permission:edit.attendances');
        Route::delete('{id}', [AttendanceController::class, 'destroy'])->middleware('permission:delete.attendances');
    });

    // =================== Salary Management ===================
    Route::prefix('salaries')->group(function () {
        Route::get('/', [SalaryController::class, 'index'])->middleware('permission:view.salaries');
        Route::post('/', [SalaryController::class, 'store'])->middleware('permission:create.salaries');
        Route::get('summary', [SalaryController::class, 'summary'])->middleware('permission:view.salaries');
        Route::get('employee', [SalaryController::class, 'employeeSalaries'])->middleware('permission:view.salaries');
        Route::post('generate-payroll', [SalaryController::class, 'generatePayroll'])->middleware('permission:create.salaries');
        Route::get('yearly-statistics', [SalaryController::class, 'yearlyStatistics'])->middleware('permission:view.salaries');
        Route::get('top-earners', [SalaryController::class, 'topEarners'])->middleware('permission:view.salaries');
        Route::get('{id}', [SalaryController::class, 'show'])->middleware('permission:view.salaries');
        Route::put('{id}', [SalaryController::class, 'update'])->middleware('permission:edit.salaries');
        Route::delete('{id}', [SalaryController::class, 'destroy'])->middleware('permission:delete.salaries');
    });

    // =================== Activity Logs ===================
    Route::prefix('activity-logs')->middleware('role:Super Admin,Admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ActivityLogController::class, 'index']);
        Route::get('statistics', [\App\Http\Controllers\Api\ActivityLogController::class, 'statistics']);
        Route::get('{tableName}/{recordId}', [\App\Http\Controllers\Api\ActivityLogController::class, 'show']);
    });

    // =================== AI Purchase Suggestions (Ollama) ===================
    Route::prefix('ai')->group(function () {
        Route::get('health', [\App\Http\Controllers\Api\AiSuggestionController::class, 'health']);
        Route::middleware('permission:view.products')->group(function () {
            Route::get('suggestions', [\App\Http\Controllers\Api\AiSuggestionController::class, 'index']);
            Route::post('suggestions/generate', [\App\Http\Controllers\Api\AiSuggestionController::class, 'generate']);
            Route::delete('suggestions/{id}', [\App\Http\Controllers\Api\AiSuggestionController::class, 'destroy']);
        });
    });

    // =================== Barcode / QR ===================
    Route::prefix('barcode')->middleware('permission:view.products')->group(function () {
        Route::get('scan', [\App\Http\Controllers\Api\BarcodeController::class, 'scan']);
        Route::get('product/{id}', [\App\Http\Controllers\Api\BarcodeController::class, 'forProduct']);
        Route::post('batch', [\App\Http\Controllers\Api\BarcodeController::class, 'batch']);
    });

    // =================== OCR – Invoice / Receipt Reader ===================
    Route::prefix('ocr')->group(function () {
        Route::get('status', [\App\Http\Controllers\Api\OcrController::class, 'status']);
        Route::middleware('permission:view.products')->group(function () {
            Route::post('scan-image', [\App\Http\Controllers\Api\OcrController::class, 'scanImage']);
            Route::post('scan-text',  [\App\Http\Controllers\Api\OcrController::class, 'scanText']);
        Route::post('create-stock-in', [\App\Http\Controllers\Api\OcrController::class, 'createStockIn']);
        });
    });

    // =================== Settings (Super Admin) ===================
    Route::prefix('settings')->middleware('role:Super Admin')->group(function () {
        Route::get('/',            [\App\Http\Controllers\Api\SettingController::class, 'index']);
        Route::post('/',           [\App\Http\Controllers\Api\SettingController::class, 'save']);
        Route::get('status',       [\App\Http\Controllers\Api\SettingController::class, 'systemStatus']);
        Route::post('clear-cache', [\App\Http\Controllers\Api\SettingController::class, 'clearCache']);
        Route::post('optimize-db', [\App\Http\Controllers\Api\SettingController::class, 'optimizeDb']);
        Route::post('test-email',  [\App\Http\Controllers\Api\SettingController::class, 'testEmail']);
        Route::post('maintenance', [\App\Http\Controllers\Api\SettingController::class, 'toggleMaintenance']);
    });

    // =================== SUPPLIER MANAGEMENT ===================
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->middleware('permission:view.suppliers');
        Route::post('/', [SupplierController::class, 'store'])->middleware('permission:create.suppliers');
        Route::get('{id}', [SupplierController::class, 'show'])->middleware('permission:view.suppliers');
        Route::put('{id}', [SupplierController::class, 'update'])->middleware('permission:edit.suppliers');
        Route::delete('{id}', [SupplierController::class, 'destroy'])->middleware('permission:delete.suppliers');
        Route::get('{id}/purchase-history', [SupplierController::class, 'purchaseHistory'])->middleware('permission:view.suppliers');
        Route::get('{id}/statistics', [SupplierController::class, 'statistics'])->middleware('permission:view.suppliers');
    });

    // =================== PURCHASE ORDER MANAGEMENT ===================
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->middleware('permission:view.purchase-orders');
        Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('permission:create.purchase-orders');
        Route::get('statistics', [PurchaseOrderController::class, 'statistics'])->middleware('permission:view.purchase-orders');
        Route::get('{id}', [PurchaseOrderController::class, 'show'])->middleware('permission:view.purchase-orders');
        Route::put('{id}', [PurchaseOrderController::class, 'update'])->middleware('permission:edit.purchase-orders');
        Route::delete('{id}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:delete.purchase-orders');
        Route::post('{id}/confirm', [PurchaseOrderController::class, 'confirm'])->middleware('permission:approve.purchase-orders');
        Route::post('{id}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:approve.purchase-orders');
        Route::post('{id}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:edit.purchase-orders');
    });

    // =================== INVOICE MANAGEMENT ===================
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:view.invoices');
        Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:create.invoices');
        Route::get('statistics', [InvoiceController::class, 'statistics'])->middleware('permission:view.invoices');
        Route::get('{id}', [InvoiceController::class, 'show'])->middleware('permission:view.invoices');
        Route::put('{id}', [InvoiceController::class, 'update'])->middleware('permission:edit.invoices');
        Route::delete('{id}', [InvoiceController::class, 'destroy'])->middleware('permission:delete.invoices');
        Route::post('{id}/send', [InvoiceController::class, 'send'])->middleware('permission:edit.invoices');
        Route::post('{id}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:edit.invoices');
    });

    // =================== PAYMENT MANAGEMENT ===================
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->middleware('permission:view.payments');
        Route::post('/', [PaymentController::class, 'store'])->middleware('permission:create.payments');
        Route::get('statistics', [PaymentController::class, 'statistics'])->middleware('permission:view.payments');
        Route::get('{id}', [PaymentController::class, 'show'])->middleware('permission:view.payments');
        Route::delete('{id}', [PaymentController::class, 'destroy'])->middleware('permission:delete.payments');
    });

    // =================== ACCOUNTS (AR/AP) MANAGEMENT ===================
    Route::prefix('accounts')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->middleware('permission:view.accounts');
        Route::get('summary', [AccountController::class, 'summary'])->middleware('permission:view.accounts');
        Route::get('aging', [AccountController::class, 'aging'])->middleware('permission:view.accounts');
        Route::post('check-overdue', [AccountController::class, 'checkOverdue'])->middleware('permission:edit.accounts');
        Route::get('{id}', [AccountController::class, 'show'])->middleware('permission:view.accounts');
    });
});

// $routes = collect(Route::getRoutes())
//     ->filter(fn ($r) => str_starts_with($r->uri(), 'api/'))
//     ->filter(fn ($r) => in_array('auth:api', $r->middleware()))
//     ->map(fn ($r) => [
//         'method' => strtolower($r->methods()[0]),
//         'uri' => '/'.$r->uri(),
//     ]);
