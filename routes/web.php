<?php

use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\HealthController;
use App\Http\Controllers\Web\InvoiceController;
use App\Http\Controllers\Web\JobController;
use App\Http\Controllers\Web\JournalEntryController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PlanController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'Subscription Management API',
        'status' => 'running',
    ]);
});
Route::get('/api/documentation', [DocumentationController::class, 'swagger']);
Route::get('/api/documentation/openapi.yaml', [DocumentationController::class, 'openapi']);
Route::get('/docs/postman/collection.json', [DocumentationController::class, 'postman']);

Route::get('/health', HealthController::class);

Route::prefix('web/v1')->group(function () {
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
            Route::get('plans', [PlanController::class, 'index']);
            Route::post('plans', [PlanController::class, 'store']);
            Route::get('plans/{plan}', [PlanController::class, 'show']);
            Route::put('plans/{plan}', [PlanController::class, 'update']);
            Route::delete('plans/{plan}', [PlanController::class, 'destroy']);

            Route::get('customers', [CustomerController::class, 'index']);
            Route::post('customers', [CustomerController::class, 'store']);
            Route::get('customers/{customer}', [CustomerController::class, 'show']);
            Route::put('customers/{customer}', [CustomerController::class, 'update']);
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

            Route::get('subscriptions', [SubscriptionController::class, 'index']);
            Route::post('subscriptions', [SubscriptionController::class, 'store']);
            Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
            Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update']);
            Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy']);
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
