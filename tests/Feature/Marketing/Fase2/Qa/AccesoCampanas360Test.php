<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — acceso a las 18 rutas nuevas de Campañas 360 (contrato §2.4, criterio 10, F2-12).
 *
 * Barrido DESDE EL ROUTER (no lista a mano): toda ruta cuyo nombre empiece por `api.marketing.campanas-360.` o
 * `marketing.campanas-360.`. Regla B de Fase 1: entra solo quien tiene fila ACTIVA `admin` en la empresa 1.
 *  - sin sesión → nunca 200 (login / 401 / 419);
 *  - sin equipo, super-administrador SIN fila, fila marketing, fila ventas, admin de otra empresa → 403;
 *  - inactivo → 401 `usuario_inactivo` (JSON) / login (HTML);
 *  - admin → nunca 401/403/404/500 con ids válidos (GET → 200);
 *  - campaña de otra empresa, pieza/video de otra campaña, ids inexistentes o no numéricos → 404.
 */
class AccesoCampanas360Test extends MarketingTestCase
{
    use EscenarioFase2;

    private int $campana;
    private int $pieza;
    private int $video;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2();
    }

    protected function tearDown(): void
    {
        $this->limpiarArchivosQa();
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    /** @return array<int, Route> */
    private function rutasNuevas(): array
    {
        $out = [];
        foreach (app('router')->getRoutes()->getRoutes() as $r) {
            $n = (string) $r->getName();
            if (str_starts_with($n, 'api.marketing.campanas-360.') || str_starts_with($n, 'marketing.campanas-360.')) {
                $out[] = $r;
            }
        }

        return $out;
    }

    private function metodos(Route $r): array
    {
        return array_values(array_diff($r->methods(), ['HEAD']));
    }

    private function url(Route $r, ?int $campana = null, ?int $pieza = null, ?int $video = null): string
    {
        return '/' . strtr($r->uri(), [
            '{id}' => (string) ($campana ?? $this->campana),
            '{piezaId}' => (string) ($pieza ?? $this->pieza),
            '{videoId}' => (string) ($video ?? $this->video),
        ]);
    }

    /** Campaña generada + un video listo con archivos reales chicos (para ver/miniatura). */
    private function sembrar(): void
    {
        $this->campana = $this->campanaGenerada();
        $this->pieza = $this->piezaId($this->campana, 'reel_guion');
        $dir = storage_path('app/marketing/videos/1/qa2');
        @mkdir($dir, 0775, true);
        $mp4 = $dir . '/acceso-' . uniqid() . '.mp4';
        $jpg = preg_replace('/\.mp4$/', '_thumb.jpg', $mp4);
        exec('ffmpeg -nostdin -loglevel error -y -f lavfi -i color=c=blue:s=64x64:d=1 -pix_fmt yuv420p ' . escapeshellarg($mp4));
        exec('ffmpeg -nostdin -loglevel error -y -i ' . escapeshellarg($mp4) . ' -frames:v 1 ' . escapeshellarg($jpg));
        $this->archivosQa[] = $mp4;
        $this->archivosQa[] = $jpg;
        $this->video = (int) DB::table('marketing_generated_content')->insertGetId([
            'company_id' => 1, 'type' => 'video', 'pieza' => 'reel', 'pieza_origen_id' => $this->pieza,
            'source_campaign_id' => $this->campana, 'status' => 'ready', 'generation_engine' => 'mvm_ffmpeg',
            'output_path' => 'marketing/videos/1/qa2/' . basename($mp4), 'thumbnail_path' => 'marketing/videos/1/qa2/' . basename($jpg),
            'generation_metadata' => json_encode(['voz_generada' => true, 'duration_sec' => 1]), 'regeneraciones' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Cuerpo válido por ruta (para que el admin no reciba 422 por cuerpo vacío). */
    private function cuerpo(string $nombre): array
    {
        return match (true) {
            str_ends_with($nombre, '.store') => $this->cuerpoCampana(),
            str_ends_with($nombre, 'campanas-360.update') => ['nombre' => 'QA2 renombrada por acceso'],
            str_ends_with($nombre, '.estado.cambiar') => ['estado' => 'active'],
            str_ends_with($nombre, '.generar') => ['alcance' => 'piezas', 'reemplazar' => true],
            str_ends_with($nombre, '.estrategia.update') => ['estrategia' => $this->fixture('estrategia-valida.json')],
            str_ends_with($nombre, '.piezas.update') => ['contenido' => $this->contenidoValido('reel_guion')],
            str_ends_with($nombre, '.piezas.regenerar') => ['indicaciones' => 'Más corto'],
            str_ends_with($nombre, '.piezas.producir') => ['voz' => 'nova', 'musica' => 'sin_musica'],
            default => [],
        };
    }

    private function pedir(User $u, string $metodo, string $url, array $cuerpo = []): TestResponse
    {
        return $this->como($u)->json($metodo, $url, $cuerpo);
    }

    // ── estructura ────────────────────────────────────────────────────────────

    public function test_existen_las_18_rutas_nuevas_con_sus_nombres(): void
    {
        $nombres = array_map(fn (Route $r) => $r->getName(), $this->rutasNuevas());
        sort($nombres);
        $esperados = [
            'api.marketing.campanas-360.destroy', 'api.marketing.campanas-360.estado.cambiar', 'api.marketing.campanas-360.estrategia.update',
            'api.marketing.campanas-360.generar', 'api.marketing.campanas-360.index', 'api.marketing.campanas-360.piezas.producir',
            'api.marketing.campanas-360.piezas.regenerar', 'api.marketing.campanas-360.piezas.update', 'api.marketing.campanas-360.progreso',
            'api.marketing.campanas-360.show', 'api.marketing.campanas-360.store', 'api.marketing.campanas-360.update',
            'api.marketing.campanas-360.videos.miniatura', 'api.marketing.campanas-360.videos.ver',
            'marketing.campanas-360.editar', 'marketing.campanas-360.ficha', 'marketing.campanas-360.lista', 'marketing.campanas-360.nueva',
        ];
        sort($esperados);
        $this->assertSame($esperados, $nombres, 'Contrato §2.4: las 18 rutas con su nombre exacto.');
    }

    public function test_cada_ruta_nueva_lleva_web_auth_y_marketing_empresa_y_throttle_donde_toca(): void
    {
        $errores = [];
        $conThrottle = ['api.marketing.campanas-360.generar', 'api.marketing.campanas-360.piezas.regenerar', 'api.marketing.campanas-360.piezas.producir'];
        foreach ($this->rutasNuevas() as $r) {
            $mw = $r->gatherMiddleware();
            $n = $r->getName();
            foreach (['web', 'auth', 'marketing.empresa'] as $m) {
                if (! in_array($m, $mw, true)) {
                    $errores[] = "{$n}: falta {$m}";
                }
            }
            if (in_array('check_route_permission', $mw, true)) {
                $errores[] = "{$n}: lleva check_route_permission (F2-12: el acceso es la regla de Fase 1)";
            }
            $tiene = (bool) array_filter($mw, fn ($m) => $m === 'throttle:6,1');
            if (in_array($n, $conThrottle, true) && ! $tiene) {
                $errores[] = "{$n}: falta throttle:6,1 (F2-15)";
            }
            if (! in_array($n, $conThrottle, true) && $tiene) {
                $errores[] = "{$n}: throttle:6,1 donde el contrato no lo pide";
            }
            foreach (['id', 'piezaId', 'videoId'] as $p) {
                if (str_contains($r->uri(), '{' . $p . '}') && ! isset($r->wheres[$p])) {
                    $errores[] = "{$n}: {{$p}} sin restricción numérica";
                }
            }
        }
        $this->assertSame([], $errores, implode("\n", $errores));
    }

    // ── matriz de acceso ─────────────────────────────────────────────────────

    public function test_sin_sesion_ninguna_ruta_nueva_responde_200(): void
    {
        $this->sembrar();
        $errores = [];
        foreach ($this->rutasNuevas() as $r) {
            foreach ($this->metodos($r) as $m) {
                foreach ([true, false] as $comoJson) {
                    $this->anonimo();
                    $res = $comoJson ? $this->json($m, $this->url($r)) : $this->call($m, $this->url($r), [], [], [], ['HTTP_ACCEPT' => 'text/html']);
                    $aLogin = $res->isRedirect() && preg_match('#/login(\?|$)#', (string) $res->headers->get('Location'));
                    if (! in_array($res->getStatusCode(), [401, 419], true) && ! $aLogin) {
                        $errores[] = ($comoJson ? 'JSON ' : 'HTML ') . "{$m} {$this->url($r)} → " . $this->etiqueta($res);
                    }
                }
            }
        }
        $this->assertSame([], $errores, "Sin sesión respondió algo distinto de login/401/419:\n" . implode("\n", $errores));
    }

    public function test_quien_no_es_admin_de_meganet_recibe_403_en_cada_ruta(): void
    {
        $this->sembrar();
        $antes = $this->contarFilas('marketing_campaigns') . '/' . $this->contarFilas('marketing_generated_content');
        $trabajosAntes = $this->totalTrabajos();
        $usuarios = [
            'sin equipo' => $this->sinEquipo, 'super-administrador sin fila' => $this->superSinFila,
            'fila marketing' => $this->filaMarketing, 'fila ventas' => $this->filaVentas, 'admin de otra empresa' => $this->adminOtra,
        ];
        $errores = [];
        foreach ($usuarios as $quien => $u) {
            foreach ($this->rutasNuevas() as $r) {
                foreach ($this->metodos($r) as $m) {
                    $res = $this->pedir($u, $m, $this->url($r), $this->cuerpo($r->getName()));
                    if ($res->getStatusCode() !== 403) {
                        $errores[] = "{$quien}: {$m} {$this->url($r)} → " . $this->etiqueta($res);
                    }
                }
            }
        }
        $this->assertSame([], $errores, "Debían dar 403:\n" . implode("\n", $errores));
        $this->assertSame($antes, $this->contarFilas('marketing_campaigns') . '/' . $this->contarFilas('marketing_generated_content'), 'Un 403 escribió algo');
        $this->assertSame($trabajosAntes, $this->totalTrabajos(), 'Un 403 despachó un trabajo');
    }

    private function totalTrabajos(): int
    {
        return array_sum(array_map('count', Queue::pushedJobs()));
    }

    public function test_usuario_inactivo_recibe_401_usuario_inactivo(): void
    {
        $this->sembrar();
        $errores = [];
        foreach ($this->rutasNuevas() as $r) {
            foreach ($this->metodos($r) as $m) {
                $res = $this->pedir($this->inactivo, $m, $this->url($r), $this->cuerpo($r->getName()));
                if ($res->getStatusCode() !== 401 || $res->json('error') !== 'usuario_inactivo') {
                    $errores[] = "{$m} {$this->url($r)} → " . $this->etiqueta($res);
                }
            }
        }
        $this->assertSame([], $errores, implode("\n", $errores));
    }

    public function test_el_admin_entra_a_todas_y_las_get_dan_200(): void
    {
        $this->sembrar();
        $errores = [];
        // destroy al final y sobre su propia campaña en borrador (la generada puede activarse en el barrido)
        $rutas = $this->rutasNuevas();
        // generar (reemplaza las piezas) y destroy van al final para no invalidar los ids del resto del barrido
        $peso = fn ($r) => str_ends_with($r->getName(), '.destroy') ? 2 : (str_ends_with($r->getName(), '.generar') ? 1 : 0);
        usort($rutas, fn ($a, $b) => $peso($a) <=> $peso($b));
        foreach ($rutas as $r) {
            foreach ($this->metodos($r) as $m) {
                $campana = str_ends_with($r->getName(), '.destroy') ? $this->crearCampanaApi() : null;
                $res = $this->pedir($this->admin, $m, $this->url($r, $campana), $this->cuerpo($r->getName()));
                $s = $res->getStatusCode();
                if (in_array($s, [401, 403, 404, 419], true) || $s >= 500 || ($m === 'GET' && $s !== 200)) {
                    $errores[] = "{$m} {$this->url($r, $campana)} → " . $this->etiqueta($res);
                }
            }
        }
        $this->assertSame([], $errores, "El admin de Meganet no pudo usar:\n" . implode("\n", $errores));
    }

    public function test_paginas_blade_montan_su_tag_con_el_id_como_numero(): void
    {
        $this->sembrar();
        $casos = [
            '/marketing/campanas-360' => 'marketing-campanas-360-lista',
            '/marketing/campanas-360/nueva' => 'marketing-campana-360-asistente',
            "/marketing/campanas-360/{$this->campana}" => 'marketing-campana-360-ficha',
            "/marketing/campanas-360/{$this->campana}/editar" => 'marketing-campana-360-asistente',
        ];
        foreach ($casos as $url => $tag) {
            $res = $this->como($this->admin)->get($url);
            $res->assertStatus(200);
            $html = (string) $res->getContent();
            $this->assertStringContainsString("<{$tag}", $html, "{$url} no monta {$tag}");
            if (str_contains($url, (string) $this->campana)) {
                $this->assertMatchesRegularExpression('/:campana-id="' . $this->campana . '"/', $html, "{$url}: el id va como :campana-id numérico");
            }
        }
    }

    /** [Correcciones de QA, H-3] fila marketing o ventas que escribe la URL de una pantalla → «No tienes acceso a Marketing», sin datos. */
    public function test_pantallas_con_fila_marketing_o_ventas_muestran_no_tienes_acceso(): void
    {
        $this->sembrar();
        $nombre = (string) DB::table('marketing_campaigns')->where('id', $this->campana)->value('name');
        $errores = [];
        foreach (['fila marketing' => $this->filaMarketing, 'fila ventas' => $this->filaVentas] as $quien => $u) {
            foreach (['/marketing/campanas-360', '/marketing/campanas-360/nueva', "/marketing/campanas-360/{$this->campana}", "/marketing/campanas-360/{$this->campana}/editar"] as $url) {
                $res = $this->como($u)->get($url, ['Accept' => 'text/html']);
                $html = (string) $res->getContent();
                if ($res->getStatusCode() !== 403 || ! str_contains($html, 'No tienes acceso a Marketing')) {
                    $errores[] = "{$quien} {$url} → " . $this->etiqueta($res);
                }
                if (str_contains($html, $nombre) || preg_match('/<marketing-campana/', $html)) {
                    $errores[] = "{$quien} {$url}: la página trae datos o el componente de la campaña";
                }
            }
        }
        $this->assertSame([], $errores, implode("\n", $errores));
    }

    // ── ids ajenos ───────────────────────────────────────────────────────────

    /**
     * Las páginas Blade `ficha`/`editar` reciben el id solo como prop (§2.4: «Las vistas Blade reciben el id solo como
     * atributo del tag») y el «No encontrado» lo pinta el componente con el 404 de la API (§2.9, navegador punto 5).
     * Se acepta un 200 de esa página SOLO si es el cascarón: monta su tag y no trae ningún dato de la campaña.
     */
    private function esCascaronBlade(Route $r, TestResponse $res, array $prohibidos): bool
    {
        if (str_starts_with($r->uri(), 'api/') || $res->getStatusCode() !== 200) {
            return false;
        }
        $html = (string) $res->getContent();
        foreach ($prohibidos as $p) {
            if ($p !== '' && str_contains($html, $p)) {
                return false;
            }
        }

        return (bool) preg_match('/<marketing-campana-360-(ficha|asistente)\b/', $html);
    }

    public function test_campana_de_otra_empresa_da_404_en_todas_las_rutas_con_id(): void
    {
        $this->sembrar();
        $ajena = $this->campanaDirecta($this->empresaB->id);
        $piezaAjena = DB::table('marketing_generated_content')->insertGetId([
            'company_id' => $this->empresaB->id, 'type' => 'copy', 'pieza' => 'reel_guion', 'variante' => 'A', 'status' => 'ready',
            'source_campaign_id' => $ajena->id, 'generation_engine' => 'ia:qa', 'contenido' => json_encode($this->contenidoValido('reel_guion')),
            'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $errores = [];
        foreach ($this->rutasNuevas() as $r) {
            if (! str_contains($r->uri(), '{id}')) {
                continue;
            }
            foreach ($this->metodos($r) as $m) {
                $url = $this->url($r, $ajena->id, $piezaAjena, $this->video);
                $res = $this->pedir($this->admin, $m, $url, $this->cuerpo($r->getName()));
                if ($this->esCascaronBlade($r, $res, [$ajena->name])) {
                    continue; // página Blade: solo monta el componente; el 404 lo da la API (ver esCascaronBlade)
                }
                if ($res->getStatusCode() !== 404) {
                    $errores[] = "{$m} {$url} → " . $this->etiqueta($res);
                } elseif (str_starts_with($r->uri(), 'api/') && str_contains($r->uri(), '{id}') && ! str_contains($r->uri(), '{piezaId}') && ! str_contains($r->uri(), '{videoId}')
                    && $res->json('error') !== 'no_encontrado') {
                    $errores[] = "{$m} {$url}: 404 sin la forma {ok:false,error:no_encontrado} → " . $this->etiqueta($res);
                }
            }
        }
        $this->assertSame([], $errores, "Campaña de otra empresa:\n" . implode("\n", $errores));
        $this->assertSame('draft', DB::table('marketing_campaigns')->where('id', $ajena->id)->value('status'));
        $this->assertNull(DB::table('marketing_campaigns')->where('id', $ajena->id)->value('deleted_at'));
    }

    public function test_pieza_o_video_de_otra_campana_da_404(): void
    {
        $this->sembrar();
        $otra = $this->campanaGenerada();
        $piezaOtra = $this->piezaId($otra, 'reel_guion');
        $errores = [];
        foreach ($this->rutasNuevas() as $r) {
            if (! str_contains($r->uri(), '{piezaId}') && ! str_contains($r->uri(), '{videoId}')) {
                continue;
            }
            foreach ($this->metodos($r) as $m) {
                // campaña propia + pieza/video de la otra campaña (o al revés)
                $url = $this->url($r, $otra, $this->pieza, $this->video);
                $res = $this->pedir($this->admin, $m, $url, $this->cuerpo($r->getName()));
                if ($res->getStatusCode() !== 404) {
                    $errores[] = "{$m} {$url} → " . $this->etiqueta($res);
                }
            }
        }
        $this->assertSame([], $errores, "Pieza/video de otra campaña:\n" . implode("\n", $errores));
        $this->assertSame(0, (int) DB::table('marketing_generated_content')->where('id', $this->pieza)->value('regeneraciones'));
        $this->assertNotSame(0, $piezaOtra);
    }

    public function test_ids_inexistentes_o_no_numericos_dan_404_nunca_500(): void
    {
        $this->sembrar();
        $raros = ['999999999', 'abc', '1;DROP TABLE marketing_campaigns', '-1', '0x10', '1e3', '%27%20OR%201%3D1'];
        $errores = [];
        foreach ($this->rutasNuevas() as $r) {
            if (! str_contains($r->uri(), '{id}')) {
                continue;
            }
            foreach ($this->metodos($r) as $m) {
                foreach ($raros as $raro) {
                    $url = '/' . strtr($r->uri(), ['{id}' => $raro, '{piezaId}' => $raro, '{videoId}' => $raro]);
                    $res = $this->pedir($this->admin, $m, $url, $this->cuerpo($r->getName()));
                    if ($res->getStatusCode() !== 404 && ! (ctype_digit($raro) && $this->esCascaronBlade($r, $res, []))) {
                        $errores[] = "{$m} {$url} → " . $this->etiqueta($res);
                    }
                }
                $url = $this->url($r, null, 999999999, 999999999);
                if (str_contains($r->uri(), '{piezaId}') || str_contains($r->uri(), '{videoId}')) {
                    $res = $this->pedir($this->admin, $m, $url, $this->cuerpo($r->getName()));
                    if ($res->getStatusCode() !== 404) {
                        $errores[] = "{$m} {$url} → " . $this->etiqueta($res);
                    }
                }
            }
        }
        $this->assertSame([], $errores, implode("\n", $errores));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('marketing_campaigns'));
    }

    public function test_el_indice_solo_lista_campanas_de_meganet(): void
    {
        $propia = $this->crearCampanaApi();
        $ajena = $this->campanaDirecta($this->empresaB->id, ['name' => 'QA2 ajena B']);
        $res = $this->como($this->admin)->getJson('/api/marketing/campanas-360?pagina=1');
        $res->assertStatus(200);
        $ids = array_column($res->json('data'), 'id');
        $this->assertContains($propia, $ids);
        $this->assertNotContains($ajena->id, $ids, 'El índice mostró una campaña de otra empresa');
    }
}
