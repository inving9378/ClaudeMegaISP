<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — contrato §2.5 / §2.6, riesgos R6 (falla abierta) y R8 (orden de rutas).
 *
 * (a) Toda ruta autenticada `marketing*` / `api/marketing*` lleva `marketing.empresa`; las 7 públicas no.
 * (b) Las 121 rutas existentes conservan método + URI + nombre + acción + middleware previo + ORDEN de
 *     registro, y llevan el grupo de rol de la matriz (P/V/MP/M).
 * (c) Existen las 10 rutas nuevas con su middleware; total 131.
 *
 * La lista fija se escribió desde la base (cc002c0f6, `Route::getRoutes()` en orden de registro).
 */
class RutasMarketingProtegidasTest extends MarketingTestCase
{
    private const NS = 'App\\Modules\\Addons\\Marketing\\Controllers\\';

    private const ROLES_GRUPO = [
        'V'  => ['admin', 'marketing', 'ventas'],
        'MP' => ['admin', 'marketing'],
        'M'  => ['admin', 'marketing'],
    ];

    /** [métodos, uri, nombre, acción, grupo, middleware que ya tenía además de web/auth] — orden de registro de la base. */
    private static function existentes(): array
    {
        return [
            ['GET|POST', 'webhooks/marketing/meta-ads',                                         'marketing.webhook.meta-ads',                              'MetaAdsWebhookController@handle',                                       'P', []],
            ['POST',   'webhooks/marketing/evolution',                                        'marketing.webhook.evolution',                             'EvolutionWebhookController@handle',                                     'P', []],
            ['GET',    'public/marketing/lead-form/{slug}',                                   'marketing.public.form.show',                              'PublicLeadFormController@show',                                         'P', []],
            ['POST',   'public/marketing/lead-form/{slug}/submit',                            'marketing.public.form.submit',                            'PublicLeadFormController@submit',                                       'P', []],
            ['GET',    'public/marketing/embed.js',                                           'marketing.public.embed.js',                               'PublicLeadFormController@embedScript',                                  'P', []],
            ['GET',    'marketing/pilot/track/open/{token}.gif',                              'marketing.pilot.track.open',                              'PilotCampaignTrackingController@open',                                  'P', []],
            ['GET',    'marketing/pilot/track/click/{token}',                                 'marketing.pilot.track.click',                             'PilotCampaignTrackingController@click',                                 'P', []],
            ['GET',    'api/marketing/leads',                                                 'api.marketing.leads.index',                               'MarketingLeadController@index',                                         'V', ['permission:marketing.leads.view']],
            ['POST',   'api/marketing/leads',                                                 'api.marketing.leads.store',                               'MarketingLeadController@store',                                         'V', ['permission:marketing.leads.create']],
            ['GET',    'api/marketing/leads/{id}',                                            'api.marketing.leads.show',                                'MarketingLeadController@show',                                          'V', ['permission:marketing.leads.view']],
            ['PUT',    'api/marketing/leads/{id}',                                            'api.marketing.leads.update',                              'MarketingLeadController@update',                                        'V', ['permission:marketing.leads.update']],
            ['DELETE', 'api/marketing/leads/{id}',                                            'api.marketing.leads.destroy',                             'MarketingLeadController@destroy',                                       'M', ['permission:marketing.leads.delete']],
            ['POST',   'api/marketing/leads/{id}/assign',                                     'api.marketing.leads.assign',                              'MarketingLeadController@assign',                                        'M', ['permission:marketing.leads.assign']],
            ['POST',   'api/marketing/leads/{id}/score',                                      'api.marketing.leads.score',                               'MarketingLeadController@triggerScoring',                                'M', ['permission:marketing.leads.score']],
            ['GET',    'api/marketing/leads/{id}/activities',                                 'api.marketing.leads.activities',                          'MarketingLeadController@activities',                                    'V', ['permission:marketing.leads.view']],
            ['GET',    'api/marketing/lead-forms',                                            'api.marketing.lead-forms.index',                          'MarketingLeadFormController@index',                                     'M', ['permission:marketing.forms.view']],
            ['POST',   'api/marketing/lead-forms',                                            'api.marketing.lead-forms.store',                          'MarketingLeadFormController@store',                                     'M', ['permission:marketing.forms.create']],
            ['GET',    'api/marketing/lead-forms/{id}',                                       'api.marketing.lead-forms.show',                           'MarketingLeadFormController@show',                                      'M', ['permission:marketing.forms.view']],
            ['PUT',    'api/marketing/lead-forms/{id}',                                       'api.marketing.lead-forms.update',                         'MarketingLeadFormController@update',                                    'M', ['permission:marketing.forms.update']],
            ['DELETE', 'api/marketing/lead-forms/{id}',                                       'api.marketing.lead-forms.destroy',                        'MarketingLeadFormController@destroy',                                   'M', ['permission:marketing.forms.delete']],
            ['GET',    'api/marketing/lead-forms/{id}/embed-code',                            'api.marketing.lead-forms.embed-code',                     'MarketingLeadFormController@getEmbedCode',                              'M', []],
            ['GET',    'api/marketing/conversations',                                         'api.marketing.conversations.index',                       'MarketingConversationController@index',                                 'V', []],
            ['GET',    'api/marketing/conversations/{id}',                                    'api.marketing.conversations.show',                        'MarketingConversationController@show',                                  'V', []],
            ['GET',    'api/marketing/conversations/{id}/messages',                           'api.marketing.conversations.messages',                    'MarketingConversationController@messages',                              'V', []],
            ['POST',   'api/marketing/conversations/{id}/send-message',                       'api.marketing.conversations.send-message',                'MarketingConversationController@sendMessage',                           'V', []],
            ['POST',   'api/marketing/conversations/{id}/toggle-ai',                          'api.marketing.conversations.toggle-ai',                   'MarketingConversationController@toggleAi',                              'V', []],
            ['POST',   'api/marketing/conversations/{id}/assign',                             'api.marketing.conversations.assign',                      'MarketingConversationController@assign',                                'V', []],
            ['POST',   'api/marketing/conversations/{id}/close',                              'api.marketing.conversations.close',                       'MarketingConversationController@close',                                 'V', []],
            ['POST',   'api/marketing/conversations/{id}/mark-as-read',                       'api.marketing.conversations.mark-as-read',                'MarketingConversationController@markAsRead',                            'V', []],
            ['GET',    'api/marketing/lead-sources',                                          'api.marketing.lead-sources.index',                        'Closure',                                                               'V', []],
            ['GET',    'api/marketing/brand-kit',                                             'api.marketing.brand-kit.show',                            'MarketingBrandKitController@show',                                      'M', ['permission:marketing.brand_kit.configure']],
            ['PUT',    'api/marketing/brand-kit',                                             'api.marketing.brand-kit.update',                          'MarketingBrandKitController@update',                                    'MP', ['permission:marketing.brand_kit.configure']],
            ['POST',   'api/marketing/brand-kit/logo',                                        'api.marketing.brand-kit.logo.upload',                     'MarketingBrandKitController@uploadLogo',                                'MP', ['permission:marketing.brand_kit.configure']],
            ['DELETE', 'api/marketing/brand-kit/logo',                                        'api.marketing.brand-kit.logo.delete',                     'MarketingBrandKitController@deleteLogo',                                'MP', ['permission:marketing.brand_kit.configure']],
            ['GET',    'api/marketing/brand-kit/logo/serve',                                  'api.marketing.brand-kit.logo.serve',                      'MarketingBrandKitController@serveLogo',                                 'M', ['permission:marketing.brand_kit.configure']],
            ['PUT',    'api/marketing/brand-kit/integrations',                                'api.marketing.brand-kit.integrations.update',             'MarketingBrandKitController@updateIntegrations',                        'MP', ['permission:marketing.brand_kit.configure']],
            ['GET',    'api/marketing/video-templates',                                       'api.marketing.video-templates.index',                     'MarketingVideoTemplateController@index',                                'M', ['permission:view-video-templates']],
            ['GET',    'api/marketing/video-templates/{id}',                                  'api.marketing.video-templates.show',                      'MarketingVideoTemplateController@show',                                 'M', ['permission:view-video-templates']],
            ['GET',    'api/marketing/video-templates/{id}/variables',                        'api.marketing.video-templates.variables',                 'MarketingVideoTemplateController@variables',                            'M', ['permission:view-video-templates']],
            ['GET',    'api/marketing/generated-content',                                     'api.marketing.generated-content.index',                   'MarketingGeneratedContentController@index',                             'M', ['permission:view-video-content']],
            ['GET',    'api/marketing/generated-content/{id}',                                'api.marketing.generated-content.show',                    'MarketingGeneratedContentController@show',                              'M', ['permission:view-video-content']],
            ['GET',    'api/marketing/generated-content/{id}/progress',                       'api.marketing.generated-content.progress',                'MarketingGeneratedContentController@progress',                          'M', ['permission:view-video-content']],
            ['GET',    'api/marketing/generated-content/{id}/download',                       'api.marketing.generated-content.download',                'MarketingGeneratedContentController@download',                          'M', []],
            ['POST',   'api/marketing/generated-content/render',                              'api.marketing.generated-content.render',                  'MarketingGeneratedContentController@render',                            'M', ['permission:generate-video-content']],
            ['DELETE', 'api/marketing/generated-content/{id}',                                'api.marketing.generated-content.destroy',                 'MarketingGeneratedContentController@destroy',                           'M', ['permission:delete-video-content']],
            ['GET',    'api/marketing/multivariant-campaigns',                                'api.marketing.multivariant-campaigns.index',              'MarketingMultivariantCampaignController@index',                         'M', ['permission:view-marketing-campaigns']],
            ['POST',   'api/marketing/multivariant-campaigns',                                'api.marketing.multivariant-campaigns.store',              'MarketingMultivariantCampaignController@store',                         'M', ['permission:create-marketing-campaigns']],
            ['GET',    'api/marketing/multivariant-campaigns/{id}',                           'api.marketing.multivariant-campaigns.show',               'MarketingMultivariantCampaignController@show',                          'M', ['permission:view-marketing-campaigns']],
            ['DELETE', 'api/marketing/multivariant-campaigns/{id}',                           'api.marketing.multivariant-campaigns.destroy',            'MarketingMultivariantCampaignController@destroy',                       'M', ['permission:delete-marketing-campaigns']],
            ['GET',    'api/marketing/multivariant-campaigns/{id}/progress',                  'api.marketing.multivariant-campaigns.progress',           'MarketingMultivariantCampaignController@progress',                      'M', ['permission:view-marketing-campaigns']],
            ['POST',   'api/marketing/multivariant-campaigns/{id}/regenerate-variant/{niche}', 'api.marketing.multivariant-campaigns.regenerate-variant', 'MarketingMultivariantCampaignController@regenerateVariant',             'M', ['permission:regenerate-marketing-variants']],
            ['GET',    'api/marketing/niches',                                                'api.marketing.niches.index',                              'MarketingMultivariantCampaignController@niches',                        'M', ['permission:manage-marketing-niches']],
            ['PUT',    'api/marketing/niches/{id}',                                           'api.marketing.niches.update',                             'MarketingMultivariantCampaignController@updateNiche',                   'MP', ['permission:manage-marketing-niches']],
            ['GET',    'api/marketing/voice-comparator/voices',                               'api.marketing.voice-comparator.voices',                   'VoiceComparatorController@listVoices',                                  'M', ['permission:test-voices']],
            ['POST',   'api/marketing/voice-comparator/generate-samples',                     'api.marketing.voice-comparator.generate-samples',         'VoiceComparatorController@generateSamples',                             'M', ['permission:test-voices']],
            ['POST',   'api/marketing/voice-comparator/assign-niche',                         'api.marketing.voice-comparator.assign-niche',             'VoiceComparatorController@assignToNiche',                               'MP', ['permission:test-voices']],
            ['GET',    'api/marketing/pilot-campaigns',                                       'api.marketing.pilot-campaigns.index',                     'PilotCampaignController@index',                                         'M', []],
            ['POST',   'api/marketing/pilot-campaigns',                                       'api.marketing.pilot-campaigns.store',                     'PilotCampaignController@store',                                         'M', []],
            ['GET',    'api/marketing/pilot-campaigns/{id}',                                  'api.marketing.pilot-campaigns.show',                      'PilotCampaignController@show',                                          'M', []],
            ['DELETE', 'api/marketing/pilot-campaigns/{id}',                                  'api.marketing.pilot-campaigns.destroy',                   'PilotCampaignController@destroy',                                       'M', []],
            ['POST',   'api/marketing/pilot-campaigns/{id}/dry-run',                          'api.marketing.pilot-campaigns.dry-run',                   'PilotCampaignController@dryRun',                                        'M', []],
            ['POST',   'api/marketing/pilot-campaigns/{id}/send',                             'api.marketing.pilot-campaigns.send',                      'PilotCampaignController@send',                                          'M', []],
            ['POST',   'api/marketing/pilot-campaigns/{id}/sends/{sendId}/mark-converted',    'api.marketing.pilot-campaigns.mark-converted',            'PilotCampaignController@markConverted',                                 'M', []],
            ['GET',    'marketing/leads',                                                     'marketing.leads.view',                                    'Closure',                                                               'V', ['check_route_permission']],
            ['GET',    'marketing/leads/{id}',                                                'marketing.lead-detail.view',                              'Closure',                                                               'V', ['check_route_permission']],
            ['GET',    'marketing/lead-forms',                                                'marketing.lead-forms.view',                               'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/lead-forms/create',                                         'marketing.lead-forms.create',                             'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/lead-forms/{id}/edit',                                      'marketing.lead-forms.edit',                               'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/conversations',                                             'marketing.conversations.view',                            'Closure',                                                               'V', ['check_route_permission']],
            ['GET',    'marketing/conversations/{id}',                                        'marketing.conversations.detail',                          'Closure',                                                               'V', ['check_route_permission']],
            ['GET',    'marketing/video-templates',                                           'marketing.video-templates.view',                          'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/video-generator',                                           'marketing.video-generator.view',                          'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/video-queue',                                               'marketing.video-queue.view',                              'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/brand-kit',                                                 'marketing.brand-kit.view',                                'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/campaigns/generate',                                        'marketing.campaigns.generate',                            'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/campaigns/multivariant',                                    'marketing.campaigns.multivariant',                        'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/voice-comparator',                                          'marketing.voice-comparator.view',                         'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing',                                                           'marketing.index',                                         'MarketingController@index',                                             'M', ['check_route_permission']],
            ['GET',    'marketing/stats',                                                     'marketing.stats',                                         'MarketingController@stats',                                             'M', ['check_route_permission']],
            ['GET',    'marketing/campaigns',                                                 'marketing.campaigns.index',                               'CampaignController@index',                                              'M', ['check_route_permission']],
            ['GET',    'marketing/campaigns/{id}',                                            'marketing.campaigns.show',                                'CampaignController@show',                                               'M', ['check_route_permission']],
            ['POST',   'marketing/campaigns/table',                                           'marketing.campaigns.table',                               'CampaignController@table',                                              'M', ['check_route_permission']],
            ['POST',   'marketing/campaigns/store',                                           'marketing.campaigns.store',                               'CampaignController@store',                                              'M', ['check_route_permission']],
            ['PUT',    'marketing/campaigns/{id}',                                            'marketing.campaigns.update',                              'CampaignController@update',                                             'M', ['check_route_permission']],
            ['DELETE', 'marketing/campaigns/{id}',                                            'marketing.campaigns.destroy',                             'CampaignController@destroy',                                            'M', ['check_route_permission']],
            ['POST',   'marketing/campaigns/{id}/approve',                                    'marketing.campaigns.approve',                             'CampaignController@approve',                                            'M', ['check_route_permission']],
            ['POST',   'marketing/campaigns/{id}/pause',                                      'marketing.campaigns.pause',                               'CampaignController@pause',                                              'M', ['check_route_permission']],
            ['POST',   'marketing/campaigns/{id}/activate',                                   'marketing.campaigns.activate',                            'CampaignController@activate',                                           'M', ['check_route_permission']],
            ['POST',   'marketing/content/generate-copy',                                     'marketing.content.generate-copy',                         'ContentGeneratorController@generateCopy',                               'M', ['check_route_permission']],
            ['POST',   'marketing/content/generate-image',                                    'marketing.content.generate-image',                        'ContentGeneratorController@generateImage',                              'M', ['check_route_permission']],
            ['POST',   'marketing/content/approve/{id}',                                      'marketing.content.approve',                               'ContentGeneratorController@approve',                                    'M', ['check_route_permission']],
            ['POST',   'marketing/content/reject/{id}',                                       'marketing.content.reject',                                'ContentGeneratorController@reject',                                     'M', ['check_route_permission']],
            ['GET',    'marketing/content/campaign/{campaignId}',                             'marketing.content.by-campaign',                           'ContentGeneratorController@byCampaign',                                 'M', ['check_route_permission']],
            ['POST',   'marketing/leads-legacy/table',                                        'marketing.leads-legacy.table',                            'LeadController@table',                                                  'M', ['check_route_permission']],
            ['GET',    'marketing/leads-legacy/{id}',                                         'marketing.leads-legacy.show',                             'LeadController@show',                                                   'M', ['check_route_permission']],
            ['POST',   'marketing/leads-legacy/{id}/qualify',                                 'marketing.leads-legacy.qualify',                          'LeadController@qualify',                                                'M', ['check_route_permission']],
            ['PUT',    'marketing/leads-legacy/{id}/status',                                  'marketing.leads-legacy.update-status',                    'LeadController@updateStatus',                                           'M', ['check_route_permission']],
            ['POST',   'marketing/leads-legacy/{id}/assign',                                  'marketing.leads-legacy.assign',                           'LeadController@assign',                                                 'M', ['check_route_permission']],
            ['GET',    'marketing/templates',                                                 'marketing.templates.index',                               'MarketingController@templates',                                         'M', ['check_route_permission']],
            ['POST',   'marketing/templates/store',                                           'marketing.templates.store',                               'MarketingController@storeTemplate',                                     'M', ['check_route_permission']],
            ['PUT',    'marketing/templates/{id}',                                            'marketing.templates.update',                              'MarketingController@updateTemplate',                                    'M', ['check_route_permission']],
            ['DELETE', 'marketing/templates/{id}',                                            'marketing.templates.destroy',                             'MarketingController@destroyTemplate',                                   'M', ['check_route_permission']],
            ['GET',    'marketing/pilot-campaigns',                                           'marketing.pilot-campaigns.view',                          'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/publishing',                                                'marketing.publishing.dashboard',                          'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/publishing/setup',                                          'marketing.publishing.setup',                              'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/publishing/queue',                                          'marketing.publishing.queue',                              'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/publishing/campaign',                                       'marketing.publishing.campaign',                           'Closure',                                                               'M', ['check_route_permission']],
            ['GET',    'marketing/meta/oauth/start',                                          'marketing.meta.oauth.start',                              'MetaOAuthController@start',                                             'MP', ['check_route_permission']],
            ['GET',    'marketing/meta/oauth/callback',                                       'marketing.meta.oauth.callback',                           'MetaOAuthController@callback',                                          'MP', ['check_route_permission']],
            ['GET',    'api/marketing/publishing/channels',                                   'api.marketing.publishing.channels.index',                 'PublishingController@listChannels',                                     'M', ['check_route_permission']],
            ['POST',   'api/marketing/publishing/channels/{id}/validate',                     'api.marketing.publishing.channels.validate',              'PublishingController@validateChannel',                                  'M', ['check_route_permission']],
            ['PUT',    'api/marketing/publishing/channels/{id}/config',                       'api.marketing.publishing.channels.config',                'PublishingController@updateChannelConfig',                              'M', ['check_route_permission']],
            ['GET',    'api/marketing/publishing/campaigns/{id}/route',                       'api.marketing.publishing.campaigns.route',                'PublishingController@routeCampaign',                                    'M', ['check_route_permission']],
            ['POST',   'api/marketing/publishing/campaigns/{id}/publish',                     'api.marketing.publishing.campaigns.publish',              'PublishingController@publishCampaign',                                  'M', ['check_route_permission']],
            ['GET',    'api/marketing/publishing/publications',                               'api.marketing.publishing.publications.index',             'PublishingController@listPublications',                                 'M', ['check_route_permission']],
            ['GET',    'api/marketing/publishing/publications/{id}',                          'api.marketing.publishing.publications.show',              'PublishingController@showPublication',                                  'M', ['check_route_permission']],
            ['POST',   'api/marketing/publishing/publications/{id}/retry',                    'api.marketing.publishing.publications.retry',             'PublishingController@retryPublication',                                 'M', ['check_route_permission']],
            ['POST',   'api/marketing/publishing/publications/{id}/cancel',                   'api.marketing.publishing.publications.cancel',            'PublishingController@cancelPublication',                                'M', ['check_route_permission']],
            ['GET',    'api/marketing/publishing/publications/{id}/metrics',                  'api.marketing.publishing.publications.metrics',           'PublishingController@fetchMetrics',                                     'M', ['check_route_permission']],
            ['GET',    'api/marketing/publishing/dashboard/stats',                            'api.marketing.publishing.dashboard.stats',                'PublishingController@dashboardStats',                                   'M', ['check_route_permission']],
            ['GET',    'api/marketing/publishing/dashboard/recent',                           'api.marketing.publishing.dashboard.recent',               'PublishingController@dashboardRecent',                                  'M', ['check_route_permission']],
        ];
    }

    /** [método, uri, nombre, acción, ¿de gestión de empresas?] — contrato §2.6. */
    private static function nuevas(): array
    {
        return [
            ['GET',  'api/marketing/contexto',                              'api.marketing.contexto.show',                 'ContextoEmpresaController@show',          false],
            ['POST', 'api/marketing/contexto/empresa-activa',               'api.marketing.contexto.cambiar',              'ContextoEmpresaController@cambiar',       false],
            ['GET',  'marketing/empresas',                                  'marketing.empresas.view',                     'EmpresaController@vista',                 true],
            ['GET',  'api/marketing/empresas',                              'api.marketing.empresas.index',                'EmpresaController@index',                 true],
            ['POST', 'api/marketing/empresas',                              'api.marketing.empresas.store',                'EmpresaController@store',                 true],
            ['PUT',  'api/marketing/empresas/{id}',                         'api.marketing.empresas.update',               'EmpresaController@update',                true],
            ['GET',  'api/marketing/empresas/{id}/usuarios',                'api.marketing.empresas.usuarios.index',       'EmpresaUsuarioController@index',          true],
            ['POST', 'api/marketing/empresas/{id}/usuarios',                'api.marketing.empresas.usuarios.store',       'EmpresaUsuarioController@store',          true],
            ['PUT',  'api/marketing/empresas/{id}/usuarios/{userId}',       'api.marketing.empresas.usuarios.update',      'EmpresaUsuarioController@update',         true],
            ['GET',  'api/marketing/empresas/{id}/usuarios-disponibles',    'api.marketing.empresas.usuarios.disponibles', 'EmpresaUsuarioController@disponibles',    true],
        ];
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return array<int, Route> rutas de Marketing en orden de registro */
    private function rutasMarketing(): array
    {
        $out = [];
        foreach (app('router')->getRoutes()->getRoutes() as $r) {
            if (preg_match('#^(api/)?marketing|^webhooks/marketing|^public/marketing#', $r->uri())) {
                $out[] = $r;
            }
        }

        return $out;
    }

    private function metodos(Route $r): string
    {
        return implode('|', array_values(array_diff($r->methods(), ['HEAD'])));
    }

    private function accion(Route $r): string
    {
        return str_replace(self::NS, '', $r->getActionName());
    }

    private function esAutenticada(array $mw): bool
    {
        foreach ($mw as $m) {
            if ($m === 'auth' || str_starts_with($m, 'auth:')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string>|null roles de `marketing.rol:...` o null si no lo lleva */
    private function rolesDe(array $mw): ?array
    {
        foreach ($mw as $m) {
            if (str_starts_with($m, 'marketing.rol:')) {
                $roles = array_map('trim', explode(',', substr($m, strlen('marketing.rol:'))));
                sort($roles);

                return $roles;
            }
        }

        return null;
    }

    private function urlEjemplo(string $uri): string
    {
        $u = preg_replace(['/\{token\}/', '/\{slug\}/', '/\{niche\}/', '/\{[a-zA-Z]+\}/'], ['abc123', 'qa-slug', 'gamer', '987654'], $uri);

        return '/' . $u;
    }

    private function porNombre(): array
    {
        $out = [];
        foreach ($this->rutasMarketing() as $r) {
            $out[$r->getName()] = $r;
        }

        return $out;
    }

    // ── (a) falla abierta ────────────────────────────────────────────────────

    public function test_toda_ruta_autenticada_de_marketing_lleva_marketing_empresa(): void
    {
        $sinEmpresa = [];
        foreach ($this->rutasMarketing() as $r) {
            if (! preg_match('#^(api/)?marketing#', $r->uri())) {
                continue;
            }
            $mw = $r->gatherMiddleware();
            if ($this->esAutenticada($mw) && ! in_array('marketing.empresa', $mw, true)) {
                $sinEmpresa[] = $this->metodos($r) . ' ' . $r->uri();
            }
        }

        $this->assertSame([], $sinEmpresa, "Rutas autenticadas de Marketing SIN marketing.empresa (falla abierta R6):\n" . implode("\n", $sinEmpresa));
    }

    public function test_las_siete_publicas_no_llevan_auth_ni_marketing_empresa(): void
    {
        $rutas = $this->porNombre();
        $publicas = array_filter(self::existentes(), fn ($f) => $f[4] === 'P');
        $this->assertCount(7, $publicas);

        foreach ($publicas as [$met, $uri, $nombre]) {
            $this->assertArrayHasKey($nombre, $rutas, "Falta la ruta pública {$nombre}");
            $mw = $rutas[$nombre]->gatherMiddleware();
            $this->assertFalse($this->esAutenticada($mw), "{$nombre} no debe exigir sesión");
            $this->assertNotContains('marketing.empresa', $mw, "{$nombre} no debe llevar marketing.empresa");
            $this->assertNull($this->rolesDe($mw), "{$nombre} no debe llevar marketing.rol");
            $this->assertNotContains('marketing.principal', $mw);
        }
    }

    // ── (b) snapshot de las 121 existentes ───────────────────────────────────

    public function test_las_121_existentes_conservan_metodo_uri_nombre_y_accion(): void
    {
        $this->assertCount(121, self::existentes());
        $rutas = $this->porNombre();

        $errores = [];
        foreach (self::existentes() as [$met, $uri, $nombre, $accion]) {
            if (! isset($rutas[$nombre])) {
                $errores[] = "falta {$nombre}";
                continue;
            }
            $r = $rutas[$nombre];
            if ($this->metodos($r) !== $met) {
                $errores[] = "{$nombre}: método " . $this->metodos($r) . " ≠ {$met}";
            }
            if ($r->uri() !== $uri) {
                $errores[] = "{$nombre}: uri {$r->uri()} ≠ {$uri}";
            }
            if ($accion !== 'Closure' && $this->accion($r) !== $accion) {
                $errores[] = "{$nombre}: acción " . $this->accion($r) . " ≠ {$accion}";
            }
            if ($accion === 'Closure' && $this->accion($r) !== 'Closure') {
                $errores[] = "{$nombre}: era Closure y ahora es " . $this->accion($r);
            }
        }

        $this->assertSame([], $errores, implode("\n", $errores));
    }

    public function test_las_121_existentes_conservan_su_orden_de_registro(): void
    {
        $esperado = array_column(self::existentes(), 2);
        $set = array_flip($esperado);
        $real = [];
        foreach ($this->rutasMarketing() as $r) {
            if (isset($set[$r->getName()])) {
                $real[] = $r->getName();
            }
        }

        $this->assertSame($esperado, $real, 'Cambió el orden de registro de las rutas existentes (R8).');
    }

    public function test_cada_url_de_ejemplo_sigue_resolviendo_a_la_misma_ruta(): void
    {
        $coleccion = app('router')->getRoutes();
        $errores = [];
        foreach (self::existentes() as [$met, $uri, $nombre]) {
            $metodo = explode('|', $met)[0];
            $url = $this->urlEjemplo($uri);
            try {
                $r = $coleccion->match(Request::create($url, $metodo));
                if ($r->getName() !== $nombre) {
                    $errores[] = "{$metodo} {$url} → {$r->getName()} (esperaba {$nombre})";
                }
            } catch (\Throwable $e) {
                $errores[] = "{$metodo} {$url} → " . class_basename($e);
            }
        }

        $this->assertSame([], $errores, implode("\n", $errores));
    }

    public function test_las_existentes_conservan_su_middleware_previo(): void
    {
        $rutas = $this->porNombre();
        $errores = [];
        foreach (self::existentes() as [$met, $uri, $nombre, $accion, $grupo, $previo]) {
            if (! isset($rutas[$nombre])) {
                continue; // lo reporta la prueba de snapshot
            }
            $mw = $rutas[$nombre]->gatherMiddleware();
            if (! in_array('web', $mw, true)) {
                $errores[] = "{$nombre}: perdió web";
            }
            if ($grupo !== 'P' && ! $this->esAutenticada($mw)) {
                $errores[] = "{$nombre}: perdió auth";
            }
            foreach ($previo as $p) {
                if (! in_array($p, $mw, true)) {
                    $errores[] = "{$nombre}: perdió {$p}";
                }
            }
        }

        $this->assertSame([], $errores, implode("\n", $errores));
    }

    public function test_cada_existente_lleva_el_grupo_de_rol_de_la_matriz(): void
    {
        $rutas = $this->porNombre();
        $errores = [];
        $conteo = ['P' => 0, 'V' => 0, 'MP' => 0, 'M' => 0];

        foreach (self::existentes() as [$met, $uri, $nombre, $accion, $grupo]) {
            $conteo[$grupo]++;
            if ($grupo === 'P' || ! isset($rutas[$nombre])) {
                continue;
            }
            $mw = $rutas[$nombre]->gatherMiddleware();
            $esperados = self::ROLES_GRUPO[$grupo];
            sort($esperados);

            if (! in_array('marketing.empresa', $mw, true)) {
                $errores[] = "{$nombre} ({$grupo}): sin marketing.empresa";
            }
            if ($this->rolesDe($mw) !== $esperados) {
                $errores[] = "{$nombre} ({$grupo}): marketing.rol = " . json_encode($this->rolesDe($mw)) . ' ≠ ' . json_encode($esperados);
            }
            $tienePrincipal = in_array('marketing.principal', $mw, true);
            if ($grupo === 'MP' && ! $tienePrincipal) {
                $errores[] = "{$nombre} (MP): falta marketing.principal";
            }
            if ($grupo !== 'MP' && $tienePrincipal) {
                $errores[] = "{$nombre} ({$grupo}): no debe llevar marketing.principal";
            }

            // Orden: [web, auth[, check_route_permission]] → marketing.empresa → marketing.rol → marketing.principal
            $pos = fn (string $prefijo) => collect($mw)->search(fn ($m) => $m === $prefijo || str_starts_with($m, $prefijo . ':'));
            $iAuth = $pos('auth');
            $iEmp = $pos('marketing.empresa');
            $iRol = $pos('marketing.rol');
            $iCrp = $pos('check_route_permission');
            if ($iAuth !== false && $iEmp !== false && $iAuth > $iEmp) {
                $errores[] = "{$nombre}: marketing.empresa corre antes que auth";
            }
            if ($iCrp !== false && $iEmp !== false && $iCrp > $iEmp) {
                $errores[] = "{$nombre}: marketing.empresa corre antes que check_route_permission";
            }
            if ($iEmp !== false && $iRol !== false && $iEmp > $iRol) {
                $errores[] = "{$nombre}: marketing.rol corre antes que marketing.empresa";
            }
            if ($tienePrincipal && $iRol !== false && $pos('marketing.principal') < $iRol) {
                $errores[] = "{$nombre}: marketing.principal corre antes que marketing.rol";
            }
        }

        $this->assertSame(['P' => 7, 'V' => 18, 'MP' => 8, 'M' => 88], $conteo);
        $this->assertSame([], $errores, implode("\n", $errores));
    }

    // ── (c) 10 nuevas, total 131 ─────────────────────────────────────────────

    public function test_existen_las_diez_rutas_nuevas_con_su_middleware(): void
    {
        $rutas = $this->porNombre();
        $errores = [];

        foreach (self::nuevas() as [$met, $uri, $nombre, $accion, $gestion]) {
            if (! isset($rutas[$nombre])) {
                $errores[] = "falta {$nombre}";
                continue;
            }
            $r = $rutas[$nombre];
            $mw = $r->gatherMiddleware();
            if ($this->metodos($r) !== $met) {
                $errores[] = "{$nombre}: método " . $this->metodos($r) . " ≠ {$met}";
            }
            if ($r->uri() !== $uri) {
                $errores[] = "{$nombre}: uri {$r->uri()} ≠ {$uri}";
            }
            if ($this->accion($r) !== $accion) {
                $errores[] = "{$nombre}: acción " . $this->accion($r) . " ≠ {$accion}";
            }
            foreach (['web', 'marketing.empresa'] as $req) {
                if (! in_array($req, $mw, true)) {
                    $errores[] = "{$nombre}: falta {$req}";
                }
            }
            if (! $this->esAutenticada($mw)) {
                $errores[] = "{$nombre}: falta auth";
            }
            if (in_array('check_route_permission', $mw, true)) {
                $errores[] = "{$nombre}: NO debe usar check_route_permission (§0.3)";
            }
            if ($gestion) {
                if ($this->rolesDe($mw) !== ['admin']) {
                    $errores[] = "{$nombre}: marketing.rol debe ser solo admin, es " . json_encode($this->rolesDe($mw));
                }
                if (! in_array('permission:marketing.empresas.gestionar', $mw, true)) {
                    $errores[] = "{$nombre}: falta permission:marketing.empresas.gestionar";
                }
            } elseif ($this->rolesDe($mw) !== null) {
                $errores[] = "{$nombre}: el contexto lo usan todos los roles; no debe llevar marketing.rol";
            }
        }

        $this->assertSame([], $errores, implode("\n", $errores));
    }

    public function test_total_de_rutas_de_marketing_es_131(): void
    {
        $this->assertCount(131, $this->rutasMarketing(), 'Contrato §2.6: 121 existentes + 10 nuevas.');
    }

    public function test_las_nuevas_no_tapan_a_las_existentes(): void
    {
        // marketing/empresas no debe confundirse con ninguna existente y viceversa.
        $coleccion = app('router')->getRoutes();
        $this->assertSame('marketing.empresas.view', $coleccion->match(Request::create('/marketing/empresas', 'GET'))->getName());
        $this->assertSame('api.marketing.contexto.show', $coleccion->match(Request::create('/api/marketing/contexto', 'GET'))->getName());
        $this->assertSame('api.marketing.empresas.usuarios.disponibles', $coleccion->match(Request::create('/api/marketing/empresas/5/usuarios-disponibles', 'GET'))->getName());
    }
}
