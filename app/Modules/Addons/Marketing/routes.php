<?php

use App\Modules\Addons\Marketing\Controllers\CampaignController;
use App\Modules\Addons\Marketing\Controllers\ContentGeneratorController;
use App\Modules\Addons\Marketing\Controllers\LeadController;
use App\Modules\Addons\Marketing\Controllers\MarketingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () {

        Route::get('/', [MarketingController::class, 'index'])->name('index');
        Route::get('/stats', [MarketingController::class, 'stats'])->name('stats');

        // Campañas
        Route::prefix('campaigns')->name('campaigns.')->group(function () {
            Route::get('/', [CampaignController::class, 'index'])->name('index');
            Route::get('/{id}', [CampaignController::class, 'show'])->name('show');
            Route::post('/table', [CampaignController::class, 'table'])->name('table');
            Route::post('/store', [CampaignController::class, 'store'])->name('store');
            Route::put('/{id}', [CampaignController::class, 'update'])->name('update');
            Route::delete('/{id}', [CampaignController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/approve', [CampaignController::class, 'approve'])->name('approve');
            Route::post('/{id}/pause', [CampaignController::class, 'pause'])->name('pause');
            Route::post('/{id}/activate', [CampaignController::class, 'activate'])->name('activate');
        });

        // Generación de contenido IA
        Route::prefix('content')->name('content.')->group(function () {
            Route::post('/generate-copy', [ContentGeneratorController::class, 'generateCopy'])->name('generate-copy');
            Route::post('/generate-image', [ContentGeneratorController::class, 'generateImage'])->name('generate-image');
            Route::post('/approve/{id}', [ContentGeneratorController::class, 'approve'])->name('approve');
            Route::post('/reject/{id}', [ContentGeneratorController::class, 'reject'])->name('reject');
            Route::get('/campaign/{campaignId}', [ContentGeneratorController::class, 'byCampaign'])->name('by-campaign');
        });

        // Leads
        Route::prefix('leads')->name('leads.')->group(function () {
            Route::post('/table', [LeadController::class, 'table'])->name('table');
            Route::get('/{id}', [LeadController::class, 'show'])->name('show');
            Route::post('/{id}/qualify', [LeadController::class, 'qualify'])->name('qualify');
            Route::put('/{id}/status', [LeadController::class, 'updateStatus'])->name('update-status');
            Route::post('/{id}/assign', [LeadController::class, 'assign'])->name('assign');
        });

        // Plantillas
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', [MarketingController::class, 'templates'])->name('index');
            Route::post('/store', [MarketingController::class, 'storeTemplate'])->name('store');
            Route::put('/{id}', [MarketingController::class, 'updateTemplate'])->name('update');
            Route::delete('/{id}', [MarketingController::class, 'destroyTemplate'])->name('destroy');
        });
    });
