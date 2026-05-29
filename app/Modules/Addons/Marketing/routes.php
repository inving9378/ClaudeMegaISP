<?php

use App\Modules\Addons\Marketing\Controllers\CampaignController;
use App\Modules\Addons\Marketing\Controllers\ContentGeneratorController;
use App\Modules\Addons\Marketing\Controllers\EvolutionWebhookController;
use App\Modules\Addons\Marketing\Controllers\LeadController;
use App\Modules\Addons\Marketing\Controllers\MarketingController;
use App\Modules\Addons\Marketing\Controllers\MarketingConversationController;
use App\Modules\Addons\Marketing\Controllers\MarketingBrandKitController;
use App\Modules\Addons\Marketing\Controllers\MarketingGeneratedContentController;
use App\Modules\Addons\Marketing\Controllers\MarketingMultivariantCampaignController;
use App\Modules\Addons\Marketing\Controllers\MarketingVideoTemplateController;
use App\Modules\Addons\Marketing\Controllers\MetaAdsWebhookController;
use App\Modules\Addons\Marketing\Controllers\PublicLeadFormController;
use App\Modules\Addons\Marketing\Controllers\MarketingLeadController;
use App\Modules\Addons\Marketing\Controllers\MarketingLeadFormController;
use App\Modules\Addons\Marketing\Controllers\MetaOAuthController;
use App\Modules\Addons\Marketing\Controllers\PublishingController;
use App\Modules\Addons\Marketing\Controllers\VoiceComparatorController;
use Illuminate\Support\Facades\Route;

// ── PÚBLICAS: sin auth ────────────────────────────────────────────────────────
Route::middleware(['web'])->group(function () {
    // Logo serve — auth via permission inside controller
    Route::get('/api/marketing/brand-kit/logo/serve', [MarketingBrandKitController::class, 'serveLogo'])
        ->name('marketing.brand-kit.logo.serve');

    // Meta Ads webhook (GET verify + POST receive)
    Route::match(['get', 'post'], '/webhooks/marketing/meta-ads', [MetaAdsWebhookController::class, 'handle'])
        ->name('marketing.webhook.meta-ads');

    // Evolution API webhook
    Route::post('/webhooks/marketing/evolution', [EvolutionWebhookController::class, 'handle'])
        ->name('marketing.webhook.evolution');

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

    // Conversations API (Fase 3)
    Route::prefix('conversations')->name('conversations.')->group(function () {
        Route::get('/',                                 [MarketingConversationController::class, 'index'])->name('index');
        Route::get('/{id}',                            [MarketingConversationController::class, 'show'])->name('show');
        Route::get('/{id}/messages',                   [MarketingConversationController::class, 'messages'])->name('messages');
        Route::post('/{id}/send-message',              [MarketingConversationController::class, 'sendMessage'])->name('send-message');
        Route::post('/{id}/toggle-ai',                 [MarketingConversationController::class, 'toggleAi'])->name('toggle-ai');
        Route::post('/{id}/assign',                    [MarketingConversationController::class, 'assign'])->name('assign');
        Route::post('/{id}/close',                     [MarketingConversationController::class, 'close'])->name('close');
        Route::post('/{id}/mark-as-read',              [MarketingConversationController::class, 'markAsRead'])->name('mark-as-read');
    });

    // Lead Sources API (for ConversationResolver lookups from Vue)
    Route::get('lead-sources', fn () => response()->json(\App\Models\Marketing\LeadSource::all()))->name('lead-sources.index');

    // Brand Kit API (Fase 4 — MVM)
    Route::prefix('brand-kit')->name('brand-kit.')->group(function () {
        Route::get('/',              [MarketingBrandKitController::class, 'show'])->name('show');
        Route::put('/',              [MarketingBrandKitController::class, 'update'])->name('update');
        Route::post('logo',          [MarketingBrandKitController::class, 'uploadLogo'])->name('logo.upload');
        Route::delete('logo',        [MarketingBrandKitController::class, 'deleteLogo'])->name('logo.delete');
        Route::put('integrations',   [MarketingBrandKitController::class, 'updateIntegrations'])->name('integrations.update');
    });

    // Video Templates API (Fase 4 — MVM)
    Route::prefix('video-templates')->name('video-templates.')->group(function () {
        Route::get('/',              [MarketingVideoTemplateController::class, 'index'])->name('index');
        Route::get('/{id}',         [MarketingVideoTemplateController::class, 'show'])->name('show');
        Route::get('/{id}/variables',[MarketingVideoTemplateController::class, 'variables'])->name('variables');
    });

    // Generated Video Content API (Fase 4 — MVM)
    Route::prefix('generated-content')->name('generated-content.')->group(function () {
        Route::get('/',              [MarketingGeneratedContentController::class, 'index'])->name('index');
        Route::get('/{id}',          [MarketingGeneratedContentController::class, 'show'])->name('show');
        Route::get('/{id}/progress', [MarketingGeneratedContentController::class, 'progress'])->name('progress');
        Route::get('/{id}/download', [MarketingGeneratedContentController::class, 'download'])->name('download');
        Route::post('/render',       [MarketingGeneratedContentController::class, 'render'])->name('render');
        Route::delete('/{id}',       [MarketingGeneratedContentController::class, 'destroy'])->name('destroy');
    });

    // ── Multivariant Campaigns (Phase 4.5b) ──────────────────────────────────────
    Route::prefix('multivariant-campaigns')->name('multivariant-campaigns.')->group(function () {
        Route::get('/',                                    [MarketingMultivariantCampaignController::class, 'index'])->name('index');
        Route::post('/',                                   [MarketingMultivariantCampaignController::class, 'store'])->name('store');
        Route::get('/{id}',                                [MarketingMultivariantCampaignController::class, 'show'])->name('show');
        Route::delete('/{id}',                             [MarketingMultivariantCampaignController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/progress',                       [MarketingMultivariantCampaignController::class, 'progress'])->name('progress');
        Route::post('/{id}/regenerate-variant/{niche}',    [MarketingMultivariantCampaignController::class, 'regenerateVariant'])->name('regenerate-variant');
    });

    Route::prefix('niches')->name('niches.')->group(function () {
        Route::get('/',     [MarketingMultivariantCampaignController::class, 'niches'])->name('index');
        Route::put('/{id}', [MarketingMultivariantCampaignController::class, 'updateNiche'])->name('update');
    });

    // Voice Comparator (Fase 4.5b+)
    Route::prefix('voice-comparator')->name('voice-comparator.')->group(function () {
        Route::get('voices',          [VoiceComparatorController::class, 'listVoices'])->name('voices');
        Route::post('generate-samples',[VoiceComparatorController::class, 'generateSamples'])->name('generate-samples');
        Route::post('assign-niche',   [VoiceComparatorController::class, 'assignToNiche'])->name('assign-niche');
    });
});

// ── STAFF: panel admin (Blade views) ─────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () {

        // Vistas principales Fase 2
        Route::get('/leads', fn () => view('addon-marketing::leads'))->name('leads.view');
        Route::get('/leads/{id}', fn () => view('addon-marketing::lead-detail', ['leadId' => request()->route('id')]))->name('lead-detail.view');
        Route::get('/lead-forms', fn () => view('addon-marketing::lead-forms'))->name('lead-forms.view');
        Route::get('/lead-forms/create', fn () => view('addon-marketing::lead-form-editor', ['formId' => null]))->name('lead-forms.create');
        Route::get('/lead-forms/{id}/edit', fn () => view('addon-marketing::lead-form-editor', ['formId' => request()->route('id')]))->name('lead-forms.edit');

        // Fase 3: Conversaciones
        Route::get('/conversations', fn () => view('addon-marketing::conversations'))->name('conversations.view');
        Route::get('/conversations/{id}', fn () => view('addon-marketing::conversations', ['conversationId' => request()->route('id')]))->name('conversations.detail');

        // Fase 4: Motor de Video
        Route::get('/video-templates', fn () => view('addon-marketing::video-templates'))->name('video-templates.view');
        Route::get('/video-generator',  fn () => view('addon-marketing::video-generator'))->name('video-generator.view');
        Route::get('/video-queue',       fn () => view('addon-marketing::video-queue'))->name('video-queue.view');
        Route::get('/brand-kit',         fn () => view('addon-marketing::brand-kit'))->name('brand-kit.view');

        // Fase 4.5b: Director Creativo IA
        Route::get('/campaigns/generate', fn () => view('addon-marketing::campaign-generator'))->name('campaigns.generate');
        Route::get('/campaigns/multivariant', fn () => view('addon-marketing::campaign-generator'))->name('campaigns.multivariant');

        // Voice Comparator
        Route::get('/voice-comparator', fn () => view('addon-marketing::voice-comparator'))->name('voice-comparator.view');

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

        // ── Fase 5: Publicador Multicanal — Vistas Blade ────────────────────
        Route::get('/publishing', fn () => view('addon-marketing::publishing.dashboard'))->name('publishing.dashboard');
        Route::get('/publishing/setup', fn () => view('addon-marketing::publishing.setup'))->name('publishing.setup');
        Route::get('/publishing/queue', fn () => view('addon-marketing::publishing.queue'))->name('publishing.queue');
        Route::get('/publishing/campaign', fn () => view('addon-marketing::publishing.campaign'))->name('publishing.campaign');

        // Meta OAuth
        Route::get('/meta/oauth/start', [MetaOAuthController::class, 'start'])->name('meta.oauth.start');
        Route::get('/meta/oauth/callback', [MetaOAuthController::class, 'callback'])->name('meta.oauth.callback');
    });

// ── Fase 5: API de Publicación ───────────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('api/marketing/publishing')
    ->name('api.marketing.publishing.')
    ->group(function () {
        // Canales
        Route::get('channels', [PublishingController::class, 'listChannels'])->name('channels.index');
        Route::post('channels/{id}/validate', [PublishingController::class, 'validateChannel'])->name('channels.validate');
        Route::put('channels/{id}/config', [PublishingController::class, 'updateChannelConfig'])->name('channels.config');

        // Smart routing
        Route::get('campaigns/{id}/route', [PublishingController::class, 'routeCampaign'])->name('campaigns.route');

        // Publicaciones
        Route::post('campaigns/{id}/publish', [PublishingController::class, 'publishCampaign'])->name('campaigns.publish');
        Route::get('publications', [PublishingController::class, 'listPublications'])->name('publications.index');
        Route::get('publications/{id}', [PublishingController::class, 'showPublication'])->name('publications.show');
        Route::post('publications/{id}/retry', [PublishingController::class, 'retryPublication'])->name('publications.retry');
        Route::post('publications/{id}/cancel', [PublishingController::class, 'cancelPublication'])->name('publications.cancel');
        Route::get('publications/{id}/metrics', [PublishingController::class, 'fetchMetrics'])->name('publications.metrics');

        // Dashboard
        Route::get('dashboard/stats', [PublishingController::class, 'dashboardStats'])->name('dashboard.stats');
        Route::get('dashboard/recent', [PublishingController::class, 'dashboardRecent'])->name('dashboard.recent');
    });
