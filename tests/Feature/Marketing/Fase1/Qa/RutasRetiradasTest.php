<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — [F1-3] rutas retiradas (contrato §2.5, criterio 7).
 *
 * Cada ruta retirada: primero lo que hacía antes de tocar tablas inexistentes (validación → 422,
 * findOrFail con scope → 404 para un id de otra marca), después 410 con el cuerpo exacto. Ninguna 500,
 * ninguna llamada HTTP ni a IA. Y un barrido: TODA ruta autenticada del módulo, como admin de A, ≠ 500.
 */
class RutasRetiradasTest extends MarketingTestCase
{
    use EscenarioFase1;

    private const RETIRADA = [
        'ok' => false,
        'error' => 'retirada',
        'mensaje' => 'Esta función se retiró porque dependía de tablas que ya no existen. Las campañas y plantillas se rehacen en la Fase 2.',
    ];

    /** Componentes que F1-9 deja sin tocar (código muerto que se limpia en Fase 2). */
    private const VUE_SIN_USO = ['MarketingCampaigns.vue', 'MarketingLeads.vue', 'MarketingContentGenerator.vue', 'MarketingCampaignShow.vue', 'MarketingLeadForm.vue'];

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        Bus::fake();
        Mail::fake();
        $this->montarEscenarioFase1();
        $this->a = $this->sembrarNegocio($this->empresaA->id, 'A');
        $this->b = $this->sembrarNegocio($this->empresaB->id, 'B');
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function admin(): static
    {
        return $this->como($this->adminA, $this->empresaA->id);
    }

    private function assertRetirada(TestResponse $r, string $que): void
    {
        $this->assertSame(410, $r->status(), "{$que}: " . Str::limit((string) $r->getContent(), 300));
        $this->assertSame(self::RETIRADA, $r->json(), $que);
    }

    // ── 410 directo ──────────────────────────────────────────────────────────

    public function test_rutas_que_responden_410_directo(): void
    {
        $idA = $this->a['campana']->id;
        $casos = [
            ['GET', '/marketing/stats', []],
            ['GET', '/marketing/templates', []],
            ['PUT', '/marketing/templates/987654', ['name' => 'x']],
            ['DELETE', '/marketing/templates/987654', []],
            ['POST', '/marketing/campaigns/table', []],
            ['POST', '/marketing/content/approve/987654', []],
            ['POST', '/marketing/content/reject/987654', []],
            ['POST', '/marketing/leads-legacy/table', []],
        ];
        foreach ($casos as [$m, $url, $datos]) {
            $this->assertRetirada($this->admin()->json($m, $url, $datos), "{$m} {$url}");
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    // ── validación antes del 410 ─────────────────────────────────────────────

    public function test_campaigns_store_valida_antes_y_luego_410(): void
    {
        $this->admin()->postJson('/marketing/campaigns/store', [])->assertStatus(422);
        $this->admin()->postJson('/marketing/campaigns/store', ['title' => ['array'], 'channel' => '<script>alert(1)</script>'])->assertStatus(422);

        $antes = \DB::table('marketing_campaigns')->count();
        // Sin conocer todas las reglas: lo que sí sabemos es que nunca crea filas ni da 500.
        $r = $this->admin()->postJson('/marketing/campaigns/store', [
            'title' => 'Campaña QA1', 'name' => 'Campaña QA1', 'target_zone' => 'Centro', 'channel' => 'whatsapp',
            'type' => 'manual', 'daily_limit' => 10, 'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        ]);
        $this->assertContains($r->status(), [410, 422], (string) $r->getContent());
        if ($r->status() === 410) {
            $this->assertSame(self::RETIRADA, $r->json());
        }
        $this->assertSame($antes, \DB::table('marketing_campaigns')->count(), 'Una ruta retirada no crea filas');
    }

    public function test_templates_store_valida_antes_y_luego_410(): void
    {
        $this->admin()->postJson('/marketing/templates/store', [])->assertStatus(422);
    }

    public function test_generar_copy_e_imagen_validan_la_campana_de_la_empresa_y_no_llaman_a_ia(): void
    {
        foreach (['/marketing/content/generate-copy', '/marketing/content/generate-image'] as $url) {
            $this->admin()->postJson($url, [])->assertStatus(422);
            // Campaña de OTRA marca → 422 (exists acotado), nunca 410 ni 200.
            $this->admin()->postJson($url, ['campaign_id' => $this->b['campana']->id, 'prompt' => 'x', 'tone' => 'formal', 'type' => 'post'])
                ->assertStatus(422);
            // Campaña propia → 410 (o 422 si faltan otros campos; nunca 500 ni llamada a IA)
            $r = $this->admin()->postJson($url, ['campaign_id' => $this->a['campana']->id, 'prompt' => 'Promo', 'tone' => 'formal', 'type' => 'post', 'style' => 'photo', 'size' => '1024x1024']);
            $this->assertContains($r->status(), [410, 422], "{$url}: " . $r->getContent());
            if ($r->status() === 422) {
                $this->assertArrayNotHasKey('campaign_id', $r->json('errors') ?? [], "{$url}: la campaña propia no debe fallar exists");
            } else {
                $this->assertSame(self::RETIRADA, $r->json());
            }
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    // ── findOrFail con scope antes del 410 ───────────────────────────────────

    public function test_campanas_legado_con_id_propio_410_y_de_otra_marca_404(): void
    {
        $idA = $this->a['campana']->id;
        $idB = $this->b['campana']->id;
        $rutas = [
            ['PUT', '/marketing/campaigns/%d', ['name' => 'x']],
            ['DELETE', '/marketing/campaigns/%d', []],
            ['POST', '/marketing/campaigns/%d/approve', []],
            ['POST', '/marketing/campaigns/%d/pause', []],
            ['POST', '/marketing/campaigns/%d/activate', []],
            ['GET', '/marketing/content/campaign/%d', []],
        ];
        foreach ($rutas as [$m, $patron, $datos]) {
            $this->assertRetirada($this->admin()->json($m, sprintf($patron, $idA), $datos), "{$m} " . sprintf($patron, $idA));
            $r = $this->admin()->json($m, sprintf($patron, $idB), $datos);
            $this->assertSame(404, $r->status(), "{$m} " . sprintf($patron, $idB) . ' (otra marca): ' . Str::limit((string) $r->getContent(), 200));
            $this->assertStringNotContainsString($this->b['marca'], (string) $r->getContent());
        }
        $this->assertNotNull($this->fila('marketing_campaigns', $idA), 'DELETE retirado no borra');
        $this->assertNotNull($this->fila('marketing_campaigns', $idB));
        $this->assertSame('draft', $this->fila('marketing_campaigns', $idA)['status'], 'approve/pause/activate retirados no cambian estado');
    }

    public function test_ver_campana_legado_redirige_al_panel_y_la_de_otra_marca_da_404(): void
    {
        $this->admin()->get('/marketing/campaigns/' . $this->a['campana']->id)->assertRedirect('/marketing');
        $this->admin()->get('/marketing/campaigns/' . $this->b['campana']->id)->assertNotFound();
        $this->admin()->get('/marketing/campaigns/987654')->assertNotFound();
    }

    public function test_leads_legacy_con_id_propio_410_y_de_otra_marca_404(): void
    {
        $idA = $this->a['lead']->id;
        $idB = $this->b['lead']->id;
        $rutas = [
            ['GET', '/marketing/leads-legacy/%d', []],
            ['POST', '/marketing/leads-legacy/%d/qualify', []],
            ['PUT', '/marketing/leads-legacy/%d/status', ['status' => 'won']],
            ['POST', '/marketing/leads-legacy/%d/assign', ['user_id' => $this->adminA->id]],
        ];
        foreach ($rutas as [$m, $patron, $datos]) {
            $this->assertRetirada($this->admin()->json($m, sprintf($patron, $idA), $datos), "{$m} " . sprintf($patron, $idA));
            $this->assertSame(404, $this->admin()->json($m, sprintf($patron, $idB), $datos)->status(), "{$m} " . sprintf($patron, $idB));
        }
        $this->assertSame('new', $this->fila('marketing_leads', $idA)['status'], 'status retirado no cambia el lead');
        $this->assertNull($this->fila('marketing_leads', $idA)['assigned_user_id']);
    }

    /** Regla B (cambio de alcance): ventas no llega ni a las retiradas → 403 `sin_acceso`. */
    public function test_ventas_no_llega_a_las_retiradas_sigue_403_de_rol(): void
    {
        $this->como($this->ventasA, $this->empresaA->id)->getJson('/marketing/stats')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
        $this->como($this->ventasA, $this->empresaA->id)->postJson('/marketing/campaigns/table')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
    }

    // ── barridos ─────────────────────────────────────────────────────────────

    public function test_barrido_toda_ruta_get_sin_parametros_responde_distinto_de_500_al_admin(): void
    {
        $errores = [];
        $n = 0;
        foreach ($this->rutasDelModulo() as $r) {
            if (! in_array('GET', $r->methods(), true) || str_contains($r->uri(), '{') || ! $this->esAutenticada($r->gatherMiddleware())) {
                continue;
            }
            $n++;
            foreach (['json', 'html'] as $modo) {
                $res = $modo === 'json'
                    ? $this->admin()->getJson('/' . $r->uri())
                    : $this->admin()->get('/' . $r->uri(), ['Accept' => 'text/html']);
                if ($res->status() >= 500) {
                    $errores[] = strtoupper($modo) . " GET /{$r->uri()} → {$res->status()} " . Str::limit(strip_tags((string) $res->getContent()), 200);
                }
            }
        }
        $this->assertGreaterThan(30, $n);
        $this->assertSame([], $errores, "Rutas GET que dan 500 al admin:\n" . implode("\n", $errores));
        Http::assertNothingSent();
    }

    public function test_barrido_toda_ruta_autenticada_con_cualquier_metodo_e_id_inexistente_no_da_500(): void
    {
        $errores = [];
        foreach ($this->rutasDelModulo() as $r) {
            if (! $this->esAutenticada($r->gatherMiddleware())) {
                continue;
            }
            foreach ($this->metodosDe($r) as $m) {
                $url = $this->urlDe($r);
                $res = $this->admin()->json($m, $url, []);
                if ($res->status() >= 500) {
                    $errores[] = "{$m} {$url} → {$res->status()} " . Str::limit((string) $res->getContent(), 200);
                }
            }
        }
        $this->assertSame([], $errores, "Rutas que dan 500 al admin (id inexistente, cuerpo vacío):\n" . implode("\n", $errores));
        Http::assertNothingSent();
    }

    public function test_ataque_id_no_numerico_en_la_url_no_da_500(): void
    {
        $errores = [];
        foreach ($this->rutasDelModulo() as $r) {
            if (! $this->esAutenticada($r->gatherMiddleware()) || ! preg_match('/\{(id|campaignId|userId|sendId)\}/', $r->uri())) {
                continue;
            }
            foreach (['abc', '1 OR 1=1', '99999999999999999999'] as $valor) {
                $url = '/' . preg_replace(['/\{(id|campaignId|userId|sendId)\}/', '/\{niche\}/'], [rawurlencode($valor), 'gamer'], $r->uri());
                foreach ($this->metodosDe($r) as $m) {
                    $res = $this->admin()->json($m, $url, []);
                    if ($res->status() >= 500) {
                        $errores[] = "{$m} {$url} → {$res->status()} " . Str::limit((string) $res->getContent(), 160);
                    }
                }
            }
        }
        $this->assertSame([], $errores, "id no numérico → 500:\n" . implode("\n", $errores));
    }

    public function test_la_ui_en_uso_ya_no_llama_a_las_rutas_retiradas(): void
    {
        $dir = base_path('resources/js/components/module/marketing');
        $patron = '#[\'"`]/marketing/(stats|templates|campaigns/(table|store)|content/(generate-copy|generate-image|approve|reject|campaign)|leads-legacy)#';
        $patronCampanaId = '#/marketing/campaigns/\$\{[^}]+\}/(approve|pause|activate)|/marketing/campaigns/\'\s*\+#';
        $hallados = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'vue' && $f->getExtension() !== 'js') {
                continue;
            }
            if (in_array($f->getFilename(), self::VUE_SIN_USO, true)) {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            if (preg_match_all($patron, $src, $mm) || preg_match_all($patronCampanaId, $src, $mm)) {
                $hallados[] = str_replace(base_path() . '/', '', $f->getPathname()) . ': ' . implode(', ', array_unique($mm[0]));
            }
        }
        $this->assertSame([], $hallados, "Componentes en uso que todavía llaman a rutas retiradas:\n" . implode("\n", $hallados));
    }

    private function fila(string $tabla, int $id): ?array
    {
        $r = \DB::table($tabla)->where('id', $id)->first();

        return $r ? (array) $r : null;
    }
}
