<?php

use App\Modules\Addons\Marketing\Controllers\CampaignController;
use App\Modules\Addons\Marketing\Controllers\ContentGeneratorController;
use App\Modules\Addons\Marketing\Controllers\LeadController;
use App\Modules\Addons\Marketing\Controllers\MarketingController;
use App\Modules\Addons\Marketing\Controllers\MetaAdsWebhookController;
use App\Modules\Addons\Marketing\Controllers\PublicLeadFormController;
use App\Modules\Addons\Marketing\Controllers\MarketingLeadController;
use App\Modules\Addons\Marketing\Controllers\MarketingLeadFormController;
use Illuminate\Support\Facades\Route;

// ── PÚBLICAS: sin auth ────────────────────────────────────────────────────────
Route::middleware(['web'])->group(function () {
    // Meta Ads webhook (GET verify + POST receive)
    Route::match(['get', 'post'], '/webhooks/marketing/meta-ads', [MetaAdsWebhookController::class, 'handle'])
        ->name('marketing.webhook.meta-ads');

    // Formulario web embebible
    Route::prefix('public/marketing')->name('marketing.public.')->group(function () {
        Route::get('lead-form/{slug}', [PublicLeadFormController::class, 'show'])->name('form.show');
        Route::post('lead-form/{slug}/submit', [PublicLeadFormController::class, 'submit'])->name('form.submit');
        Route::get('embed.js', [PublicLeadFormController::class, 'embedScript'])->name('embed.js');
    });
});

// ── API JSON: auth requerida ──────────────────────────────────────────────────
Route::middleware(['web', 'auth'])->prefix('api/marketing')->name('api.marketing.')->group(function () {
    // Leads CRUD
    Route::get('leads', [MarketingLeadController::class, 'index'])->name('leads.index');
    Route::post('leads', [MarketingLeadController::class, 'store'])->name('leads.store');
    Route::get('leads/{id}', [MarketingLeadController::class, 'show'])->name('leads.show');
    Route::put('leads/{id}', [MarketingLeadController::class, 'update'])->name('leads.update');
    Route::delete('leads/{id}', [MarketingLeadController::class, 'destroy'])->name('leads.destroy');
    Route::post('leads/{id}/assign', [MarketingLeadController::class, 'assign'])->name('leads.assign');
    Route::post('leads/{id}/score', [MarketingLeadController::class, 'triggerScoring'])->name('leads.score');
    Route::get('leads/{id}/activities', [MarketingLeadController::class, 'activities'])->name('leads.activities');

    // Lead Forms CRUD
    Route::get('lead-forms', [MarketingLeadFormController::class, 'index'])->name('lead-forms.index');
    Route::post('lead-forms', [MarketingLeadFormController::class, 'store'])->name('lead-forms.store');
    Route::get('lead-forms/{id}', [MarketingLeadFormController::class, 'show'])->name('lead-forms.show');
    Route::put('lead-forms/{id}', [MarketingLeadFormController::class, 'update'])->name('lead-forms.update');
    Route::delete('lead-forms/{id}', [MarketingLeadFormController::class, 'destroy'])->name('lead-forms.destroy');
    Route::get('lead-forms/{id}/embed-code', [MarketingLeadFormController::class, 'getEmbedCode'])->name('lead-forms.embed-code');
});

// ── STAFF: panel admin (Blade views) ─────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () {

        // Vistas principales Fase 2
        Route::get('/leads', fn () => view('addon-marketing::leads'))->name('leads.view');
        Route::get('/leads/{id}', fn () => view('addon-marketing::lead-detail'))->name('lead-detail.view');
        Route::get('/forms', fn () => view('addon-marketing::lead-forms'))->name('forms.view');
        Route::get('/forms/{id}', fn () => view('addon-marketing::lead-form-editor'))->name('form-editor.view');
        Route::get('/forms/new', fn () => view('addon-marketing::lead-form-editor'))->name('form-new.view');

        // Legado (mantener compatible mientras se migra)
        Route::get('/', [MarketingController::class, 'index'])->name('index');
        Route::get('/stats', [MarketingController::class, 'stats'])->name('stats');

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

        Route::prefix('content')->name('content.')->group(function () {
            Route::post('/generate-copy', [ContentGeneratorController::class, 'generateCopy'])->name('generate-copy');
            Route::post('/generate-image', [ContentGeneratorController::class, 'generateImage'])->name('generate-image');
            Route::post('/approve/{id}', [ContentGeneratorController::class, 'approve'])->name('approve');
            Route::post('/reject/{id}', [ContentGeneratorController::class, 'reject'])->name('reject');
            Route::get('/campaign/{campaignId}', [ContentGeneratorController::class, 'byCampaign'])->name('by-campaign');
        });

        Route::prefix('leads-legacy')->name('leads-legacy.')->group(function () {
            Route::post('/table', [LeadController::class, 'table'])->name('table');
            Route::get('/{id}', [LeadController::class, 'show'])->name('show');
            Route::post('/{id}/qualify', [LeadController::class, 'qualify'])->name('qualify');
            Route::put('/{id}/status', [LeadController::class, 'updateStatus'])->name('update-status');
            Route::post('/{id}/assign', [LeadController::class, 'assign'])->name('assign');
        });

        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', [MarketingController::class, 'templates'])->name('index');
            Route::post('/store', [MarketingController::class, 'storeTemplate'])->name('store');
            Route::put('/{id}', [MarketingController::class, 'updateTemplate'])->name('update');
            Route::delete('/{id}', [MarketingController::class, 'destroyTemplate'])->name('destroy');
        });
    });
