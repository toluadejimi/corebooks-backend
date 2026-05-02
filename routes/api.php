<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MediaUploadController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ExpenseApiController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierApiController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Middleware\EnsureBusinessMember;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('businesses', [BusinessController::class, 'index']);
        Route::post('businesses', [BusinessController::class, 'store']);

        Route::prefix('businesses/{business:uuid}')
            ->middleware(EnsureBusinessMember::class)
            ->group(function (): void {
                Route::get('/', [BusinessController::class, 'show']);

                Route::get('categories', [CategoryController::class, 'index']);

                Route::get('products', [ProductController::class, 'index']);
                Route::get('products/{product}', [ProductController::class, 'show']);

                Route::post('sales', [SaleController::class, 'store']);
                Route::get('sales', [SaleController::class, 'index']);
                Route::get('sales/{sale:uuid}', [SaleController::class, 'show']);

                Route::get('sync/pull', [SyncController::class, 'pull']);

                Route::get('reports/daily-sales', [ReportController::class, 'dailySales']);
                Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
                Route::get('reports/timeseries', [ReportController::class, 'timeseries']);
                Route::get('reports/pnl', [ReportController::class, 'profitLoss']);
                Route::get('reports/products', [ReportController::class, 'products']);
                Route::get('reports/payments', [ReportController::class, 'payments']);
                Route::get('reports/expenses', [ReportController::class, 'expenses']);
                Route::get('reports/firs', [ReportController::class, 'firs']);
                Route::get('reports/sales-ledger', [ReportController::class, 'salesLedger']);

                Route::get('payroll/me', [PayrollController::class, 'myPayslips']);
                Route::get('payroll/runs/{run}', [PayrollController::class, 'showRun']);

                Route::middleware('business.role:manager')->group(function (): void {
                    Route::patch('profile', [BusinessController::class, 'update']);

                    Route::post('uploads', [MediaUploadController::class, 'store']);

                    Route::post('locations', [LocationController::class, 'store']);
                    Route::patch('locations/{location}', [LocationController::class, 'update']);
                    Route::delete('locations/{location}', [LocationController::class, 'destroy']);

                    Route::post('stock-transfers', [StockTransferController::class, 'store']);

                    Route::get('suppliers', [SupplierApiController::class, 'index']);
                    Route::post('purchases', [PurchaseController::class, 'store']);

                    Route::get('expense-entries', [ExpenseApiController::class, 'index']);
                    Route::post('expense-entries', [ExpenseApiController::class, 'store']);
                    Route::patch('expense-entries/{expenseUuid}', [ExpenseApiController::class, 'update']);
                    Route::delete('expense-entries/{expenseUuid}', [ExpenseApiController::class, 'destroy']);

                    Route::post('categories', [CategoryController::class, 'store']);

                    Route::post('products', [ProductController::class, 'store']);
                    Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
                    Route::delete('products/{product}', [ProductController::class, 'destroy']);

                    Route::post('sync/push', [SyncController::class, 'push']);

                    Route::get('team', [TeamMemberController::class, 'index']);
                    Route::post('team', [TeamMemberController::class, 'store']);
                    Route::patch('team/{user}', [TeamMemberController::class, 'update']);
                    Route::delete('team/{user}', [TeamMemberController::class, 'destroy']);

                    Route::get('payroll/runs', [PayrollController::class, 'indexRuns']);
                    Route::post('payroll/runs', [PayrollController::class, 'storeRun']);
                    Route::post('payroll/runs/{run}/lines', [PayrollController::class, 'storeLine']);
                    Route::delete('payroll/runs/{run}/lines/{user}', [PayrollController::class, 'destroyLine']);
                    Route::post('payroll/runs/{run}/finalize', [PayrollController::class, 'finalizeRun']);
                });
            });
    });
});
