<?php

use App\Http\Controllers\Web\Admin\AccountsWebController;
use App\Http\Controllers\Web\Admin\BusinessSettingsWebController;
use App\Http\Controllers\Web\Admin\BusinessSubscriptionPlatformWebController;
use App\Http\Controllers\Web\Admin\CategoryWebController;
use App\Http\Controllers\Web\Admin\ExtraServiceApplicationPlatformWebController;
use App\Http\Controllers\Web\Admin\ExtraServiceWebController;
use App\Http\Controllers\Web\Admin\GeneralLedgerWebController;
use App\Http\Controllers\Web\Admin\JobPostingPlatformWebController;
use App\Http\Controllers\Web\Admin\JobSeekerPlatformWebController;
use App\Http\Controllers\Web\Admin\LoanApplicationPlatformWebController;
use App\Http\Controllers\Web\Admin\LoanPartnerBankWebController;
use App\Http\Controllers\Web\Admin\LoanWorkspaceWebController;
use App\Http\Controllers\Web\Admin\PayrollWebController;
use App\Http\Controllers\Web\Admin\PlatformMaintenanceWebController;
use App\Http\Controllers\Web\Admin\PortfolioController;
use App\Http\Controllers\Web\Admin\ProductWebController;
use App\Http\Controllers\Web\Admin\PurchaseWebController;
use App\Http\Controllers\Web\Admin\ReportsWebController;
use App\Http\Controllers\Web\Admin\SalesWebController;
use App\Http\Controllers\Web\Admin\StockWebController;
use App\Http\Controllers\Web\Admin\SubscriptionPlanWebController;
use App\Http\Controllers\Web\Admin\SupplierWebController;
use App\Http\Controllers\Web\Admin\TokenSettingsWebController;
use App\Http\Controllers\Web\Admin\TeamWebController;
use App\Http\Controllers\Web\Admin\WorkspaceController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminPasskeySetupController;
use App\Http\Controllers\Web\PaystackSubscriptionReturnController;
use App\Http\Controllers\Web\PublicShopController;
use App\Http\Controllers\WebAuthn\WebAuthnLoginController;
use App\Http\Controllers\WebAuthn\WebAuthnRegisterController;
use App\Http\Middleware\EnsureBusinessMember;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing');

Route::get('/s/{slug}', [PublicShopController::class, 'resolveBySlug'])
    ->where('slug', '[a-z0-9-]+')
    ->name('public.shop.slug');
Route::get('/shop/{business:uuid}', [PublicShopController::class, 'index'])->name('public.shop');
Route::get('/shop/{business:uuid}/p/{product:uuid}', [PublicShopController::class, 'product'])->name('public.shop.product');
Route::post('/shop/{business:uuid}/checkout', [PublicShopController::class, 'checkout'])->name('public.shop.checkout');
Route::get('/shop/{business:uuid}/thanks', [PublicShopController::class, 'thanks'])->name('public.shop.thanks');

Route::get('/paystack/subscription-return', [PaystackSubscriptionReturnController::class, 'show'])
    ->name('paystack.subscription-return');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('login.store');
});

Route::post('/admin/webauthn/login/options', [WebAuthnLoginController::class, 'options'])
    ->middleware('throttle:30,1')
    ->name('webauthn.login.options');
Route::post('/admin/webauthn/login', [WebAuthnLoginController::class, 'login'])
    ->middleware('throttle:15,1')
    ->name('webauthn.login');

Route::post('/admin/logout', [AdminAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/passkey-setup', [AdminPasskeySetupController::class, 'show'])->name('admin.passkey-setup');
    Route::post('/admin/webauthn/register/options', [WebAuthnRegisterController::class, 'options'])
        ->middleware('throttle:20,1')
        ->name('webauthn.register.options');
    Route::post('/admin/webauthn/register', [WebAuthnRegisterController::class, 'register'])
        ->middleware('throttle:20,1')
        ->name('webauthn.register');

    Route::get('/admin', [PortfolioController::class, 'index'])->name('dashboard');
    Route::post('/admin/businesses', [PortfolioController::class, 'store'])->name('admin.businesses.store');

    Route::middleware('platform.admin')->prefix('admin/platform')->name('admin.platform.')->group(function (): void {
        Route::post('run-migrations', [PlatformMaintenanceWebController::class, 'runMigrations'])
            ->middleware('throttle:8,1')
            ->name('migrations.run');

        Route::post('clear-caches', [PlatformMaintenanceWebController::class, 'clearCaches'])
            ->middleware('throttle:8,1')
            ->name('caches.clear');

        Route::get('business-subscriptions', [BusinessSubscriptionPlatformWebController::class, 'index'])->name('business-subscriptions.index');
        Route::get('business-subscriptions/{business}/edit', [BusinessSubscriptionPlatformWebController::class, 'edit'])->name('business-subscriptions.edit');
        Route::put('business-subscriptions/{business}', [BusinessSubscriptionPlatformWebController::class, 'update'])->name('business-subscriptions.update');

        Route::get('subscription-plans', [SubscriptionPlanWebController::class, 'index'])->name('plans.index');
        Route::get('subscription-plans/create', [SubscriptionPlanWebController::class, 'create'])->name('plans.create');
        Route::post('subscription-plans', [SubscriptionPlanWebController::class, 'store'])->name('plans.store');
        Route::get('subscription-plans/{plan}/edit', [SubscriptionPlanWebController::class, 'edit'])->name('plans.edit');
        Route::put('subscription-plans/{plan}', [SubscriptionPlanWebController::class, 'update'])->name('plans.update');
        Route::delete('subscription-plans/{plan}', [SubscriptionPlanWebController::class, 'destroy'])->name('plans.destroy');

        Route::get('loan-applications', [LoanApplicationPlatformWebController::class, 'index'])->name('loans.index');
        Route::get('loan-applications/{loanApplication}', [LoanApplicationPlatformWebController::class, 'show'])->name('loans.show');
        Route::put('loan-applications/{loanApplication}', [LoanApplicationPlatformWebController::class, 'update'])->name('loans.update');

        Route::get('loan-partner-banks', [LoanPartnerBankWebController::class, 'index'])->name('loan-banks.index');
        Route::get('loan-partner-banks/create', [LoanPartnerBankWebController::class, 'create'])->name('loan-banks.create');
        Route::post('loan-partner-banks', [LoanPartnerBankWebController::class, 'store'])->name('loan-banks.store');
        Route::get('loan-partner-banks/{loanPartnerBank}/edit', [LoanPartnerBankWebController::class, 'edit'])->name('loan-banks.edit');
        Route::put('loan-partner-banks/{loanPartnerBank}', [LoanPartnerBankWebController::class, 'update'])->name('loan-banks.update');
        Route::delete('loan-partner-banks/{loanPartnerBank}', [LoanPartnerBankWebController::class, 'destroy'])->name('loan-banks.destroy');

        Route::get('extra-services', [ExtraServiceWebController::class, 'index'])->name('extra-services.index');
        Route::get('extra-services/create', [ExtraServiceWebController::class, 'create'])->name('extra-services.create');
        Route::post('extra-services', [ExtraServiceWebController::class, 'store'])->name('extra-services.store');
        Route::get('extra-services/{extraService}/edit', [ExtraServiceWebController::class, 'edit'])->name('extra-services.edit');
        Route::put('extra-services/{extraService}', [ExtraServiceWebController::class, 'update'])->name('extra-services.update');
        Route::delete('extra-services/{extraService}', [ExtraServiceWebController::class, 'destroy'])->name('extra-services.destroy');

        Route::get('extra-service-applications', [ExtraServiceApplicationPlatformWebController::class, 'index'])->name('extra-service-applications.index');
        Route::get('extra-service-applications/{extraServiceApplication}', [ExtraServiceApplicationPlatformWebController::class, 'show'])->name('extra-service-applications.show');
        Route::put('extra-service-applications/{extraServiceApplication}', [ExtraServiceApplicationPlatformWebController::class, 'update'])->name('extra-service-applications.update');

        Route::get('token-settings', [TokenSettingsWebController::class, 'edit'])->name('token-settings.edit');
        Route::put('token-settings', [TokenSettingsWebController::class, 'update'])->name('token-settings.update');

        Route::get('jobs', [JobPostingPlatformWebController::class, 'index'])->name('jobs.index');
        Route::get('jobs/create', [JobPostingPlatformWebController::class, 'create'])->name('jobs.create');
        Route::post('jobs', [JobPostingPlatformWebController::class, 'store'])->name('jobs.store');
        Route::get('jobs/{job}/edit', [JobPostingPlatformWebController::class, 'edit'])->name('jobs.edit');
        Route::put('jobs/{job}', [JobPostingPlatformWebController::class, 'update'])->name('jobs.update');
        Route::delete('jobs/{job}', [JobPostingPlatformWebController::class, 'destroy'])->name('jobs.destroy');
        Route::post('jobs/{job}/approve', [JobPostingPlatformWebController::class, 'approve'])->name('jobs.approve');
        Route::post('jobs/{job}/reject', [JobPostingPlatformWebController::class, 'reject'])->name('jobs.reject');
        Route::post('jobs/{job}/close', [JobPostingPlatformWebController::class, 'close'])->name('jobs.close');

        Route::get('job-seekers', [JobSeekerPlatformWebController::class, 'index'])->name('job-seekers.index');
        Route::get('job-seekers/create', [JobSeekerPlatformWebController::class, 'create'])->name('job-seekers.create');
        Route::post('job-seekers', [JobSeekerPlatformWebController::class, 'store'])->name('job-seekers.store');
        Route::get('job-seekers/{seeker}/edit', [JobSeekerPlatformWebController::class, 'edit'])->name('job-seekers.edit');
        Route::match(['put', 'patch'], 'job-seekers/{seeker}', [JobSeekerPlatformWebController::class, 'update'])->name('job-seekers.update');
        Route::delete('job-seekers/{seeker}', [JobSeekerPlatformWebController::class, 'destroy'])->name('job-seekers.destroy');
    });

    Route::prefix('admin/b/{business:uuid}')
        ->middleware(EnsureBusinessMember::class)
        ->name('admin.b.')
        ->group(function (): void {
            Route::get('/', [WorkspaceController::class, 'overview'])->name('overview');

            Route::get('/reports/export/{report}/{format}', [ReportsWebController::class, 'export'])
                ->where(['report' => 'daily|trends|pnl|products|payments|expenses|firs|ledger', 'format' => 'pdf|xlsx'])
                ->name('reports.export');
            Route::get('/reports', [ReportsWebController::class, 'index'])->name('reports.index');
            Route::get('/general-ledger', [GeneralLedgerWebController::class, 'index'])->name('ledger.index');

            Route::get('/accounts', [AccountsWebController::class, 'index'])->name('accounts.index');
            Route::middleware('business.role:manager')->group(function (): void {
                Route::post('/accounts', [AccountsWebController::class, 'storeAccount'])->name('accounts.store-account');
                Route::post('/accounts/transfer', [AccountsWebController::class, 'transfer'])->name('accounts.transfer');
                Route::post('/accounts/deposit', [AccountsWebController::class, 'deposit'])->name('accounts.deposit');
                Route::post('/accounts/withdraw', [AccountsWebController::class, 'withdraw'])->name('accounts.withdraw');
            });

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
                // {product} not {product:uuid}: explicit ":uuid" uses parent $business->products() child binding;
                // Product::resolveRouteBinding() already scopes by workspace (same pattern as suppliers).
                Route::get('/products/{product}', [ProductWebController::class, 'redirectToEdit'])->name('products.show');
                Route::get('/products/{product}/edit', [ProductWebController::class, 'edit'])->name('products.edit');
                Route::put('/products/{product}', [ProductWebController::class, 'update'])->name('products.update');
                Route::delete('/products/{product}', [ProductWebController::class, 'destroy'])->name('products.destroy');
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
            // Plain string UUID + manual resolve: supports /purchases/{purchase_order_uuid} and redirects
            // when someone opens /purchases/{product_batch_uuid} (common bookmark mix-up).
            Route::get('/purchases/{purchaseUuid}', [PurchaseWebController::class, 'show'])
                ->whereUuid('purchaseUuid')
                ->name('purchases.show');

            Route::get('/sales', [SalesWebController::class, 'index'])->name('sales.index');
            Route::get('/sales/{sale}', [SalesWebController::class, 'show'])->name('sales.show');

            Route::get('/suppliers', [SupplierWebController::class, 'index'])->name('suppliers.index');
            Route::middleware('business.role:manager')->group(function (): void {
                Route::get('/suppliers/create', [SupplierWebController::class, 'create'])->name('suppliers.create');
                Route::post('/suppliers', [SupplierWebController::class, 'store'])->name('suppliers.store');
                // {supplier} not {supplier:uuid}: ":uuid" forces parent $business->suppliers() child binding;
                // Supplier::resolveRouteBinding() already scopes by workspace.
                Route::get('/suppliers/{supplier}/edit', [SupplierWebController::class, 'edit'])->name('suppliers.edit');
                Route::put('/suppliers/{supplier}', [SupplierWebController::class, 'update'])->name('suppliers.update');
                Route::delete('/suppliers/{supplier}', [SupplierWebController::class, 'destroy'])->name('suppliers.destroy');
            });

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

                Route::get('/loan', [LoanWorkspaceWebController::class, 'edit'])->name('loan.edit');
                Route::put('/loan', [LoanWorkspaceWebController::class, 'update'])->name('loan.update');
                Route::post('/loan/submit', [LoanWorkspaceWebController::class, 'submit'])->name('loan.submit');
            });
        });
});
