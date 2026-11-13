<?php

use App\Modules\Addons\Marketing\Controllers\CampaignController;
use App\Modules\Addons\Marketing\Controllers\ContentGeneratorController;
use App\Modules\Addons\Marketing\Controllers\ContextoEmpresaController;
use App\Modules\Addons\Marketing\Controllers\EmpresaController;
use App\Modules\Addons\Marketing\Controllers\EmpresaUsuarioController;
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
use App\Modules\Addons\Marketing\Controllers\PilotCampaignController;
use App\Modules\Addons\Marketing\Controllers\PilotCampaignTrackingController;
use App\Modules\Addons\Marketing\Controllers\PublishingController;
use App\Modules\Addons\Marketing\Controllers\VoiceComparatorController;
use Illuminate\Support\Facades\Route;

// ── Fase 0 (Marketing IA 360): capa por empresa ──────────────────────────────
// Toda ruta autenticada del módulo lleva `marketing.empresa` (fija la empresa activa; sin ella el
// scope de empresa no filtra) y el rol por empresa que le toca. Ver docs/marketing/fase-0-paquetes.md §2.5.
//   $V  = ventas también (leads y conversaciones)
//   $M  = solo admin y marketing
//   $MP = configuración compartida de Meganet: además exige Meganet como empresa activa
$V  = ['marketing.empresa', 'marketing.rol:admin,marketing,ventas'];
$M  = ['marketing.empresa', 'marketing.rol:admin,marketing'];
$MP = ['marketing.empresa', 'marketing.rol:admin,marketing', 'marketing.principal'];

// ── PÚBLICAS: sin auth ────────────────────────────────────────────────────────
Route::middleware(['web'])->group(function () {
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

    // Tracking del piloto de campaña A/B (item #47) — lo abre el correo del destinatario, sin sesión.
    Route::prefix('marketing/pilot/track')->name('marketing.pilot.track.')->group(function () {
        Route::get('open/{token}.gif', [PilotCampaignTrackingController::class, 'open'])
            ->where('token', '[A-Za-z0-9]+')->name('open');
        Route::get('click/{token}', [PilotCampaignTrackingController::class, 'click'])
            ->where('token', '[A-Za-z0-9]+')->name('click');
    });
});

// ── API JSON: auth requerida ──────────────────────────────────────────────────
Route::middleware(['web', 'auth'])->prefix('api/marketing')->name('api.marketing.')->group(function () use ($V, $M, $MP) {
    // Leads CRUD
    Route::get('leads', [MarketingLeadController::class, 'index'])->name('leads.index')->middleware($V);
    Route::post('leads', [MarketingLeadController::class, 'store'])->name('leads.store')->middleware($V);
    Route::get('leads/{id}', [MarketingLeadController::class, 'show'])->name('leads.show')->middleware($V);
    Route::put('leads/{id}', [MarketingLeadController::class, 'update'])->name('leads.update')->middleware($V);
    Route::delete('leads/{id}', [MarketingLeadController::class, 'destroy'])->name('leads.destroy')->middleware($M);
    Route::post('leads/{id}/assign', [MarketingLeadController::class, 'assign'])->name('leads.assign')->middleware($M);
    Route::post('leads/{id}/score', [MarketingLeadController::class, 'triggerScoring'])->name('leads.score')->middleware($M);
    Route::get('leads/{id}/activities', [MarketingLeadController::class, 'activities'])->name('leads.activities')->middleware($V);

    // Lead Forms CRUD
    Route::get('lead-forms', [MarketingLeadFormController::class, 'index'])->name('lead-forms.index')->middleware($M);
    Route::post('lead-forms', [MarketingLeadFormController::class, 'store'])->name('lead-forms.store')->middleware($M);
    Route::get('lead-forms/{id}', [MarketingLeadFormController::class, 'show'])->name('lead-forms.show')->middleware($M);
    Route::put('lead-forms/{id}', [MarketingLeadFormController::class, 'update'])->name('lead-forms.update')->middleware($M);
    Route::delete('lead-forms/{id}', [MarketingLeadFormController::class, 'destroy'])->name('lead-forms.destroy')->middleware($M);
    Route::get('lead-forms/{id}/embed-code', [MarketingLeadFormController::class, 'getEmbedCode'])->name('lead-forms.embed-code')->middleware($M);

    // Conversations API (Fase 3)
    Route::prefix('conversations')->name('conversations.')->group(function () use ($V, $M, $MP) {
        Route::get('/',                                 [MarketingConversationController::class, 'index'])->name('index')->middleware($V);
        Route::get('/{id}',                            [MarketingConversationController::class, 'show'])->name('show')->middleware($V);
        Route::get('/{id}/messages',                   [MarketingConversationController::class, 'messages'])->name('messages')->middleware($V);
        Route::post('/{id}/send-message',              [MarketingConversationController::class, 'sendMessage'])->name('send-message')->middleware($V);
        Route::post('/{id}/toggle-ai',                 [MarketingConversationController::class, 'toggleAi'])->name('toggle-ai')->middleware($V);
        Route::post('/{id}/assign',                    [MarketingConversationController::class, 'assign'])->name('assign')->middleware($V);
        Route::post('/{id}/close',                     [MarketingConversationController::class, 'close'])->name('close')->middleware($V);
        Route::post('/{id}/mark-as-read',              [MarketingConversationController::class, 'markAsRead'])->name('mark-as-read')->middleware($V);
    });

    // Lead Sources API (for ConversationResolver lookups from Vue)
    Route::get('lead-sources', fn () => response()->json(\App\Models\Marketing\LeadSource::all()))->name('lead-sources.index')->middleware($V);

    // Brand Kit API (Fase 4 — MVM)
    Route::prefix('brand-kit')->name('brand-kit.')->group(function () use ($V, $M, $MP) {
        Route::get('/',              [MarketingBrandKitController::class, 'show'])->name('show')->middleware($M);
        Route::put('/',              [MarketingBrandKitController::class, 'update'])->name('update')->middleware($MP);
        Route::post('logo',          [MarketingBrandKitController::class, 'uploadLogo'])->name('logo.upload')->middleware($MP);
        Route::delete('logo',        [MarketingBrandKitController::class, 'deleteLogo'])->name('logo.delete')->middleware($MP);
        Route::get('logo/serve',     [MarketingBrandKitController::class, 'serveLogo'])->name('logo.serve')->middleware($M);
        Route::put('integrations',   [MarketingBrandKitController::class, 'updateIntegrations'])->name('integrations.update')->middleware($MP);
    });

    // Video Templates API (Fase 4 — MVM)
    Route::prefix('video-templates')->name('video-templates.')->group(function () use ($V, $M, $MP) {
        Route::get('/',              [MarketingVideoTemplateController::class, 'index'])->name('index')->middleware($M);
        Route::get('/{id}',         [MarketingVideoTemplateController::class, 'show'])->name('show')->middleware($M);
        Route::get('/{id}/variables',[MarketingVideoTemplateController::class, 'variables'])->name('variables')->middleware($M);
    });

    // Generated Video Content API (Fase 4 — MVM)
    Route::prefix('generated-content')->name('generated-content.')->group(function () use ($V, $M, $MP) {
        Route::get('/',              [MarketingGeneratedContentController::class, 'index'])->name('index')->middleware($M);
        Route::get('/{id}',          [MarketingGeneratedContentController::class, 'show'])->name('show')->middleware($M);
        Route::get('/{id}/progress', [MarketingGeneratedContentController::class, 'progress'])->name('progress')->middleware($M);
        Route::get('/{id}/download', [MarketingGeneratedContentController::class, 'download'])->name('download')->middleware($M);
        Route::post('/render',       [MarketingGeneratedContentController::class, 'render'])->name('render')->middleware($M);
        Route::delete('/{id}',       [MarketingGeneratedContentController::class, 'destroy'])->name('destroy')->middleware($M);
    });

    // ── Multivariant Campaigns (Phase 4.5b) ──────────────────────────────────────
    Route::prefix('multivariant-campaigns')->name('multivariant-campaigns.')->group(function () use ($V, $M, $MP) {
        Route::get('/',                                    [MarketingMultivariantCampaignController::class, 'index'])->name('index')->middleware($M);
        Route::post('/',                                   [MarketingMultivariantCampaignController::class, 'store'])->name('store')->middleware($M);
        Route::get('/{id}',                                [MarketingMultivariantCampaignController::class, 'show'])->name('show')->middleware($M);
        Route::delete('/{id}',                             [MarketingMultivariantCampaignController::class, 'destroy'])->name('destroy')->middleware($M);
        Route::get('/{id}/progress',                       [MarketingMultivariantCampaignController::class, 'progress'])->name('progress')->middleware($M);
        Route::post('/{id}/regenerate-variant/{niche}',    [MarketingMultivariantCampaignController::class, 'regenerateVariant'])->name('regenerate-variant')->middleware($M);
    });

    Route::prefix('niches')->name('niches.')->group(function () use ($V, $M, $MP) {
        Route::get('/',     [MarketingMultivariantCampaignController::class, 'niches'])->name('index')->middleware($M);
        Route::put('/{id}', [MarketingMultivariantCampaignController::class, 'updateNiche'])->name('update')->middleware($MP);
    });

    // Voice Comparator (Fase 4.5b+)
    Route::prefix('voice-comparator')->name('voice-comparator.')->group(function () use ($V, $M, $MP) {
        Route::get('voices',          [VoiceComparatorController::class, 'listVoices'])->name('voices')->middleware($M);
        Route::post('generate-samples',[VoiceComparatorController::class, 'generateSamples'])->name('generate-samples')->middleware($M);
        Route::post('assign-niche',   [VoiceComparatorController::class, 'assignToNiche'])->name('assign-niche')->middleware($MP);
    });

    // Piloto de campaña multivariante A/B por email (item #47) — solo lista de prueba interna.
    Route::prefix('pilot-campaigns')->name('pilot-campaigns.')->group(function () use ($V, $M, $MP) {
        Route::get('/',                     [PilotCampaignController::class, 'index'])->name('index')->middleware($M);
        Route::post('/',                    [PilotCampaignController::class, 'store'])->name('store')->middleware($M);
        Route::get('/{id}',                 [PilotCampaignController::class, 'show'])->name('show')->middleware($M);
        Route::delete('/{id}',              [PilotCampaignController::class, 'destroy'])->name('destroy')->middleware($M);
        Route::post('/{id}/dry-run',        [PilotCampaignController::class, 'dryRun'])->name('dry-run')->middleware($M);
        Route::post('/{id}/send',           [PilotCampaignController::class, 'send'])->name('send')->middleware($M);
        Route::post('/{id}/sends/{sendId}/mark-converted', [PilotCampaignController::class, 'markConverted'])->name('mark-converted')->middleware($M);
    });
});

// ── STAFF: panel admin (Blade views) ─────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () use ($V, $M, $MP) {

        // Vistas principales Fase 2
        Route::get('/leads', fn () => view('addon-marketing::leads'))->name('leads.view')->middleware($V);
        Route::get('/leads/{id}', fn () => view('addon-marketing::lead-detail', ['leadId' => request()->route('id')]))->name('lead-detail.view')->middleware($V);
        Route::get('/lead-forms', fn () => view('addon-marketing::lead-forms'))->name('lead-forms.view')->middleware($M);
        Route::get('/lead-forms/create', fn () => view('addon-marketing::lead-form-editor', ['formId' => null]))->name('lead-forms.create')->middleware($M);
        Route::get('/lead-forms/{id}/edit', fn () => view('addon-marketing::lead-form-editor', ['formId' => request()->route('id')]))->name('lead-forms.edit')->middleware($M);

        // Fase 3: Conversaciones
        Route::get('/conversations', fn () => view('addon-marketing::conversations'))->name('conversations.view')->middleware($V);
        Route::get('/conversations/{id}', fn () => view('addon-marketing::conversations', ['conversationId' => request()->route('id')]))->name('conversations.detail')->middleware($V);

        // Fase 4: Motor de Video
        Route::get('/video-templates', fn () => view('addon-marketing::video-templates'))->name('video-templates.view')->middleware($M);
        Route::get('/video-generator',  fn () => view('addon-marketing::video-generator'))->name('video-generator.view')->middleware($M);
        Route::get('/video-queue',       fn () => view('addon-marketing::video-queue'))->name('video-queue.view')->middleware($M);
        Route::get('/brand-kit',         fn () => view('addon-marketing::brand-kit'))->name('brand-kit.view')->middleware($M);

        // Fase 4.5b: Director Creativo IA
        Route::get('/campaigns/generate', fn () => view('addon-marketing::campaign-generator'))->name('campaigns.generate')->middleware($M);
        Route::get('/campaigns/multivariant', fn () => view('addon-marketing::campaign-generator'))->name('campaigns.multivariant')->middleware($M);

        // Voice Comparator
        Route::get('/voice-comparator', fn () => view('addon-marketing::voice-comparator'))->name('voice-comparator.view')->middleware($M);

        // Legado (mantener compatible mientras se migra)
        Route::get('/', [MarketingController::class, 'index'])->name('index')->middleware($M);
        Route::get('/stats', [MarketingController::class, 'stats'])->name('stats')->middleware($M);

        Route::prefix('campaigns')->name('campaigns.')->group(function () use ($V, $M, $MP) {
            Route::get('/', [CampaignController::class, 'index'])->name('index')->middleware($M);
            Route::get('/{id}', [CampaignController::class, 'show'])->name('show')->middleware($M);
            Route::post('/table', [CampaignController::class, 'table'])->name('table')->middleware($M);
            Route::post('/store', [CampaignController::class, 'store'])->name('store')->middleware($M);
            Route::put('/{id}', [CampaignController::class, 'update'])->name('update')->middleware($M);
            Route::delete('/{id}', [CampaignController::class, 'destroy'])->name('destroy')->middleware($M);
            Route::post('/{id}/approve', [CampaignController::class, 'approve'])->name('approve')->middleware($M);
            Route::post('/{id}/pause', [CampaignController::class, 'pause'])->name('pause')->middleware($M);
            Route::post('/{id}/activate', [CampaignController::class, 'activate'])->name('activate')->middleware($M);
        });

        Route::prefix('content')->name('content.')->group(function () use ($V, $M, $MP) {
            Route::post('/generate-copy', [ContentGeneratorController::class, 'generateCopy'])->name('generate-copy')->middleware($M);
            Route::post('/generate-image', [ContentGeneratorController::class, 'generateImage'])->name('generate-image')->middleware($M);
            Route::post('/approve/{id}', [ContentGeneratorController::class, 'approve'])->name('approve')->middleware($M);
            Route::post('/reject/{id}', [ContentGeneratorController::class, 'reject'])->name('reject')->middleware($M);
            Route::get('/campaign/{campaignId}', [ContentGeneratorController::class, 'byCampaign'])->name('by-campaign')->middleware($M);
        });

        Route::prefix('leads-legacy')->name('leads-legacy.')->group(function () use ($V, $M, $MP) {
            Route::post('/table', [LeadController::class, 'table'])->name('table')->middleware($M);
            Route::get('/{id}', [LeadController::class, 'show'])->name('show')->middleware($M);
            Route::post('/{id}/qualify', [LeadController::class, 'qualify'])->name('qualify')->middleware($M);
            Route::put('/{id}/status', [LeadController::class, 'updateStatus'])->name('update-status')->middleware($M);
            Route::post('/{id}/assign', [LeadController::class, 'assign'])->name('assign')->middleware($M);
        });

        Route::prefix('templates')->name('templates.')->group(function () use ($V, $M, $MP) {
            Route::get('/', [MarketingController::class, 'templates'])->name('index')->middleware($M);
            Route::post('/store', [MarketingController::class, 'storeTemplate'])->name('store')->middleware($M);
            Route::put('/{id}', [MarketingController::class, 'updateTemplate'])->name('update')->middleware($M);
            Route::delete('/{id}', [MarketingController::class, 'destroyTemplate'])->name('destroy')->middleware($M);
        });

        // Piloto de campaña multivariante A/B (item #47)
        Route::get('/pilot-campaigns', fn () => view('addon-marketing::pilot-campaigns'))->name('pilot-campaigns.view')->middleware($M);

        // ── Fase 5: Publicador Multicanal — Vistas Blade ────────────────────
        Route::get('/publishing', fn () => view('addon-marketing::publishing.dashboard'))->name('publishing.dashboard')->middleware($M);
        Route::get('/publishing/setup', fn () => view('addon-marketing::publishing.setup'))->name('publishing.setup')->middleware($M);
        Route::get('/publishing/queue', fn () => view('addon-marketing::publishing.queue'))->name('publishing.queue')->middleware($M);
        Route::get('/publishing/campaign', fn () => view('addon-marketing::publishing.campaign'))->name('publishing.campaign')->middleware($M);

        // Meta OAuth
        Route::get('/meta/oauth/start', [MetaOAuthController::class, 'start'])->name('meta.oauth.start')->middleware($MP);
        Route::get('/meta/oauth/callback', [MetaOAuthController::class, 'callback'])->name('meta.oauth.callback')->middleware($MP);
    });

// ── Fase 5: API de Publicación ───────────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('api/marketing/publishing')
    ->name('api.marketing.publishing.')
    ->group(function () use ($V, $M, $MP) {
        // Canales
        Route::get('channels', [PublishingController::class, 'listChannels'])->name('channels.index')->middleware($M);
        Route::post('channels/{id}/validate', [PublishingController::class, 'validateChannel'])->name('channels.validate')->middleware($M);
        Route::put('channels/{id}/config', [PublishingController::class, 'updateChannelConfig'])->name('channels.config')->middleware($M);

        // Smart routing
        Route::get('campaigns/{id}/route', [PublishingController::class, 'routeCampaign'])->name('campaigns.route')->middleware($M);

        // Publicaciones
        Route::post('campaigns/{id}/publish', [PublishingController::class, 'publishCampaign'])->name('campaigns.publish')->middleware($M);
        Route::get('publications', [PublishingController::class, 'listPublications'])->name('publications.index')->middleware($M);
        Route::get('publications/{id}', [PublishingController::class, 'showPublication'])->name('publications.show')->middleware($M);
        Route::post('publications/{id}/retry', [PublishingController::class, 'retryPublication'])->name('publications.retry')->middleware($M);
        Route::post('publications/{id}/cancel', [PublishingController::class, 'cancelPublication'])->name('publications.cancel')->middleware($M);
        Route::get('publications/{id}/metrics', [PublishingController::class, 'fetchMetrics'])->name('publications.metrics')->middleware($M);

        // Dashboard
        Route::get('dashboard/stats', [PublishingController::class, 'dashboardStats'])->name('dashboard.stats')->middleware($M);
        Route::get('dashboard/recent', [PublishingController::class, 'dashboardRecent'])->name('dashboard.recent')->middleware($M);
    });

// ── Fase 0 (Marketing IA 360): contexto de empresa + «Empresas y equipo» ─────
// Fuera de los grupos con check_route_permission (config/route_permission.php no tiene entradas
// /marketing/**). La pantalla de equipo exige rol `admin` en la empresa activa Y el permiso Spatie.
Route::middleware(['web', 'auth', 'marketing.empresa'])->group(function () {
    Route::get('/api/marketing/contexto', [ContextoEmpresaController::class, 'show'])
        ->name('api.marketing.contexto.show');
    Route::post('/api/marketing/contexto/empresa-activa', [ContextoEmpresaController::class, 'cambiar'])
        ->name('api.marketing.contexto.cambiar');

    Route::middleware(['marketing.rol:admin', 'permission:marketing.empresas.gestionar'])->group(function () {
        Route::get('/marketing/empresas', [EmpresaController::class, 'vista'])
            ->name('marketing.empresas.view');
        Route::get('/api/marketing/empresas', [EmpresaController::class, 'index'])
            ->name('api.marketing.empresas.index');
        Route::post('/api/marketing/empresas', [EmpresaController::class, 'store'])
            ->name('api.marketing.empresas.store');
        Route::put('/api/marketing/empresas/{id}', [EmpresaController::class, 'update'])
            ->name('api.marketing.empresas.update');
        Route::get('/api/marketing/empresas/{id}/usuarios', [EmpresaUsuarioController::class, 'index'])
            ->name('api.marketing.empresas.usuarios.index');
        Route::post('/api/marketing/empresas/{id}/usuarios', [EmpresaUsuarioController::class, 'store'])
            ->name('api.marketing.empresas.usuarios.store');
        Route::put('/api/marketing/empresas/{id}/usuarios/{userId}', [EmpresaUsuarioController::class, 'update'])
            ->name('api.marketing.empresas.usuarios.update');
        Route::get('/api/marketing/empresas/{id}/usuarios-disponibles', [EmpresaUsuarioController::class, 'disponibles'])
            ->name('api.marketing.empresas.usuarios.disponibles');
    });
});
