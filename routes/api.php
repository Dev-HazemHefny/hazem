<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\TenantRouteBinding;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::middleware(['throttle:auth'])->group(function () {
        Route::post('/auth/register-tenant', [AuthController::class, 'registerTenant']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    Route::middleware(['cron.secret', 'throttle:jobs'])->group(function () {
        Route::post('jobs/run-billing', [JobController::class, 'runBilling']);
        Route::post('jobs/run-revenue-recognition', [JobController::class, 'runRevenueRecognition']);
    });

    Route::middleware(['bootstrap.tenant', 'auth:sanctum', 'tenant', 'throttle:api'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::middleware('admin')->group(function () {
            Route::apiResource('plans', PlanController::class);
            Route::apiResource('customers', CustomerController::class);
            Route::apiResource('subscriptions', SubscriptionController::class);
            Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
            Route::post('subscriptions/{subscription}/change-plan', [SubscriptionController::class, 'changePlan']);

            Route::get('invoices', [InvoiceController::class, 'index']);
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
            Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store']);

            Route::get('accounts', [AccountController::class, 'index']);
            Route::get('journal-entries', [JournalEntryController::class, 'index']);

            Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
            Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
        });
    });
});

Route::bind('plan', fn (string $id) => TenantRouteBinding::resolve(SubscriptionPlan::class, $id));
Route::bind('customer', fn (string $id) => TenantRouteBinding::resolve(Customer::class, $id));
Route::bind('subscription', fn (string $id) => TenantRouteBinding::resolve(Subscription::class, $id));
Route::bind('invoice', fn (string $id) => TenantRouteBinding::resolve(Invoice::class, $id));
