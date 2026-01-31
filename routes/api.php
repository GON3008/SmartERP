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

/*
|--------------------------------------------------------------------------
| API Routes with RBAC Protection
|--------------------------------------------------------------------------
*/

// ==================== PUBLIC ROUTES ====================
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// ==================== PROTECTED ROUTES ====================
Route::middleware(['auth:sanctum'])->group(function () {

    // ==================== AUTH ROUTES ====================
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

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
    Route::prefix('reports')->middleware('permission:view.reports')->group(function () {
        Route::get('dashboard', [ReportController::class, 'dashboard']);
        Route::get('sales', [ReportController::class, 'sales']);
        Route::get('top-products', [ReportController::class, 'topProducts']);
        Route::get('inventory-movement', [ReportController::class, 'inventoryMovement']);
        Route::get('customers', [ReportController::class, 'customers']);
        Route::get('production-efficiency', [ReportController::class, 'productionEfficiency']);
        Route::get('financial-summary', [ReportController::class, 'financialSummary'])->middleware('role:Super Admin,Admin,Accountant');
    });

    // ==================== NOTIFICATIONS ====================
    Route::prefix('notifications')->middleware('permission:view.dashboard')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('count', [NotificationController::class, 'count']);
        Route::get('low-stock', [NotificationController::class, 'lowStock']);
        Route::get('pending-orders', [NotificationController::class, 'pendingOrders']);
    });
});
