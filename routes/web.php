<?php

use App\Http\Controllers\Web\Admin\BusinessSettingsWebController;
use App\Http\Controllers\Web\Admin\CategoryWebController;
use App\Http\Controllers\Web\Admin\PayrollWebController;
use App\Http\Controllers\Web\Admin\PortfolioController;
use App\Http\Controllers\Web\Admin\ProductWebController;
use App\Http\Controllers\Web\Admin\PurchaseWebController;
use App\Http\Controllers\Web\Admin\ReportsWebController;
use App\Http\Controllers\Web\Admin\StockWebController;
use App\Http\Controllers\Web\Admin\TeamWebController;
use App\Http\Controllers\Web\Admin\WorkspaceController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Middleware\EnsureBusinessMember;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('login.store');
});

Route::post('/admin/logout', [AdminAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin', [PortfolioController::class, 'index'])->name('dashboard');
    Route::post('/admin/businesses', [PortfolioController::class, 'store'])->name('admin.businesses.store');

    Route::prefix('admin/b/{business:uuid}')
        ->middleware(EnsureBusinessMember::class)
        ->name('admin.b.')
        ->group(function (): void {
            Route::get('/', [WorkspaceController::class, 'overview'])->name('overview');

            Route::get('/reports/export/{report}/{format}', [ReportsWebController::class, 'export'])
                ->where(['report' => 'daily|trends|pnl|products|payments|expenses|firs|ledger', 'format' => 'pdf|xlsx'])
                ->name('reports.export');
            Route::get('/reports', [ReportsWebController::class, 'index'])->name('reports.index');

            Route::get('/settings', [BusinessSettingsWebController::class, 'edit'])->name('settings.edit');
            Route::middleware('business.role:manager')->group(function (): void {
                Route::put('/settings/profile', [BusinessSettingsWebController::class, 'updateProfile'])->name('settings.profile');
                Route::post('/settings/logo', [BusinessSettingsWebController::class, 'uploadLogo'])->name('settings.logo');
                Route::post('/settings/locations', [BusinessSettingsWebController::class, 'storeLocation'])->name('settings.locations.store');
                Route::delete('/settings/locations/{location}', [BusinessSettingsWebController::class, 'destroyLocation'])->name('settings.locations.destroy');
                Route::post('/categories', [CategoryWebController::class, 'store'])->name('categories.store');
            });

            Route::get('/products', [ProductWebController::class, 'index'])->name('products.index');
            Route::middleware('business.role:manager')->group(function (): void {
                Route::get('/products/create', [ProductWebController::class, 'create'])->name('products.create');
                Route::post('/products', [ProductWebController::class, 'store'])->name('products.store');
                Route::get('/products/{product:uuid}/edit', [ProductWebController::class, 'edit'])->name('products.edit');
                Route::put('/products/{product:uuid}', [ProductWebController::class, 'update'])->name('products.update');
                Route::delete('/products/{product:uuid}', [ProductWebController::class, 'destroy'])->name('products.destroy');
            });

            Route::get('/stock', [StockWebController::class, 'index'])->name('stock.index');
            Route::post('/stock/batches/{batch}/quantity', [StockWebController::class, 'updateQuantity'])
                ->middleware('business.role:manager')
                ->name('stock.batch-qty');

            Route::get('/purchases', [PurchaseWebController::class, 'index'])->name('purchases.index');
            Route::get('/purchases/create', [PurchaseWebController::class, 'create'])
                ->middleware('business.role:manager')
                ->name('purchases.create');
            Route::post('/purchases', [PurchaseWebController::class, 'store'])
                ->middleware('business.role:manager')
                ->name('purchases.store');
            Route::get('/purchases/{purchaseOrder}', [PurchaseWebController::class, 'show'])->name('purchases.show');

            Route::get('/team', [TeamWebController::class, 'index'])->name('team.index');
            Route::middleware('business.role:manager')->group(function (): void {
                Route::post('/team', [TeamWebController::class, 'store'])->name('team.store');
                Route::patch('/team/{user}', [TeamWebController::class, 'update'])->name('team.update');
                Route::delete('/team/{user}', [TeamWebController::class, 'destroy'])->name('team.destroy');
            });

            Route::get('/payroll', [PayrollWebController::class, 'index'])->name('payroll.index');
            Route::get('/payroll/{run}', [PayrollWebController::class, 'show'])->name('payroll.show');
            Route::middleware('business.role:manager')->group(function (): void {
                Route::post('/payroll', [PayrollWebController::class, 'storeRun'])->name('payroll.store');
                Route::post('/payroll/{run}/lines', [PayrollWebController::class, 'storeLine'])->name('payroll.lines.store');
                Route::delete('/payroll/{run}/lines/{user}', [PayrollWebController::class, 'destroyLine'])->name('payroll.lines.destroy');
                Route::post('/payroll/{run}/finalize', [PayrollWebController::class, 'finalize'])->name('payroll.finalize');
            });
        });
});
