<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — WhatsApp SOLO TEXTO (regla de Henry; contrato §2.1, criterio 8).
 * Generar, editar y regenerar mensajes de WhatsApp sin ningún envío: ninguna petición a Evolution/Graph, ningún
 * SendOutboundMessageJob ni PublishPostJob, sin filas nuevas en marketing_messages ni marketing_publications.
 * Además: ningún archivo NUEVO de la fase referencia gateway, Evolution, envío ni Sofía (revisión estática).
 */
class WhatsAppSoloTextoTest extends MarketingTestCase
{
    use EscenarioFase2;

    private const PROHIBIDOS = '/WhatsAppGateway|WhatsAppLineResolver|EvolutionApiService|MetaCloudApiService|SendOutboundMessageJob|PublishPostJob|sendText\s*\(|whatsapp_instances|sof[ií]a/i';

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    public function test_generar_editar_y_regenerar_whatsapp_no_envia_nada(): void
    {
        $mensajes = $this->contarFilas('marketing_messages');
        $publicaciones = $this->contarFilas('marketing_publications');

        $id = $this->campanaGenerada(['canales' => ['whatsapp']]);
        $usos = [];
        foreach (['A', 'B', 'C'] as $v) {
            $f = DB::table('marketing_generated_content')->find($this->piezaId($id, 'mensaje_whatsapp', $v));
            $this->assertSame('ready', $f->status);
            $usos[$v] = json_decode($f->contenido, true)['uso'];
        }
        $this->assertSame(['A' => 'difusion', 'B' => 'seguimiento', 'C' => 'respuesta'], $usos);

        $pieza = $this->piezaId($id, 'mensaje_whatsapp', 'B');
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}/piezas/{$pieza}", ['contenido' => ['uso' => 'seguimiento', 'texto' => 'Hola, ¿pudiste ver la promo?']])
            ->assertStatus(200);
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/piezas/{$pieza}/regenerar", [])->assertStatus(202);
        $this->correrTrabajos();
        // y producir/activar la campaña tampoco envía
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/estado", ['estado' => 'active'])->assertStatus(200);

        $this->assertSame([], $this->peticionesAjenas, 'Ninguna petición fuera de la IA simulada (Evolution, Graph, etc.)');
        Queue::assertNotPushed('App\\Modules\\Addons\\Marketing\\Jobs\\SendOutboundMessageJob');
        Queue::assertNotPushed('App\\Modules\\Addons\\Marketing\\Jobs\\PublishPostJob');
        foreach (array_keys(Queue::pushedJobs()) as $clase) {
            $this->assertDoesNotMatchRegularExpression('/Send|Publish|WhatsApp|Outbound|Evolution/i', $clase, "Trabajo inesperado: {$clase}");
        }
        $this->assertSame($mensajes, $this->contarFilas('marketing_messages'));
        $this->assertSame($publicaciones, $this->contarFilas('marketing_publications'));
    }

    public function test_ningun_archivo_nuevo_de_la_fase_referencia_envio_ni_sofia(): void
    {
        $raiz = base_path();
        $archivos = array_merge(
            glob($raiz . '/app/Modules/Addons/Marketing/Services/Campanas360/*.php') ?: [],
            glob($raiz . '/app/Modules/Addons/Marketing/Controllers/Campana360*.php') ?: [],
            glob($raiz . '/app/Modules/Addons/Marketing/Jobs/Generar*CampanaJob.php') ?: [],
            glob($raiz . '/app/Modules/Addons/Marketing/Jobs/RegenerarPiezaCampanaJob.php') ?: [],
            glob($raiz . '/app/Modules/Addons/Marketing/Requests/*Campana*.php') ?: [],
            glob($raiz . '/app/Modules/Addons/Marketing/Requests/*Pieza*.php') ?: [],
            glob($raiz . '/resources/js/components/module/marketing/campanas360/*') ?: [],
        );
        $this->assertGreaterThanOrEqual(10, count($archivos), 'No se encontraron los archivos nuevos de la fase');
        $hallazgos = [];
        foreach ($archivos as $f) {
            foreach (file($f) as $n => $linea) {
                if (preg_match(self::PROHIBIDOS, $linea)) {
                    $hallazgos[] = str_replace($raiz . '/', '', $f) . ':' . ($n + 1) . ': ' . trim($linea);
                }
                if (preg_match('/Http::(post|put|patch|withToken|withHeaders)|api\.openai\.com|api\.anthropic\.com|graph\.facebook/i', $linea)) {
                    $hallazgos[] = str_replace($raiz . '/', '', $f) . ':' . ($n + 1) . ' (HTTP/IA directa): ' . trim($linea);
                }
            }
        }
        $this->assertSame([], $hallazgos, implode("\n", $hallazgos));
    }

    public function test_la_ia_solo_se_llama_con_las_dos_claves_nuevas(): void
    {
        $raiz = base_path();
        $hallazgos = [];
        foreach (array_merge(
            glob($raiz . '/app/Modules/Addons/Marketing/Services/Campanas360/*.php') ?: [],
            glob($raiz . '/app/Modules/Addons/Marketing/Jobs/*Campana*Job.php') ?: [],
            glob($raiz . '/app/Modules/Addons/Marketing/Controllers/Campana360*.php') ?: [],
        ) as $f) {
            $src = (string) file_get_contents($f);
            preg_match_all("/IA::(enviar|configurada|json|para|conversar)\(\s*'([^']+)'/", $src, $m, PREG_SET_ORDER);
            foreach ($m as $x) {
                if (! in_array($x[2], ['marketing.campana_360', 'marketing.piezas_campana'], true)) {
                    $hallazgos[] = basename($f) . ": IA::{$x[1]}('{$x[2]}')";
                }
            }
            if (preg_match('/ClaudeApiClient|OpenAI::|CLAUDE_API_KEY|OPENAI_API_KEY/', $src)) {
                $hallazgos[] = basename($f) . ': cliente/llave de IA propio';
            }
        }
        $this->assertSame([], $hallazgos, implode("\n", $hallazgos));
        $modulos = require $raiz . '/app/Modules/Addons/IA/config/modulos.php';
        $this->assertSame('Estrategia de campañas 360', $modulos['marketing.campana_360']['nombre'] ?? null);
        $this->assertSame('Piezas de campañas 360 (guiones, copies, anuncios, WhatsApp)', $modulos['marketing.piezas_campana']['nombre'] ?? null);
        $this->assertSame('Marketing', $modulos['marketing.piezas_campana']['grupo']);
    }
}
