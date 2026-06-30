<?php

use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\I18nController as AdminI18nController;
use App\Http\Controllers\Api\Admin\OpeningBalanceController as AdminOpeningBalanceController;
use App\Http\Controllers\Api\Admin\ReconciliationController as AdminReconciliationController;
use App\Http\Controllers\Api\Admin\RecyclePriceController as AdminRecyclePriceController;
use App\Http\Controllers\Api\Admin\StoreController as AdminStoreController;
use App\Http\Controllers\Api\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\App\AuthController as AppAuthController;
use App\Http\Controllers\Api\App\CategoryController as AppCategoryController;
use App\Http\Controllers\Api\App\I18nController as AppI18nController;
use App\Http\Controllers\Api\App\OpeningBalanceController as AppOpeningBalanceController;
use App\Http\Controllers\Api\App\TransactionController as AppTransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->group(function (): void {
    Route::get('i18n', AppI18nController::class);
    Route::post('login', [AppAuthController::class, 'login']);
    Route::get('categories', [AppCategoryController::class, 'index']);
    Route::get('transactions', [AppTransactionController::class, 'index']);
    Route::post('transactions', [AppTransactionController::class, 'store']);
    Route::put('transactions/{transaction}', [AppTransactionController::class, 'update']);
    Route::delete('transactions/{transaction}', [AppTransactionController::class, 'destroy']);
    Route::get('stats/monthly', [AppTransactionController::class, 'monthlyStats']);
    Route::get('stats/current', [AppTransactionController::class, 'currentStats']);
    Route::get('opening-balance', [AppOpeningBalanceController::class, 'show']);
    Route::post('opening-balance', [AppOpeningBalanceController::class, 'store']);
});

Route::prefix('admin')->group(function (): void {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::get('me', [AdminAuthController::class, 'me']);
    Route::put('me/profile', [AdminAuthController::class, 'updateProfile']);
    Route::put('me/password', [AdminAuthController::class, 'updatePassword']);
    Route::post('logout', [AdminAuthController::class, 'logout']);
    Route::get('users/permissions', [AdminUserController::class, 'permissions']);
    Route::post('users/{adminUser}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::apiResource('users', AdminUserController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['users' => 'adminUser']);
    Route::apiResource('stores', AdminStoreController::class)->except(['show']);
    Route::apiResource('categories', AdminCategoryController::class)->except(['show']);
    Route::get('transactions', [AdminTransactionController::class, 'index']);
    Route::post('transactions', [AdminTransactionController::class, 'store']);
    Route::put('transactions/{transaction}', [AdminTransactionController::class, 'update']);
    Route::delete('transactions/{transaction}', [AdminTransactionController::class, 'destroy']);
    Route::get('account-details', [AdminTransactionController::class, 'accountDetails']);
    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);
    Route::get('reconciliations/today', [AdminReconciliationController::class, 'today']);
    Route::post('reconciliations/today/{sectionType}/submit', [AdminReconciliationController::class, 'submit']);
    Route::get('reconciliations', [AdminReconciliationController::class, 'index']);
    Route::post('reconciliation-sections/{section}/confirm', [AdminReconciliationController::class, 'confirm']);
    Route::post('reconciliation-sections/{section}/return', [AdminReconciliationController::class, 'returnSection']);
    Route::get('stats/monthly', [AdminTransactionController::class, 'monthlyStats']);
    Route::get('stats/current', [AdminTransactionController::class, 'currentStats']);
    Route::get('recycle-price', [AdminRecyclePriceController::class, 'show']);
    Route::post('recycle-price', [AdminRecyclePriceController::class, 'store']);
    Route::get('opening-balance', [AdminOpeningBalanceController::class, 'show']);
    Route::post('opening-balance', [AdminOpeningBalanceController::class, 'store']);
    Route::get('i18n', [AdminI18nController::class, 'catalog']);
    Route::get('languages', [AdminI18nController::class, 'languages']);
    Route::post('languages', [AdminI18nController::class, 'saveLanguage']);
    Route::get('translations', [AdminI18nController::class, 'translations']);
    Route::post('translations', [AdminI18nController::class, 'saveTranslation']);
});
