<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — CAMBIO DE ALCANCE de Henry, punto C: `php artisan marketing:equipo-fijar`.
 *
 * Solo con usuarios ficticios creados aquí (dentro de la transacción). Ningún login real en la prueba.
 *  - sin --aplicar no escribe nada;
 *  - con --aplicar deja SOLO esos logins como admin activos de la empresa 1, borra las demás filas de la 1,
 *    NO toca filas de otras empresas, da los permisos `marketing.*` con givePermissionTo (aditivo: no quita
 *    roles ni permisos) y deja constancia en activity_log;
 *  - un login inexistente no cambia nada; correrlo dos veces da lo mismo; el efecto se ve en el acceso (regla B).
 */
class EquipoFijarCommandTest extends MarketingTestCase
{
    use EscenarioFase1;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        $this->montarEscenarioFase1();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function filasEmpresa(int $companyId): array
    {
        return DB::table('marketing_empresa_usuario')->where('company_id', $companyId)->orderBy('user_id')
            ->get(['user_id', 'rol', 'activo'])->map(fn ($f) => [(int) $f->user_id, $f->rol, (int) $f->activo])->all();
    }

    private function foto(): array
    {
        return [
            'filas' => DB::table('marketing_empresa_usuario')->orderBy('id')->get()->map(fn ($f) => (array) $f)->all(),
            'model_has_permissions' => DB::table('model_has_permissions')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
            'activity' => DB::table('activity_log')->count(),
        ];
    }

    private function permisosMarketing(): array
    {
        return Permission::query()->where('guard_name', 'web')->where('name', 'like', 'marketing.%')->orderBy('name')->pluck('name')->all();
    }

    public function test_sin_aplicar_no_escribe_nada(): void
    {
        $nuevo = $this->crearUsuario('qa1_fijar_nuevo', [], [], []);
        $antes = $this->foto();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$nuevo->login_user, $this->marketingA->login_user]])
            ->expectsOutputToContain('no se cambió nada')
            ->assertExitCode(0);

        $this->assertSame($antes, $this->foto(), 'La vista previa escribió algo');
        $this->assertFalse($nuevo->fresh()->hasPermissionTo('marketing.leads.view'));
    }

    public function test_con_aplicar_deja_solo_esos_logins_como_admin_de_la_empresa_1(): void
    {
        $nuevo = $this->crearUsuario('qa1_fijar_nuevo', [], [], []);
        $inactivaEn1 = $this->crearUsuario('qa1_fila_inactiva', [], [], []);
        $this->asignar($inactivaEn1, 1, 'admin', activo: false);
        // marketingA también tiene fila en B: esa NO se debe tocar.
        $this->asignar($this->marketingA, $this->empresaB->id, 'ventas');
        $filasB = $this->filasEmpresa($this->empresaB->id);

        $this->artisan('marketing:equipo-fijar', ['logins' => [$nuevo->login_user, $this->marketingA->login_user], '--aplicar' => true])
            ->assertExitCode(0);

        $esperadas = [[(int) $nuevo->id, 'admin', 1], [(int) $this->marketingA->id, 'admin', 1]];
        usort($esperadas, fn ($a, $b) => $a[0] <=> $b[0]);
        $this->assertSame($esperadas, $this->filasEmpresa(1), 'En la empresa 1 quedan SOLO los dos logins, como admin activos');
        $this->assertSame($filasB, $this->filasEmpresa($this->empresaB->id), 'Las filas de otra empresa no se tocan');
    }

    public function test_con_aplicar_da_los_permisos_marketing_de_forma_aditiva(): void
    {
        // ventasA: sin roles de sistema, con 4 permisos directos. Se le agrega un rol y un permiso ajeno a Marketing.
        $u = $this->ventasA;
        $u->assignRole('Mostrador');
        $ajeno = Permission::query()->where('guard_name', 'web')->where('name', 'not like', 'marketing.%')->value('name');
        $u->givePermissionTo($ajeno);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $rolesAntes = $u->fresh()->getRoleNames()->sort()->values()->all();
        $directosAntes = $u->fresh()->getDirectPermissions()->pluck('name')->all();
        // Otro usuario que NO está en la lista: sus permisos no cambian.
        $otrosAntes = $this->adminB->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$u->login_user], '--aplicar' => true])->assertExitCode(0);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $u = $u->fresh();
        $this->assertSame($rolesAntes, $u->getRoleNames()->sort()->values()->all(), 'No quita ni agrega roles');
        foreach ($directosAntes as $p) {
            $this->assertTrue($u->hasDirectPermission($p), "Le quitó {$p} (debe ser aditivo)");
        }
        foreach ($this->permisosMarketing() as $p) {
            $this->assertTrue($u->hasPermissionTo($p), "No le dio {$p}");
        }
        $this->assertContains('marketing.leads.view', $this->permisosMarketing());
        $this->assertSame($otrosAntes, $this->adminB->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all());
    }

    public function test_deja_constancia_en_activity_log(): void
    {
        $nuevo = $this->crearUsuario('qa1_fijar_log', [], [], []);
        $antes = DB::table('activity_log')->where('log_name', 'marketing')->where('event', 'equipo_fijado')->count();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$nuevo->login_user], '--aplicar' => true])->assertExitCode(0);

        $fila = DB::table('activity_log')->where('log_name', 'marketing')->where('event', 'equipo_fijado')->orderByDesc('id')->first();
        $this->assertSame($antes + 1, DB::table('activity_log')->where('log_name', 'marketing')->where('event', 'equipo_fijado')->count());
        $props = json_decode($fila->properties, true);
        $this->assertSame([$nuevo->login_user], $props['admins']);
        $this->assertSame(1, $props['empresa_id']);
        $this->assertContains($this->adminA->login_user, array_column($props['quitados'], 'login_user'), 'Registra a quién quitó');
    }

    public function test_login_inexistente_no_cambia_nada(): void
    {
        $nuevo = $this->crearUsuario('qa1_fijar_ok', [], [], []);
        $antes = $this->foto();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$nuevo->login_user, 'qa1_no_existe_' . uniqid()], '--aplicar' => true])
            ->assertExitCode(1);

        $this->assertSame($antes, $this->foto());
    }

    public function test_dos_veces_da_lo_mismo(): void
    {
        $nuevo = $this->crearUsuario('qa1_fijar_doble', [], [], []);
        $args = ['logins' => [$nuevo->login_user, $nuevo->login_user, ' ' . $nuevo->login_user . ' '], '--aplicar' => true];
        $this->artisan('marketing:equipo-fijar', $args)->assertExitCode(0);
        $filas = $this->filasEmpresa(1);
        $permisos = DB::table('model_has_permissions')->count();

        $this->artisan('marketing:equipo-fijar', $args)->assertExitCode(0);

        $this->assertSame([[(int) $nuevo->id, 'admin', 1]], $filas, 'Login repetido / con espacios = una sola fila');
        $this->assertSame($filas, $this->filasEmpresa(1));
        $this->assertSame($permisos, DB::table('model_has_permissions')->count());
    }

    public function test_el_efecto_se_ve_en_el_acceso_regla_b(): void
    {
        $nuevo = $this->crearUsuario('qa1_fijar_acceso', [], [], []);
        $this->como($nuevo)->getJson('/api/marketing/leads')->assertStatus(403);
        $this->como($this->adminA, 1)->getJson('/api/marketing/leads')->assertOk();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$nuevo->login_user], '--aplicar' => true])->assertExitCode(0);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->como($nuevo->fresh())->getJson('/api/marketing/leads')->assertOk();
        $this->como($nuevo->fresh())->getJson('/api/marketing/lead-forms')->assertOk();
        // adminA (super-administrador) perdió su fila: ya no entra.
        $this->como($this->adminA->fresh(), 1)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
    }

    public function test_avisa_si_un_login_esta_inactivo(): void
    {
        $this->artisan('marketing:equipo-fijar', ['logins' => [$this->inactivo->login_user]])
            ->expectsOutputToContain('inactivo')
            ->assertExitCode(0);
    }

    public function test_el_comando_no_tiene_logins_ni_ids_escritos(): void
    {
        $src = (string) file_get_contents(base_path('app/Modules/Addons/Marketing/Console/EquipoFijarCommand.php'));
        $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src);
        preg_match_all('/[\'"]([A-Za-z0-9_.@-]{3,})[\'"]/', $codigo, $m);
        $loginsEnCodigo = DB::table('users')->whereIn('login_user', array_unique($m[1]))->pluck('login_user')->all();
        $this->assertSame([], $loginsEnCodigo, 'El comando no debe traer logins escritos');
        $this->assertDoesNotMatchRegularExpression('/user_id[\'"]?\s*(=>|=|,)\s*\d+/', $codigo);
        foreach (['syncRoles', 'syncPermissions', 'revokePermissionTo', 'removeRole'] as $p) {
            $this->assertStringNotContainsString($p, $codigo);
        }
    }

    /**
     * [Corrección 2 de QA, pedido de Henry] Henry e Irving tienen TODAS las facultades de Marketing: además de los
     * `marketing.*`, el comando da todos los permisos de `module.json → permissions[]` que existan (video, campañas IA,
     * variantes, nichos, voces, publicación…). `manage-integrations` NO: es el Hub de Integraciones de todo el sistema.
     */
    public function test_da_tambien_los_permisos_declarados_en_module_json_y_nada_de_fuera(): void
    {
        $modulo = \App\Modules\Addons\Marketing\Console\EquipoFijarCommand::permisosDelModulo();
        $this->assertContains('regenerate-marketing-variants', $modulo);
        $this->assertContains('test-voices', $modulo);
        $this->assertNotContains('manage-integrations', $modulo);
        foreach (array_merge($modulo, ['manage-integrations']) as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $nuevo = $this->crearUsuario('qa1_fijar_modulo', [], [], []);

        $this->artisan('marketing:equipo-fijar', ['logins' => [$nuevo->login_user], '--aplicar' => true])->assertExitCode(0);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $nuevo = $nuevo->fresh();
        foreach ($modulo as $p) {
            $this->assertTrue($nuevo->hasPermissionTo($p), "No le dio {$p}");
        }
        $this->assertFalse($nuevo->hasPermissionTo('manage-integrations'), 'manage-integrations es del Hub global');
        $fuera = $nuevo->getDirectPermissions()->pluck('name')
            ->reject(fn ($n) => str_starts_with($n, 'marketing.') || in_array($n, $modulo, true))->values()->all();
        $this->assertSame([], $fuera, 'Nada fuera de Marketing');
    }
}
