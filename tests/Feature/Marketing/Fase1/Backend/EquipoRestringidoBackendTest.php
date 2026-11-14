<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use App\Modules\Addons\Marketing\Support\EquipoMarketing;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * §2.6.2 — restricción automática del equipo al aplicar la versión (migración 2026_11_13_100200), la MARCA
 * `equipo_fijado_at` y el candado del middleware. Logins ficticios creados aquí; ninguno real.
 */
class EquipoRestringidoBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    private const MIGRACION = 'app/Modules/Addons/Marketing/migrations/2026_11_13_100200_restringe_equipo_marketing_a_lista_env.php';

    private const PUBLICAS = [
        'marketing.webhook.meta-ads', 'marketing.webhook.evolution', 'marketing.public.form.show',
        'marketing.public.form.submit', 'marketing.public.embed.js', 'marketing.pilot.track.open', 'marketing.pilot.track.click',
    ];

    protected function tearDown(): void
    {
        unset($_SERVER[EquipoMarketing::VARIABLE_LISTA], $_ENV[EquipoMarketing::VARIABLE_LISTA]);
        parent::tearDown();
    }

    private function migracion(): object
    {
        return require base_path(self::MIGRACION);
    }

    private function conLista(string $lista): void
    {
        $_SERVER[EquipoMarketing::VARIABLE_LISTA] = $lista;
        $_ENV[EquipoMarketing::VARIABLE_LISTA] = $lista;
    }

    private function filaEmpresa1(int $userId): ?object
    {
        return DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $userId)->first();
    }

    // ── Candado ──────────────────────────────────────────────────────────────

    public function test_sin_marca_ninguna_ruta_del_modulo_deja_entrar_ni_al_admin(): void
    {
        $admin = $this->adminMeganet(self::PERMISOS_FASE1);
        $this->actingAs($admin)->getJson('/api/marketing/leads')->assertOk();

        EquipoMarketing::quitarMarca();
        $this->assertFalse(EquipoMarketing::hayMarca());

        $probadas = 0;
        foreach (app('router')->getRoutes()->getRoutes() as $r) {
            if (! preg_match('#^(api/marketing|marketing)(/|$)#', $r->uri()) || in_array($r->getName(), self::PUBLICAS, true)) {
                continue;
            }
            $url = '/' . preg_replace(['/\{niche\}/', '/\{[^}]+\}/'], ['gamer', '1'], $r->uri());
            foreach (array_diff($r->methods(), ['HEAD']) as $metodo) {
                $this->actingAs($admin)->json($metodo, $url)
                    ->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
            }
            $probadas++;
        }
        $this->assertGreaterThanOrEqual(120, $probadas);

        $this->usarLayoutMinimo();
        $this->assertSame(403, $this->actingAs($admin)->get('/marketing/leads')->status());
    }

    public function test_las_publicas_no_dependen_de_la_marca(): void
    {
        EquipoMarketing::quitarMarca();

        $this->get('/public/marketing/embed.js')->assertOk();
    }

    // ── Migración ────────────────────────────────────────────────────────────

    public function test_lista_vacia_deja_a_nadie_activo_y_escribe_la_marca(): void
    {
        EquipoMarketing::quitarMarca();
        $porRol = [];
        foreach (['admin', 'admin', 'marketing', 'ventas'] as $rol) {
            $u = $this->crearUsuario(['super-administrator']);
            $this->asignar($u, 1, $rol);
            $porRol[] = $u;
        }

        $this->conLista('');
        $this->migracion()->up();

        $this->assertSame(0, (int) DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('activo', true)->count());
        foreach ($porRol as $u) {
            $this->assertNotNull($this->filaEmpresa1($u->id), 'no borra: desactiva');
            $this->actingAs($u)->getJson('/api/marketing/navegacion')->assertForbidden();
        }
        $this->assertTrue(EquipoMarketing::hayMarca());
    }

    public function test_variable_ausente_equivale_a_lista_vacia(): void
    {
        unset($_SERVER[EquipoMarketing::VARIABLE_LISTA], $_ENV[EquipoMarketing::VARIABLE_LISTA]);
        if (getenv(EquipoMarketing::VARIABLE_LISTA) !== false) {
            $this->markTestSkipped('El entorno de la terminal define la variable.');
        }

        $this->assertSame([], EquipoMarketing::loginsDeLaLista());
    }

    public function test_lista_con_dos_logins_deja_solo_a_ellos_como_admin_con_permisos(): void
    {
        $a = $this->crearUsuario();
        $b = $this->crearUsuario();
        $porRol = $this->crearUsuario(['super-administrator']);
        $marketing = $this->crearUsuario();
        $this->asignar($b, 1, 'ventas', false);
        $this->asignar($porRol, 1, 'admin');
        $this->asignar($marketing, 1, 'marketing');
        $otra = (int) $this->crearEmpresa()->id;
        $this->asignar($marketing, $otra, 'admin');

        $this->conLista(" {$a->login_user} ,{$b->login_user},, {$a->login_user} ");
        $this->migracion()->up();

        foreach ([$a, $b] as $u) {
            $fila = $this->filaEmpresa1($u->id);
            $this->assertSame(['admin', 1], [$fila->rol, (int) $fila->activo]);
            $u = $u->fresh();
            foreach (EquipoMarketing::permisos() as $p) {
                $this->assertTrue($u->hasPermissionTo($p), "falta {$p->name}");
            }
        }
        foreach ([$porRol, $marketing] as $u) {
            $this->assertSame(0, (int) $this->filaEmpresa1($u->id)->activo);
        }
        $this->assertDatabaseHas('marketing_empresa_usuario', ['company_id' => $otra, 'user_id' => $marketing->id, 'activo' => 1]);

        $this->actingAs($a->fresh())->getJson('/api/marketing/leads')->assertOk();
        // Su única fila quedó inactiva → ya no tiene asignaciones: 403 (sin_empresa).
        $this->actingAs($porRol->fresh())->getJson('/api/marketing/leads')->assertForbidden()->assertJsonPath('error', 'sin_empresa');
    }

    public function test_login_inexistente_se_ignora(): void
    {
        $a = $this->crearUsuario();
        $fantasma = 'no_existe_' . uniqid();

        $resumen = EquipoMarketing::restringirALista([$a->login_user, $fantasma]);

        $this->assertSame([$fantasma], $resumen['ignorados']);
        $this->assertSame(1, $resumen['encontrados']);
        $this->assertSame('admin', $this->filaEmpresa1($a->id)->rol);
    }

    public function test_up_dos_veces_da_lo_mismo(): void
    {
        $a = $this->crearUsuario();
        $otro = $this->crearUsuario();
        $this->asignar($otro, 1, 'admin');
        $this->conLista($a->login_user);

        $this->migracion()->up();
        $foto = DB::table('marketing_empresa_usuario')->where('company_id', 1)->orderBy('user_id')->get(['user_id', 'rol', 'activo'])->toArray();
        $permisos = $a->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all();

        $this->migracion()->up();

        $this->assertEquals($foto, DB::table('marketing_empresa_usuario')->where('company_id', 1)->orderBy('user_id')->get(['user_id', 'rol', 'activo'])->toArray());
        $this->assertSame($permisos, $a->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all());
        $this->assertSame(1, DB::table('marketing_settings')->where('company_id', 1)->where('key', 'equipo_fijado_at')->count());
    }

    public function test_down_quita_la_marca_y_nadie_entra(): void
    {
        $a = $this->crearUsuario();
        $this->conLista($a->login_user);
        $this->migracion()->up();
        $this->actingAs($a->fresh())->getJson('/api/marketing/navegacion')->assertOk();

        $this->migracion()->down();

        $this->assertFalse(EquipoMarketing::hayMarca());
        $this->assertSame(1, (int) $this->filaEmpresa1($a->id)->activo, 'down no reactiva ni desactiva filas');
        $this->actingAs($a->fresh())->getJson('/api/marketing/navegacion')->assertForbidden()->assertJsonPath('error', 'sin_acceso');
    }

    public function test_activity_log_solo_lleva_conteos(): void
    {
        $a = $this->crearUsuario();
        $this->conLista($a->login_user . ',no_existe_' . uniqid());
        $this->migracion()->up();

        $log = Activity::where('log_name', 'marketing')->where('event', 'equipo_restringido')->latest('id')->first();
        $this->assertNotNull($log);
        $props = $log->properties->toArray();
        ksort($props);
        $this->assertSame(['empresa_id', 'en_lista', 'encontrados', 'filas_desactivadas', 'ignorados'], array_keys($props));
        $this->assertSame([1, 2, 1, 1], [$props['empresa_id'], $props['en_lista'], $props['encontrados'], $props['ignorados']]);
        $this->assertStringNotContainsString($a->login_user, json_encode($log->properties));
        $this->assertStringNotContainsString('no_existe_', json_encode($log->properties));
    }

    public function test_partir_lista_recorta_y_quita_vacios_y_repetidos(): void
    {
        $this->assertSame(['uno', 'dos'], EquipoMarketing::partirLista(' uno , dos,,uno , '));
        $this->assertSame([], EquipoMarketing::partirLista(''));
        $this->assertSame([], EquipoMarketing::partirLista(' , ,'));
    }

    // ── Comando ──────────────────────────────────────────────────────────────

    public function test_equipo_fijar_con_aplicar_escribe_la_marca(): void
    {
        EquipoMarketing::quitarMarca();
        $a = $this->crearUsuario();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user]])->assertExitCode(0);
        $this->assertFalse(EquipoMarketing::hayMarca(), 'la vista previa no escribe nada');

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user], '--aplicar' => true])->assertExitCode(0);
        $this->assertTrue(EquipoMarketing::hayMarca());
        $this->actingAs($a->fresh())->getJson('/api/marketing/navegacion')->assertOk();
    }
}
