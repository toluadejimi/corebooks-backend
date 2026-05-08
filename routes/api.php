<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\BusinessTokenController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ExpenseApiController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BankTransactionController;
use App\Http\Controllers\Api\V1\ExtraServiceApplicationController;
use App\Http\Controllers\Api\V1\GlAccountController;
use App\Http\Controllers\Api\V1\GlReportController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\ExtraServiceController;
use App\Http\Controllers\Api\V1\LoanApplicationController;
use App\Http\Controllers\Api\V1\LoanPartnerBankController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MediaUploadController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PaystackWebhookController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProposalController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SalesReturnController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\SupplierApiController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Middleware\EnsureBusinessMember;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);
    Route::get('loan-partner-banks', [LoanPartnerBankController::class, 'index']);

    Route::post('webhooks/paystack', [PaystackWebhookController::class, 'handle']);

    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('extra-services', [ExtraServiceController::class, 'index']);

        Route::get('businesses', [BusinessController::class, 'index']);
        Route::post('businesses', [BusinessController::class, 'store']);

        Route::prefix('businesses/{business:uuid}')
            ->middleware(EnsureBusinessMember::class)
            ->group(function (): void {
                Route::get('/', [BusinessController::class, 'show']);
                Route::get('subscription', [SubscriptionController::class, 'show']);

                Route::middleware('business.role:manager')->group(function (): void {
                    Route::post('subscription/initialize', [SubscriptionController::class, 'initializePayment']);
                });

                Route::middleware('business.role:manager')->group(function (): void {
                    Route::get('extra-service-applications', [ExtraServiceApplicationController::class, 'index']);
                    Route::post('extra-service-applications', [ExtraServiceApplicationController::class, 'store']);
                });

                Route::middleware('business.subscription')->group(function (): void {
                    Route::get('categories', [CategoryController::class, 'index']);

                    Route::get('products', [ProductController::class, 'index']);
                    Route::get('products/{product}', [ProductController::class, 'show']);

                    Route::post('sales', [SaleController::class, 'store']);
                    Route::get('sales', [SaleController::class, 'index']);
                    Route::get('sales/{sale:uuid}', [SaleController::class, 'show']);
                    Route::get('sales/{sale:uuid}/returnable', [SalesReturnController::class, 'returnableForSale']);

                    Route::get('customers', [CustomerController::class, 'index']);
                    Route::get('customers/{customer:uuid}', [CustomerController::class, 'show']);

                    Route::get('sales-returns', [SalesReturnController::class, 'index']);
                    Route::get('sales-returns/{salesReturn:uuid}', [SalesReturnController::class, 'show']);

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

                    Route::get('gl-accounts', [GlAccountController::class, 'index']);
                    Route::middleware('business.role:owner')->group(function (): void {
                        Route::post('gl-accounts', [GlAccountController::class, 'store']);
                    });
                    Route::get('journal-entries', [JournalEntryController::class, 'index']);
                    Route::get('gl-trial-balance', [GlReportController::class, 'trialBalance']);

                    Route::get('payroll/me', [PayrollController::class, 'myPayslips']);
                    Route::get('payroll/runs/{run}', [PayrollController::class, 'showRun']);

                    Route::middleware('business.role:manager')->group(function (): void {
                        Route::patch('profile', [BusinessController::class, 'update']);

                        Route::post('uploads', [MediaUploadController::class, 'store']);

                        Route::post('locations', [LocationController::class, 'store']);
                        Route::patch('locations/{location}', [LocationController::class, 'update']);
                        Route::delete('locations/{location}', [LocationController::class, 'destroy']);

                        Route::post('stock-transfers', [StockTransferController::class, 'store']);

                        Route::post('customers', [CustomerController::class, 'store']);
                        Route::patch('customers/{customer:uuid}', [CustomerController::class, 'update']);
                        Route::delete('customers/{customer:uuid}', [CustomerController::class, 'destroy']);

                        Route::post('sales/{sale:uuid}/returns', [SalesReturnController::class, 'store']);

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

                        Route::get('quotations', [QuotationController::class, 'index']);
                        Route::post('quotations', [QuotationController::class, 'store']);
                        Route::get('quotations/{quotation}', [QuotationController::class, 'show']);
                        Route::patch('quotations/{quotation}', [QuotationController::class, 'update']);
                        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy']);
                        Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf']);
                        Route::post('quotations/{quotation}/email', [QuotationController::class, 'email']);

                        Route::post('tokens/consume', [BusinessTokenController::class, 'consume']);

                        Route::post('proposals/ai-draft', [ProposalController::class, 'generateAi']);
                        Route::get('proposals', [ProposalController::class, 'index']);
                        Route::post('proposals', [ProposalController::class, 'store']);
                        Route::get('proposals/{proposal}', [ProposalController::class, 'show']);
                        Route::patch('proposals/{proposal}', [ProposalController::class, 'update']);
                        Route::delete('proposals/{proposal}', [ProposalController::class, 'destroy']);
                        Route::get('proposals/{proposal}/pdf', [ProposalController::class, 'pdf']);
                        Route::post('proposals/{proposal}/email', [ProposalController::class, 'email']);

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

                        Route::get('loan-application', [LoanApplicationController::class, 'show']);
                        Route::put('loan-application', [LoanApplicationController::class, 'update']);
                        Route::post('loan-application/submit', [LoanApplicationController::class, 'submit']);
                        Route::post('loan-application/documents', [LoanApplicationController::class, 'uploadDocument']);

                        Route::post('journal-entries', [JournalEntryController::class, 'store']);
                        Route::get('bank-accounts', [BankAccountController::class, 'index']);
                        Route::post('bank-accounts', [BankAccountController::class, 'store']);
                        Route::get('bank-transactions', [BankTransactionController::class, 'index']);
                        Route::post('bank-transactions', [BankTransactionController::class, 'store']);
                        Route::patch('bank-transactions/{bankTransaction:uuid}', [BankTransactionController::class, 'update']);
                    });
                });
            });
    });
});
