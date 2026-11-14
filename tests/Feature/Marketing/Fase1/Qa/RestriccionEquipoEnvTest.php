<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use App\Modules\Addons\Marketing\Console\EquipoFijarCommand;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — §2.6.2 «Restricción automática al aplicar la versión» (condición de Henry, 2026-11-13).
 *
 *  - Candado: sin la marca `marketing_settings(company_id 1, key equipo_fijado_at)` → 403 `sin_acceso` en TODAS las
 *    rutas del módulo, aun con fila admin activa en la empresa 1.
 *  - Migración 2026_11_13_100200: lista `MARKETING_EQUIPO_LOGINS` del entorno. Vacía/ausente → nadie activo + marca.
 *    Con 2 logins → solo ellos admin activos (las demás filas de la 1 quedan inactivas, no se borran) y con los permisos
 *    de Marketing (misma lista que `marketing:equipo-fijar`, sin `manage-integrations`). Login inexistente → se ignora.
 *    Los «admins por rol» que deja la migración 100300 quedan inactivos. up() dos veces = igual. down() → nadie entra.
 *    activity_log `equipo_restringido` solo con conteos: ningún login.
 *
 * Usuarios ficticios (prefijo qa1_eq_); la variable de entorno se pone con putenv/$_ENV/$_SERVER y se restaura.
 * Todo dentro de la transacción de MarketingTestCase.
 */
class RestriccionEquipoEnvTest extends MarketingTestCase
{
    use EscenarioFase1;

    private const MIGRACION = 'app/Modules/Addons/Marketing/migrations/2026_11_13_100200_restringe_equipo_marketing_a_lista_env.php';
    private const MIGRACION_POR_ROL = 'app/Modules/Addons/Marketing/migrations/2026_11_12_100300_asigna_equipo_actual_a_empresa_meganet.php';
    private const VAR = 'MARKETING_EQUIPO_LOGINS';

    /** @var array{0: string|false, 1: bool, 2: mixed, 3: bool, 4: mixed} */
    private array $envOriginal;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        Bus::fake();
        Mail::fake();
        $this->envOriginal = [
            getenv(self::VAR),
            array_key_exists(self::VAR, $_ENV), $_ENV[self::VAR] ?? null,
            array_key_exists(self::VAR, $_SERVER), $_SERVER[self::VAR] ?? null,
        ];
        $this->montarEscenarioFase1();
    }

    protected function tearDown(): void
    {
        [$getenv, $enEnv, $valEnv, $enServer, $valServer] = $this->envOriginal;
        $getenv === false ? putenv(self::VAR) : putenv(self::VAR . '=' . $getenv);
        if ($enEnv) {
            $_ENV[self::VAR] = $valEnv;
        } else {
            unset($_ENV[self::VAR]);
        }
        if ($enServer) {
            $_SERVER[self::VAR] = $valServer;
        } else {
            unset($_SERVER[self::VAR]);
        }
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    // ── ayudantes ────────────────────────────────────────────────────────────

    private function lista(?string $valor): void
    {
        if ($valor === null) {
            putenv(self::VAR);
            unset($_ENV[self::VAR], $_SERVER[self::VAR]);

            return;
        }
        putenv(self::VAR . '=' . $valor);
        $_ENV[self::VAR] = $valor;
        $_SERVER[self::VAR] = $valor;
    }

    private function migracion(string $ruta = self::MIGRACION): object
    {
        $this->assertFileExists(base_path($ruta), 'Falta la migración de §2.6.2');

        return require base_path($ruta);
    }

    private function correr(string $metodo = 'up', string $ruta = self::MIGRACION): string
    {
        $m = $this->migracion($ruta);
        ob_start();
        try {
            $m->{$metodo}();
        } finally {
            $salida = (string) ob_get_clean();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $salida;
    }

    private function hayMarca(): bool
    {
        // marketing_settings tiene soft delete: una marca borrada lógicamente NO cuenta.
        return DB::table('marketing_settings')->where('company_id', 1)->where('key', 'equipo_fijado_at')->whereNull('deleted_at')->exists();
    }

    private function quitarMarca(): void
    {
        DB::table('marketing_settings')->where('company_id', 1)->where('key', 'equipo_fijado_at')->delete();
    }

    private function ponerMarca(): void
    {
        DB::table('marketing_settings')->updateOrInsert(
            ['company_id' => 1, 'key' => 'equipo_fijado_at'],
            ['value' => now()->toIso8601String(), 'group' => 'acceso', 'encrypted' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** @return array<int, array{0:int,1:string,2:int}> filas de la empresa 1 (user_id, rol, activo) */
    private function filas1(): array
    {
        return DB::table('marketing_empresa_usuario')->where('company_id', 1)->orderBy('user_id')
            ->get(['user_id', 'rol', 'activo'])->map(fn ($f) => [(int) $f->user_id, (string) $f->rol, (int) $f->activo])->all();
    }

    /** @return array<int, int> user_id de las filas ACTIVAS de la empresa 1 */
    private function activos1(): array
    {
        return DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('activo', 1)->orderBy('user_id')
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Lista esperada de permisos de Marketing (misma regla que marketing:equipo-fijar), solo los que existen. */
    private function permisosDeMarketing(): array
    {
        return Permission::query()->where('guard_name', 'web')
            ->where(fn ($q) => $q->where('name', 'like', 'marketing.%')->orWhereIn('name', EquipoFijarCommand::permisosDelModulo()))
            ->where('name', '!=', 'manage-integrations')
            ->orderBy('name')->pluck('name')->all();
    }

    private function ficticio(string $etiqueta): \App\Models\User
    {
        return $this->crearUsuario('qa1_eq_' . $etiqueta, [], [], []);
    }

    /** @return array<int, Route> */
    private function protegidas(): array
    {
        $publicas = ['marketing.webhook.meta-ads', 'marketing.webhook.evolution', 'marketing.public.form.show',
            'marketing.public.form.submit', 'marketing.public.embed.js', 'marketing.pilot.track.open', 'marketing.pilot.track.click'];

        return array_values(array_filter($this->rutasDelModulo(), fn (Route $r) => ! in_array($r->getName(), $publicas, true)));
    }

    // ── candado sin marca ────────────────────────────────────────────────────

    public function test_el_escenario_base_trae_la_marca(): void
    {
        $this->assertTrue($this->hayMarca(), '§2.6.2: MarketingTestCase / el escenario base crea la marca para que las pruebas existentes sigan igual');
        $this->como($this->adminA, 1)->getJson('/api/marketing/leads')->assertOk();
    }

    public function test_sin_marca_403_sin_acceso_en_todas_las_rutas_aun_con_fila_admin(): void
    {
        $this->quitarMarca();
        $this->assertFalse($this->hayMarca());

        $errores = [];
        $n = 0;
        foreach ($this->protegidas() as $r) {
            $url = $this->urlDe($r);
            foreach ($this->metodosDe($r) as $metodo) {
                $n++;
                $res = $this->como($this->adminA, 1)->json($metodo, $url, []);
                $cuerpo = json_decode((string) $res->getContent(), true);
                if ($res->status() !== 403 || ($cuerpo['error'] ?? null) !== 'sin_acceso') {
                    $errores[] = "JSON {$metodo} {$url} → {$res->status()} " . Str::limit((string) $res->getContent(), 140);
                }
                if ($metodo === 'GET' && ! str_starts_with($r->uri(), 'api/')) {
                    $res = $this->como($this->adminA, 1)->get($url, ['Accept' => 'text/html']);
                    if ($res->status() !== 403) {
                        $errores[] = "PÁGINA {$url} → {$res->status()}";
                    }
                }
            }
        }
        $this->assertGreaterThanOrEqual(127, $n, '134 rutas − 7 públicas, cada método');
        $this->assertSame([], $errores, "Sin la marca equipo_fijado_at alguien con fila admin entró a:\n" . implode("\n", $errores));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_sin_marca_tampoco_entran_super_administrador_ni_desarrollador_con_fila(): void
    {
        $this->quitarMarca();
        $dev = $this->crearUsuario('qa1_eq_dev', [1 => 'admin'], ['DESARROLLADOR'], []);
        foreach ([$this->adminA, $dev] as $u) {
            $this->como($u, 1)->getJson('/api/marketing/contexto')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
        }
    }

    public function test_marca_de_otra_empresa_no_cuenta(): void
    {
        $this->quitarMarca();
        DB::table('marketing_settings')->insert(['company_id' => $this->empresaB->id, 'key' => 'equipo_fijado_at', 'value' => now()->toIso8601String(),
            'group' => 'acceso', 'encrypted' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->como($this->adminA, 1)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
    }

    public function test_con_marca_rige_la_regla_b_de_siempre(): void
    {
        $this->ponerMarca();
        $this->como($this->adminA, 1)->getJson('/api/marketing/leads')->assertOk();
        $this->como($this->marketingA, 1)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
        $this->como($this->sinEmpresa)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
        $this->como($this->inactivo, 1)->getJson('/api/marketing/leads')->assertStatus(401);
    }

    // ── migración: lista vacía o ausente ─────────────────────────────────────

    public function test_lista_vacia_nadie_activo_y_marca_escrita(): void
    {
        foreach (['', '   ', ' , ,'] as $vacia) {
            $this->quitarMarca();
            DB::table('marketing_empresa_usuario')->where('company_id', 1)->update(['activo' => 1]);
            $filasAntes = count($this->filas1());
            $this->lista($vacia);

            $this->correr();

            $this->assertSame([], $this->activos1(), "lista «{$vacia}» → nadie activo en la empresa 1");
            $this->assertSame($filasAntes, count($this->filas1()), 'no borra filas (reversible)');
            $this->assertTrue($this->hayMarca(), 'escribe la marca aunque la lista esté vacía');
        }
        $this->como($this->adminA, 1)->getJson('/api/marketing/leads')->assertStatus(403);
    }

    public function test_lista_ausente_equivale_a_vacia(): void
    {
        $this->quitarMarca();
        $this->lista(null);
        $this->correr();
        $this->assertSame([], $this->activos1());
        $this->assertTrue($this->hayMarca());
    }

    public function test_la_marca_tiene_la_forma_del_contrato(): void
    {
        $this->quitarMarca();
        $this->lista('');
        $this->correr();
        $m = DB::table('marketing_settings')->where('company_id', 1)->where('key', 'equipo_fijado_at')->first();
        $this->assertNotNull($m);
        $this->assertSame('acceso', $m->group);
        $this->assertNotEmpty($m->value);
        $this->assertNotFalse(strtotime((string) $m->value), 'value = fecha');
    }

    // ── migración: 2 logins ──────────────────────────────────────────────────

    public function test_dos_logins_solo_ellos_admin_activos_con_permisos_de_marketing(): void
    {
        $uno = $this->ficticio('uno');
        $dos = $this->ficticio('dos');
        $this->asignar($dos, 1, 'ventas', activo: false); // fila previa: debe quedar admin activa
        $otrosAntes = array_values(array_diff(array_column($this->filas1(), 0), [(int) $uno->id, (int) $dos->id]));
        $integracionesAntes = [$uno->hasPermissionTo('manage-integrations'), $dos->hasPermissionTo('manage-integrations')];

        $this->quitarMarca();
        $this->lista(" {$uno->login_user} ,{$dos->login_user} "); // espacios: se recortan
        $this->correr();

        $esperado = [(int) $uno->id, (int) $dos->id];
        sort($esperado);
        $this->assertSame($esperado, $this->activos1(), 'Solo los 2 de la lista quedan activos en la empresa 1');
        foreach ([$uno, $dos] as $u) {
            $fila = DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $u->id)->first();
            $this->assertSame('admin', $fila->rol);
            $this->assertSame(1, (int) $fila->activo);
        }
        // Las demás filas de la 1 siguen ahí, inactivas (no se borran).
        foreach ($otrosAntes as $id) {
            $this->assertSame(0, (int) DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $id)->value('activo'));
        }

        $permisos = $this->permisosDeMarketing();
        $this->assertContains('marketing.leads.view', $permisos);
        foreach ([$uno, $dos] as $i => $u) {
            $u = $u->fresh();
            foreach ($permisos as $p) {
                $this->assertTrue($u->hasPermissionTo($p), "{$u->login_user} sin {$p}");
            }
            $this->assertSame($integracionesAntes[$i], $u->hasPermissionTo('manage-integrations'), 'manage-integrations NO se reparte');
        }

        $this->como($uno->fresh(), 1)->getJson('/api/marketing/leads')->assertOk();
        $this->como($dos->fresh(), 1)->getJson('/api/marketing/lead-forms')->assertOk();
        $this->como($this->adminA, 1)->getJson('/api/marketing/leads')->assertStatus(403);
    }

    public function test_permisos_son_aditivos_no_quitan_nada(): void
    {
        $u = $this->ficticio('aditivo');
        $u->assignRole('Mostrador');
        $ajeno = Permission::query()->where('guard_name', 'web')->where('name', 'not like', 'marketing.%')
            ->whereNotIn('name', EquipoFijarCommand::permisosDelModulo())->value('name');
        $u->givePermissionTo($ajeno);
        $rolesAntes = DB::table('model_has_roles')->count();

        $this->lista($u->login_user);
        $this->correr();

        $u = $u->fresh();
        $this->assertTrue($u->hasRole('Mostrador'));
        $this->assertTrue($u->hasDirectPermission($ajeno));
        $this->assertSame($rolesAntes, DB::table('model_has_roles')->count(), 'no toca roles de nadie');
    }

    public function test_login_inexistente_se_ignora(): void
    {
        $uno = $this->ficticio('real');
        $fantasma = 'qa1_eq_no_existe_' . Str::lower(Str::random(10));
        $filasAntes = count($this->filas1());
        $usuariosAntes = DB::table('users')->count();

        $this->lista("{$fantasma},{$uno->login_user}");
        $this->correr(); // no debe lanzar excepción

        $this->assertSame([(int) $uno->id], $this->activos1());
        $this->assertSame($usuariosAntes, DB::table('users')->count(), 'no crea usuarios');
        $this->assertSame($filasAntes + 1, count($this->filas1()), 'solo la fila del login real');
        $this->assertTrue($this->hayMarca());
    }

    public function test_solo_logins_inexistentes_deja_a_nadie(): void
    {
        $this->lista('qa1_eq_nadie_' . Str::random(8) . ',qa1_eq_nadie_' . Str::random(8));
        $this->correr();
        $this->assertSame([], $this->activos1());
        $this->assertTrue($this->hayMarca());
    }

    public function test_no_toca_filas_de_otras_empresas(): void
    {
        $uno = $this->ficticio('otras');
        $this->asignar($this->marketingA, $this->empresaB->id, 'admin');
        $antesB = DB::table('marketing_empresa_usuario')->where('company_id', '!=', 1)->orderBy('id')->get(['id', 'rol', 'activo'])->toArray();

        $this->lista($uno->login_user);
        $this->correr();

        $this->assertEquals($antesB, DB::table('marketing_empresa_usuario')->where('company_id', '!=', 1)->orderBy('id')->get(['id', 'rol', 'activo'])->toArray());
    }

    // ── los «admins por rol» de la migración 100300 ──────────────────────────

    public function test_los_admins_por_rol_de_la_migracion_100300_quedan_inactivos(): void
    {
        // Personas que la 100300 asigna por rol (super-administrator/DESARROLLADOR → admin; Administrador → marketing).
        $porRolSuper = $this->crearUsuario('qa1_eq_porrol_super', [], ['super-administrator'], []);
        $porRolDev = $this->crearUsuario('qa1_eq_porrol_dev', [], ['DESARROLLADOR'], []);
        $porRolAdm = $this->crearUsuario('qa1_eq_porrol_adm', [], ['Administrador'], []);
        $this->correr('up', self::MIGRACION_POR_ROL);
        foreach ([$porRolSuper, $porRolDev, $porRolAdm] as $u) {
            $this->assertSame(1, (int) DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $u->id)->value('activo'),
                "precondición: la 100300 dejó activo a {$u->login_user}");
        }
        $this->como($porRolSuper, 1)->getJson('/api/marketing/leads')->assertOk(); // con marca del escenario: hoy entra

        $equipo = $this->ficticio('equipo');
        $this->quitarMarca();
        $this->lista($equipo->login_user);
        $this->correr();

        foreach ([$porRolSuper, $porRolDev, $porRolAdm] as $u) {
            $this->assertSame(0, (int) DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $u->id)->value('activo'),
                "{$u->login_user} (asignado por rol) debe quedar inactivo");
            $this->como($u->fresh(), 1)->getJson('/api/marketing/leads')->assertStatus(403);
        }
        $this->assertSame([(int) $equipo->id], $this->activos1());
    }

    public function test_orden_real_de_deploy_100300_y_luego_100200_nadie_fuera_de_la_lista_entra(): void
    {
        // Base «recién desplegada»: sin marca y sin filas en la 1. Entre la 100300 y la 100200 nadie entra (candado);
        // después de la 100200 solo la lista.
        $this->quitarMarca();
        DB::table('marketing_empresa_usuario')->where('company_id', 1)->delete();
        $porRol = $this->crearUsuario('qa1_eq_deploy_super', [], ['super-administrator'], []);
        $equipo = $this->ficticio('deploy_equipo');

        $this->correr('up', self::MIGRACION_POR_ROL);
        $this->como($porRol, 1)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');

        $this->lista($equipo->login_user);
        $this->correr();
        $this->como($porRol->fresh(), 1)->getJson('/api/marketing/leads')->assertStatus(403);
        $this->como($equipo->fresh(), 1)->getJson('/api/marketing/leads')->assertOk();
    }

    // ── idempotencia, down y bitácora ────────────────────────────────────────

    public function test_up_dos_veces_da_lo_mismo(): void
    {
        $uno = $this->ficticio('idem1');
        $dos = $this->ficticio('idem2');
        $this->lista("{$uno->login_user},{$dos->login_user}");

        $this->correr();
        $filas = $this->filas1();
        $permisos = DB::table('model_has_permissions')->count();
        $marcas = DB::table('marketing_settings')->where('key', 'equipo_fijado_at')->count();

        $this->correr();

        $this->assertSame($filas, $this->filas1());
        $this->assertSame($permisos, DB::table('model_has_permissions')->count(), 'no duplica permisos');
        $this->assertSame($marcas, DB::table('marketing_settings')->where('key', 'equipo_fijado_at')->count(), 'una sola marca');
        $this->assertSame(1, DB::table('marketing_settings')->where('company_id', 1)->where('key', 'equipo_fijado_at')->whereNull('deleted_at')->count());
    }

    public function test_down_borra_la_marca_y_nadie_entra_sin_reactivar_filas(): void
    {
        $uno = $this->ficticio('down');
        $this->lista($uno->login_user);
        $this->correr();
        $this->como($uno->fresh(), 1)->getJson('/api/marketing/leads')->assertOk();
        $filas = $this->filas1();

        $this->correr('down');

        $this->assertFalse($this->hayMarca(), 'down() borra la marca');
        $this->assertSame($filas, $this->filas1(), 'down() no reactiva ni borra filas');
        $this->como($uno->fresh(), 1)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
        $this->como($uno->fresh(), 1)->get('/marketing', ['Accept' => 'text/html'])->assertStatus(403);
    }

    public function test_activity_log_registra_equipo_restringido_sin_ningun_login(): void
    {
        $uno = $this->ficticio('log1');
        $dos = $this->ficticio('log2');
        $fantasma = 'qa1_eq_fantasma_' . Str::lower(Str::random(8));
        $desde = (int) DB::table('activity_log')->max('id');

        $this->lista("{$uno->login_user},{$dos->login_user},{$fantasma}");
        $this->correr();

        $nuevas = DB::table('activity_log')->where('id', '>', $desde)->get();
        $restringido = $nuevas->first(fn ($f) => $f->log_name === 'marketing' && $f->event === 'equipo_restringido');
        $this->assertNotNull($restringido, 'falta activity_log marketing / equipo_restringido');
        foreach ($nuevas as $f) {
            $texto = json_encode((array) $f, JSON_UNESCAPED_UNICODE);
            foreach ([$uno->login_user, $dos->login_user, $fantasma] as $login) {
                $this->assertStringNotContainsString($login, $texto, "activity_log #{$f->id} trae un login");
            }
            $this->assertStringNotContainsString('"user_id"', (string) $f->properties, 'tampoco ids de usuario');
        }
        $props = json_decode((string) $restringido->properties, true) ?: [];
        foreach ($props as $clave => $valor) {
            $this->assertTrue(is_int($valor) || is_numeric($valor) || is_string($valor) && strtotime($valor) !== false || is_bool($valor),
                "propiedad «{$clave}» no es un conteo: " . json_encode($valor));
        }
    }

    public function test_el_codigo_de_la_migracion_cumple_las_reglas(): void
    {
        $src = (string) file_get_contents(base_path(self::MIGRACION));
        $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src);
        // La lista se lee del entorno en la migración o en la clase de apoyo que usa (Env::get = env()), nunca de config/.
        $apoyo = base_path('app/Modules/Addons/Marketing/Support/EquipoMarketing.php');
        $todo = $codigo . (is_file($apoyo) ? (string) file_get_contents($apoyo) : '');
        $this->assertMatchesRegularExpression('/MARKETING_EQUIPO_LOGINS/', $todo, 'lee MARKETING_EQUIPO_LOGINS');
        $this->assertMatchesRegularExpression('/\\benv\\(|Env::get\\(/', $todo, 'lo lee del entorno');
        $this->assertSame([], array_values(array_filter(glob(base_path('config/*.php')) ?: [],
            fn ($f) => str_contains((string) file_get_contents($f), 'MARKETING_EQUIPO_LOGINS'))), 'ningún archivo de config/ con la lista (§2.6.2)');
        foreach (['syncRoles', 'syncPermissions', 'revokePermissionTo', 'removeRole', '->delete()'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $codigo, "la migración no debe usar {$prohibido}");
        }
        $this->assertDoesNotMatchRegularExpression('/user_id[\'"]?\s*(=>|=|,)\s*\d+/', $codigo, 'nunca ids');
        preg_match_all('/[\'"]([A-Za-z0-9_.@-]{3,})[\'"]/', $codigo, $m);
        $this->assertSame([], DB::table('users')->whereIn('login_user', array_unique($m[1]))->pluck('login_user')->all(), 'sin logins escritos');
    }

    // ── equipo-fijar también escribe la marca ────────────────────────────────

    public function test_equipo_fijar_aplicar_escribe_la_marca(): void
    {
        $u = $this->ficticio('fijar');
        $this->quitarMarca();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$u->login_user]])->assertExitCode(0);
        $this->assertFalse($this->hayMarca(), 'la vista previa no escribe la marca');

        $this->artisan('marketing:equipo-fijar', ['logins' => [$u->login_user], '--aplicar' => true])->assertExitCode(0);
        $this->assertTrue($this->hayMarca(), '--aplicar escribe/actualiza la marca');
        $this->como($u->fresh(), 1)->getJson('/api/marketing/leads')->assertOk();
    }
}
