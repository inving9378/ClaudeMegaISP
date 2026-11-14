<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use App\Models\Marketing\Setting;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — condiciones de Henry para F1-1 (contrato §2.6.1, 2026-11-13).
 *
 * Quitar `check_route_permission` de las rutas de Marketing NO puede abrir nada:
 *  1. Toda ruta del módulo conserva `auth` y pasa por `marketing.empresa` — salvo una lista CERRADA, con
 *     nombre, de públicas por diseño. Una ruta nueva fuera de la lista sin esos middleware hace fallar la prueba.
 *  2. Recorriendo TODAS las rutas desde el router (no una lista a mano):
 *     a) con sesión, sin rol de Marketing y sin equipo → 403 en cada una (API `sin_empresa`; Blade «Aún no tienes empresa»);
 *     b) sin sesión → nunca 200 (redirect a login o 401/419);
 *     c) usuario inactivo → `usuario_inactivo` (401 JSON / redirect a login) y la sesión queda cerrada.
 *
 * CAMBIO DE ALCANCE de Henry (2026-11-13, punto B): entra SOLO quien tiene sesión + fila ACTIVA con
 * `rol = 'admin'` en la empresa 1 + usuario activo. En cada ruta del módulo:
 *     super-administrador SIN fila → 403 `sin_empresa`; fila `marketing` / `ventas` (o admin de otra
 *     empresa) → 403 `sin_acceso`; admin con fila → entra (nunca 401/403; las GET sin parámetros → 200).
 */
class AccesoNegadoPorDefectoTest extends MarketingTestCase
{
    use EscenarioFase1;

    /**
     * Lista CERRADA de rutas públicas por diseño (sin `auth` ni `marketing.empresa`). Nombre => motivo.
     * Agregar una ruta aquí es una decisión de Henry, no de quien programa.
     */
    private const PUBLICAS = [
        'marketing.webhook.meta-ads'   => 'Webhook de Meta: GET verifica meta_verify_token; POST exige firma HMAC X-Hub-Signature-256',
        'marketing.webhook.evolution'  => 'Webhook de Evolution: exige evolution_webhook_token',
        'marketing.public.form.show'   => 'Formulario público por slug',
        'marketing.public.form.submit' => 'Envío del formulario público por slug',
        'marketing.public.embed.js'    => 'Script para incrustar el formulario público',
        'marketing.pilot.track.open'   => 'Píxel de apertura del correo del piloto A/B (token del envío, item #47)',
        'marketing.pilot.track.click'  => 'Clic del correo del piloto A/B (token del envío, item #47)',
    ];

    private const MSG_SIN_EMPRESA = 'No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.'; // [Fase 1] texto nuevo (corrección 5 de QA)
    private const JSON_INACTIVO = ['ok' => false, 'error' => 'usuario_inactivo', 'mensaje' => 'Tu usuario está inactivo.'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        Bus::fake();
        Mail::fake();
        $this->montarEscenarioFase1();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    /** @return array<int, Route> */
    private function protegidas(): array
    {
        return array_values(array_filter($this->rutasDelModulo(), fn (Route $r) => ! isset(self::PUBLICAS[$r->getName()])));
    }

    private function esPagina(Route $r, string $metodo): bool
    {
        return $metodo === 'GET' && ! str_starts_with($r->uri(), 'api/');
    }

    private function etiqueta(string $metodo, string $url, TestResponse $res): string
    {
        return "{$metodo} {$url} → {$res->status()} " . Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $res->getContent())), 160);
    }

    // ── 1. estructura: auth + marketing.empresa, lista cerrada ───────────────

    public function test_el_recorrido_del_router_encuentra_las_134_rutas_del_modulo(): void
    {
        $this->assertCount(152, $this->rutasDelModulo(), 'Contrato §2.4: 131 de Fase 0 + 3 nuevas de Fase 1 + 18 nuevas de Fase 2.');
    }

    public function test_toda_ruta_del_modulo_lleva_auth_y_marketing_empresa_salvo_la_lista_cerrada(): void
    {
        $errores = [];
        $publicasVistas = [];

        foreach ($this->rutasDelModulo() as $r) {
            $nombre = $r->getName();
            $id = implode('|', $this->metodosDe($r)) . ' ' . $r->uri();
            $mw = $r->gatherMiddleware();

            if ($nombre === null) {
                $errores[] = "{$id}: ruta SIN nombre (no se puede controlar con la lista cerrada)";
                continue;
            }

            if (isset(self::PUBLICAS[$nombre])) {
                $publicasVistas[] = $nombre;
                continue;
            }

            if (! in_array('web', $mw, true)) {
                $errores[] = "{$id} ({$nombre}): falta web";
            }
            if (! $this->esAutenticada($mw)) {
                $errores[] = "{$id} ({$nombre}): falta auth — ruta pública que no está en la lista cerrada";
            }
            if (! in_array('marketing.empresa', $mw, true)) {
                $errores[] = "{$id} ({$nombre}): falta marketing.empresa — acceso NO negado por defecto";
            }
            $iAuth = array_search('auth', $mw, true);
            $iEmp = array_search('marketing.empresa', $mw, true);
            if ($iAuth !== false && $iEmp !== false && $iEmp < $iAuth) {
                $errores[] = "{$id} ({$nombre}): marketing.empresa corre antes que auth";
            }
        }

        sort($publicasVistas);
        $esperadas = array_keys(self::PUBLICAS);
        sort($esperadas);
        $this->assertSame($esperadas, $publicasVistas, 'La lista cerrada de públicas no coincide con el router (sobra o falta una).');
        $this->assertSame([], $errores, "Rutas del módulo fuera de la regla «acceso negado por defecto»:\n" . implode("\n", $errores));
    }

    public function test_las_publicas_de_la_lista_cerrada_no_piden_sesion_ni_empresa(): void
    {
        $porNombre = [];
        foreach ($this->rutasDelModulo() as $r) {
            $porNombre[$r->getName()] = $r;
        }
        foreach (array_keys(self::PUBLICAS) as $nombre) {
            $this->assertArrayHasKey($nombre, $porNombre, "Falta la pública {$nombre}");
            $mw = $porNombre[$nombre]->gatherMiddleware();
            $this->assertFalse($this->esAutenticada($mw), "{$nombre}: es pública por diseño");
            $this->assertNotContains('marketing.empresa', $mw, "{$nombre}: no debe fijar empresa (camino sin sesión, §0.7)");
        }
    }

    public function test_ninguna_ruta_del_modulo_lleva_ya_check_route_permission(): void
    {
        $con = [];
        foreach ($this->rutasDelModulo() as $r) {
            if (in_array('check_route_permission', $r->gatherMiddleware(), true)) {
                $con[] = $r->getName();
            }
        }
        $this->assertSame([], $con, 'F1-1 = SÍ: check_route_permission se quita de los grupos de Marketing (§2.6).');
    }

    // ── 2b. sin sesión: nunca 200 ────────────────────────────────────────────

    public function test_sin_sesion_ninguna_ruta_del_modulo_responde_200(): void
    {
        $errores = [];
        $revisadas = 0;

        foreach ($this->protegidas() as $r) {
            $url = $this->urlDe($r);
            foreach ($this->metodosDe($r) as $metodo) {
                // (1) como API (fetch/axios)
                $res = $this->anonimo()->json($metodo, $url);
                $revisadas++;
                if (! in_array($res->status(), [401, 419], true) && ! $this->esRedirectALogin($res)) {
                    $errores[] = 'JSON ' . $this->etiqueta($metodo, $url, $res);
                }
                // (2) como navegación (alguien escribiendo la URL / un formulario)
                $res = $this->anonimo()->call($metodo, $url, [], [], [], ['HTTP_ACCEPT' => 'text/html']);
                $revisadas++;
                if (! in_array($res->status(), [401, 419], true) && ! $this->esRedirectALogin($res)) {
                    $errores[] = 'HTML ' . $this->etiqueta($metodo, $url, $res);
                }
            }
        }

        $this->assertGreaterThan(200, $revisadas);
        $this->assertSame([], $errores, "Sin sesión, estas rutas NO mandan a login ni dan 401/419:\n" . implode("\n", $errores));
    }

    /** JSON decodificado o null si la respuesta no es JSON (no truena: así se listan TODAS las rutas que fallan). */
    private function cuerpo(TestResponse $res): ?array
    {
        $d = json_decode((string) $res->getContent(), true);

        return is_array($d) ? $d : null;
    }

    private function esRedirectALogin(TestResponse $res): bool
    {
        return $res->isRedirect() && (bool) preg_match('#/login(\?|$)#', (string) $res->headers->get('Location'));
    }

    // ── 2a. con sesión, sin rol de Marketing y sin equipo: 403 en cada ruta ──

    public function test_con_sesion_sin_rol_ni_empresa_todas_las_rutas_dan_403_sin_empresa(): void
    {
        $this->assertSame([], $this->sinEmpresa->getRoleNames()->all(), 'El usuario de la prueba no debe tener roles de sistema');
        $this->assertSame([], $this->sinEmpresa->getAllPermissions()->pluck('name')->all(), 'Ni permisos');

        $errores = [];
        $paginas = 0;
        foreach ($this->protegidas() as $r) {
            $url = $this->urlDe($r);
            foreach ($this->metodosDe($r) as $metodo) {
                $res = $this->como($this->sinEmpresa)->json($metodo, $url);
                if ($res->status() !== 403 || $this->cuerpo($res) !== ['ok' => false, 'error' => 'sin_empresa', 'mensaje' => self::MSG_SIN_EMPRESA]) {
                    $errores[] = 'JSON ' . $this->etiqueta($metodo, $url, $res);
                }

                if ($this->esPagina($r, $metodo)) {
                    $paginas++;
                    $res = $this->como($this->sinEmpresa)->get($url, ['Accept' => 'text/html']);
                    if ($res->status() !== 403 || ! str_contains((string) $res->getContent(), 'No tienes acceso a Marketing')) {
                        $errores[] = 'PÁGINA ' . $this->etiqueta($metodo, $url, $res);
                    }
                }
            }
        }

        $this->assertGreaterThan(25, $paginas, 'Debe haber recorrido las pantallas Blade del módulo');
        $this->assertSame([], $errores, "Sin rol ni empresa, estas rutas NO dieron 403 sin_empresa:\n" . implode("\n", $errores));
    }

    /** Recorre todas las rutas protegidas como $u (JSON y, en las pantallas, HTML) esperando 403 + $codigo. */
    private function negadoEnCadaRuta(\App\Models\User $u, string $codigo, ?int $sesionEmpresa = null): array
    {
        $errores = [];
        foreach ($this->protegidas() as $r) {
            $url = $this->urlDe($r);
            foreach ($this->metodosDe($r) as $metodo) {
                $res = $this->como($u, $sesionEmpresa)->json($metodo, $url);
                if ($res->status() !== 403 || ($this->cuerpo($res)['error'] ?? null) !== $codigo) {
                    $errores[] = $u->login_user . ' JSON ' . $this->etiqueta($metodo, $url, $res);
                }
                if ($this->esPagina($r, $metodo)) {
                    $res = $this->como($u, $sesionEmpresa)->get($url, ['Accept' => 'text/html']);
                    if ($res->status() !== 403) {
                        $errores[] = $u->login_user . ' PÁGINA ' . $this->etiqueta($metodo, $url, $res);
                    }
                }
            }
        }

        return $errores;
    }

    public function test_super_administrador_y_desarrollador_sin_fila_entran_por_su_rol(): void
    {
        // Acceso por ROL (Irving, 2026-11-14): la regla B («los roles de sistema NO sustituyen a la fila admin») ya no
        // aplica: super-administrator y DESARROLLADOR entran sin fila. El resto de esta clase prueba el camino por equipo.
        config(['marketing.roles_con_acceso' => self::ROLES_CON_ACCESO]);
        $super = $this->crearUsuario('qa1_super_sin_fila', [], ['super-administrator'], self::$permisosDeSeeder);
        $dev = $this->crearUsuario('qa1_dev_sin_fila', [], ['DESARROLLADOR'], []);
        foreach ([$super, $dev] as $u) {
            $res = $this->actingAs($u)->getJson(route('api.marketing.navegacion'));
            $this->assertNotContains($res->status(), [401, 403], "{$u->login_user} sin fila debe entrar por su rol ({$res->status()})");
            $this->assertNotContains($res->json('error'), ['sin_empresa', 'sin_acceso']);
        }
    }

    public function test_permisos_spatie_sueltos_sin_fila_403_en_cada_ruta(): void
    {
        $conPermisos = $this->crearUsuario('qa1_perm_sin_fila', [], [], array_merge(self::$permisosDeLaMigracion, self::$permisosDeSeeder));
        $errores = $this->negadoEnCadaRuta($conPermisos, 'sin_empresa');
        $this->assertSame([], $errores, implode("\n", $errores));
    }

    public function test_fila_marketing_403_en_cada_ruta(): void
    {
        $errores = $this->negadoEnCadaRuta($this->marketingA, 'sin_acceso', $this->empresaA->id);
        $this->assertSame([], $errores, "Fila rol=marketing entró a:\n" . implode("\n", $errores));
    }

    public function test_fila_ventas_403_en_cada_ruta(): void
    {
        $errores = $this->negadoEnCadaRuta($this->ventasA, 'sin_acceso', $this->empresaA->id);
        $this->assertSame([], $errores, "Fila rol=ventas entró a:\n" . implode("\n", $errores));
    }

    public function test_admin_de_otra_empresa_y_super_administrador_403_en_cada_ruta(): void
    {
        // Solo cuenta la empresa 1: admin (y super-administrador) de otra empresa no entra, ni pidiéndola en sesión.
        $errores = array_merge(
            $this->negadoEnCadaRuta($this->adminB, 'sin_acceso', $this->empresaB->id),
            $this->negadoEnCadaRuta($this->adminB, 'sin_acceso', $this->empresaA->id)
        );
        $this->assertSame([], $errores, "Admin de otra empresa entró a:\n" . implode("\n", $errores));
    }

    public function test_marketing_que_pide_la_empresa_1_en_sesion_no_se_vuelve_admin(): void
    {
        $this->como($this->marketingA, 1)->withSession(['marketing.empresa_activa_id' => 1, 'marketing.rol' => 'admin'])
            ->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
        $this->como($this->marketingA)->getJson('/api/marketing/leads?company_id=1&rol=admin')->assertStatus(403);
    }

    public function test_admin_con_fila_en_la_empresa_1_entra_a_cada_ruta(): void
    {
        $errores = [];
        $retiradas = ['marketing.stats', 'marketing.templates.index'];
        foreach ($this->protegidas() as $r) {
            $url = $this->urlDe($r);
            foreach ($this->metodosDe($r) as $metodo) {
                $res = $this->como($this->adminA, $this->empresaA->id)->json($metodo, $url, []);
                $err = $this->cuerpo($res)['error'] ?? null;
                if (in_array($res->status(), [401, 403], true) || in_array($err, ['sin_empresa', 'sin_acceso', 'sin_permiso_rol', 'usuario_inactivo'], true)) {
                    $errores[] = 'JSON ' . $this->etiqueta($metodo, $url, $res);
                }
                $sinParametros = ! str_contains($r->uri(), '{');
                // Respuestas de diseño que no son 200 pero SÍ son «entró»: sin logo subido → 404; OAuth de Meta
                // sin `code` → 302 a Canales (no a login). Un 5xx aquí ya pasó la capa de acceso: lo reporta el
                // barrido de RutasRetiradasTest (no se cuenta dos veces).
                $deDiseno = [
                    'api.marketing.brand-kit.logo.serve' => fn ($x) => $x->status() === 404,
                    'marketing.meta.oauth.start'         => fn ($x) => $x->isRedirect() && ! $this->esRedirectALogin($x),
                    'marketing.meta.oauth.callback'      => fn ($x) => $x->isRedirect() && ! $this->esRedirectALogin($x),
                ];
                if ($metodo === 'GET' && $sinParametros && $res->status() < 500 && ! (isset($deDiseno[$r->getName()]) && $deDiseno[$r->getName()]($res))) {
                    $esperado = in_array($r->getName(), $retiradas, true) ? 410 : 200;
                    if ($res->status() !== $esperado) {
                        $errores[] = "JSON esperaba {$esperado}: " . $this->etiqueta($metodo, $url, $res);
                    }
                }
                if ($this->esPagina($r, $metodo) && $sinParametros && ! in_array($r->getName(), $retiradas, true)) {
                    $res = $this->como($this->adminA, $this->empresaA->id)->get($url, ['Accept' => 'text/html']);
                    if ($res->status() !== 200 && ! (isset($deDiseno[$r->getName()]) && $deDiseno[$r->getName()]($res))) {
                        $errores[] = 'PÁGINA ' . $this->etiqueta($metodo, $url, $res);
                    }
                }
            }
        }
        $this->assertSame([], $errores, "El admin de la empresa 1 NO entró a:\n" . implode("\n", $errores));
    }

    public function test_asignacion_desactivada_o_empresa_inactiva_cuenta_como_sin_empresa(): void
    {
        $exEquipo = $this->crearUsuario('qa1_ex_equipo', [], [], ['marketing.leads.view']);
        $this->asignar($exEquipo, $this->empresaA->id, 'marketing', activo: false);

        $empresaApagada = $this->crearEmpresa('QA1 Apagada', activo: false);
        $deApagada = $this->crearUsuario('qa1_de_apagada', [$empresaApagada->id => 'admin'], [], ['marketing.leads.view']);

        $adminDesactivado = $this->crearUsuario('qa1_admin_desactivado', [], ['super-administrator'], []);
        $this->asignar($adminDesactivado, $this->empresaA->id, 'admin', activo: false);

        foreach ([$exEquipo, $deApagada, $adminDesactivado] as $u) {
            $this->como($u)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
            $this->como($u)->get('/marketing/leads', ['Accept' => 'text/html'])->assertStatus(403)->assertSee('No tienes acceso a Marketing');
        }
    }

    // ── 2c. usuario inactivo ─────────────────────────────────────────────────

    public function test_usuario_inactivo_es_rechazado_en_cada_ruta_y_se_cierra_su_sesion(): void
    {
        $errores = [];
        foreach ($this->protegidas() as $r) {
            $url = $this->urlDe($r);
            foreach ($this->metodosDe($r) as $metodo) {
                $res = $this->como($this->inactivo, $this->empresaA->id)->json($metodo, $url);
                if ($res->status() !== 401 || $this->cuerpo($res) !== self::JSON_INACTIVO) {
                    $errores[] = 'JSON ' . $this->etiqueta($metodo, $url, $res);
                }
                if ($this->isAuthenticated()) {
                    $errores[] = "JSON {$metodo} {$url}: la sesión del inactivo quedó abierta";
                }

                if ($this->esPagina($r, $metodo)) {
                    $res = $this->como($this->inactivo, $this->empresaA->id)->get($url, ['Accept' => 'text/html']);
                    if (! $this->esRedirectALogin($res)) {
                        $errores[] = 'PÁGINA ' . $this->etiqueta($metodo, $url, $res);
                    }
                    if ($this->isAuthenticated()) {
                        $errores[] = "PÁGINA {$url}: la sesión del inactivo quedó abierta";
                    }
                }
            }
        }

        $this->assertSame([], $errores, "El usuario inactivo NO fue rechazado con usuario_inactivo en:\n" . implode("\n", $errores));
    }

    public function test_el_mismo_usuario_activo_si_entra_control_de_la_prueba_de_inactivo(): void
    {
        // Control: el rechazo de arriba se debe a `estado`, no a que le falte empresa o permiso.
        $this->inactivo->forceFill(['estado' => 'activo'])->save();
        $this->como($this->inactivo->fresh(), $this->empresaA->id)->getJson('/api/marketing/leads')->assertOk();
        $this->como($this->inactivo->fresh(), $this->empresaA->id)->getJson('/api/marketing/contexto')->assertOk();
    }

    // ── públicas: las que piden firma no responden 200 sin ella ──────────────

    public function test_webhooks_publicos_sin_firma_no_responden_200(): void
    {
        Setting::query()->updateOrCreate(['company_id' => 1, 'key' => 'evolution_webhook_token'], ['value' => Str::random(64), 'group' => 'channels', 'encrypted' => false]);
        Setting::query()->updateOrCreate(['company_id' => 1, 'key' => 'meta_verify_token'], ['value' => 'qa1-verify', 'group' => 'channels', 'encrypted' => false]);
        Setting::query()->updateOrCreate(['company_id' => 1, 'key' => 'meta_app_secret'], ['value' => 'qa1-secret', 'group' => 'channels', 'encrypted' => false]);

        $casos = [
            ['POST', '/webhooks/marketing/evolution', ['event' => 'messages.upsert'], []],
            ['POST', '/webhooks/marketing/evolution?token=malo', ['event' => 'messages.upsert'], []],
            ['POST', '/webhooks/marketing/meta-ads', ['object' => 'page', 'entry' => []], []],
            ['POST', '/webhooks/marketing/meta-ads', ['object' => 'page', 'entry' => []], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . str_repeat('0', 64)]],
            ['GET', '/webhooks/marketing/meta-ads?hub_mode=subscribe&hub_verify_token=malo&hub_challenge=123', [], []],
            ['GET', '/webhooks/marketing/meta-ads?hub_mode=subscribe&hub_verify_token=&hub_challenge=123', [], []],
        ];
        foreach ($casos as [$m, $url, $datos, $server]) {
            $res = $this->anonimo()->call($m, $url, $datos, [], [], array_merge(['HTTP_ACCEPT' => 'application/json'], $server));
            $this->assertNotSame(200, $res->status(), "{$m} {$url} sin firma respondió 200");
            $this->assertLessThan(500, $res->status(), "{$m} {$url} respondió {$res->status()}");
        }
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_formulario_publico_con_slug_inexistente_no_responde_200(): void
    {
        $slug = 'qa1-no-existe-' . Str::lower(Str::random(8));
        $this->anonimo()->get("/public/marketing/lead-form/{$slug}")->assertNotFound();
        $res = $this->anonimo()->postJson("/public/marketing/lead-form/{$slug}/submit", ['full_name' => 'X']);
        $this->assertContains($res->status(), [404, 422], 'submit a un slug inexistente');
    }
}
