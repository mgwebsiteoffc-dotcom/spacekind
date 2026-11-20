<?php

use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PlatformBillingController;
use Illuminate\Support\Facades\Route;

Route::get('/document-share/{token}', [FinanceController::class, 'shared'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('financial.share');
Route::get('/document-share/{token}/pdf', [FinanceController::class, 'shared'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:20,1')->name('financial.share.pdf');
Route::prefix('workspace')->middleware(['auth', 'business', 'owner'])->group(function () {
    Route::get('/finance', [FinanceController::class, 'index']);
    Route::get('/finance/export', [FinanceController::class, 'export']);
    Route::get('/finance/settings', [FinanceController::class, 'settings']);
    Route::patch('/finance/settings', [FinanceController::class, 'saveSettings']);
    Route::get('/finance/catalogue', [FinanceController::class, 'catalogue']);
    Route::post('/finance/catalogue', [FinanceController::class, 'saveCatalogue']);
    Route::get('/finance/documents/new', [FinanceController::class, 'create']);
    Route::post('/finance/documents', [FinanceController::class, 'store']);
    Route::get('/finance/documents/{document}', [FinanceController::class, 'show']);
    Route::get('/finance/documents/{document}/edit', [FinanceController::class, 'edit']);
    Route::patch('/finance/documents/{document}', [FinanceController::class, 'update']);
    Route::get('/finance/documents/{document}/pdf', [FinanceController::class, 'pdf']);
    foreach (['issue', 'sent', 'decision', 'convert', 'copy', 'void', 'reminder', 'share'] as $action) {
        Route::post('/finance/documents/{document}/'.$action, [FinanceController::class, $action]);
    }
    Route::post('/finance/documents/{document}/shares/{share}/revoke', [FinanceController::class, 'revoke']);
    Route::get('/finance/payments', [FinanceController::class, 'payments']);
    Route::post('/finance/payments', [FinanceController::class, 'recordPayment']);
    Route::post('/finance/payments/{payment}/allocate', [FinanceController::class, 'allocate']);
    Route::post('/finance/payments/{payment}/reverse', [FinanceController::class, 'reverse']);
    Route::get('/billing', [PlatformBillingController::class, 'ownerIndex']);
    Route::get('/billing/{invoice}', [PlatformBillingController::class, 'ownerShow'])->whereNumber('invoice');
    Route::post('/billing/{invoice}/checkout/{action}', [PlatformBillingController::class, 'checkout'])->whereNumber('invoice')->whereIn('action', ['pay','refresh'])->middleware('throttle:10,1');
    Route::get('/billing/{invoice}/pdf', [PlatformBillingController::class, 'ownerPdf'])->whereNumber('invoice');
});
Route::prefix('admin/billing')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [PlatformBillingController::class, 'index']);
    Route::patch('/settings', [PlatformBillingController::class, 'settings']);
    Route::post('/', [PlatformBillingController::class, 'store']);
    Route::get('/{invoice}', [PlatformBillingController::class, 'show'])->whereNumber('invoice');
    Route::get('/{invoice}/pdf', [PlatformBillingController::class, 'pdf']);
    Route::post('/{invoice}/checkout/refresh', [PlatformBillingController::class, 'refreshCheckout'])->middleware('throttle:10,1');
    Route::post('/{invoice}/issue', [PlatformBillingController::class, 'issue']);
    Route::post('/{invoice}/payments', [PlatformBillingController::class, 'payment']);
    Route::post('/{invoice}/payments/{payment}/reverse',[PlatformBillingController::class, 'reverse']);
    Route::post('/{invoice}/void',[PlatformBillingController::class, 'void']);
});
