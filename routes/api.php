<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\CampaignLinkController;
use App\Http\Controllers\FinanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [ApiController::class, 'token'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'business', 'throttle:api'])->group(function () {
        Route::get('/me', [ApiController::class, 'me']);
        Route::get('/invoices', [FinanceController::class, 'api'])->middleware('owner');
        Route::get('/campaign-links', [CampaignLinkController::class, 'api'])->middleware('owner');
        Route::delete('/auth/token', [ApiController::class, 'revoke']);
        Route::get('/leads', [ApiController::class, 'leads']);
        Route::post('/leads', [ApiController::class, 'storeLead']);
        Route::get('/leads/{lead}', [ApiController::class, 'showLead']);
        Route::patch('/leads/{lead}', [ApiController::class, 'updateLead']);
        Route::post('/leads/{lead}/notes', [ApiController::class, 'note']);
        Route::post('/leads/{lead}/convert', [ApiController::class, 'convert']);
        Route::get('/projects', [ApiController::class, 'projects']);
    });
});
