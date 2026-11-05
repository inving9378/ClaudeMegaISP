<?php

namespace Tests\Feature\Core\Security;

use App\Models\User;
use App\Modules\Core\Usuarios\Controllers\PermissionController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Guardar los permisos de un rol (Administración → Roles) ya no quita lo que la ventana no
 * mostró ni guarda sobre un rol que cambió después de abrirla (2026-11-02, ver
 * PermissionController::update).
 *
 * USA DatabaseTransactions (rollback al terminar cada test), NO migrate:fresh.
 * Ejecutar selectivo:  php artisan test tests/Feature/Core/Security/GuardadoPermisosRolTest.php
 */
class GuardadoPermisosRolTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs(User::create([
            'name'       => 'Prueba Guardado Rol',
            'login_user' => 'prueba_guardado_rol_' . uniqid(),
        ])->fresh());
    }

    private function permiso(string $sufijo): Permission
    {
        return Permission::create([
            'name'       => 'prueba.guardado.' . $sufijo . '.' . uniqid(),
            'guard_name' => 'web',
        ]);
    }

    private function rol(Permission ...$permisos): Role
    {
        $rol = Role::create(['name' => 'rol_prueba_guardado_' . uniqid(), 'guard_name' => 'web']);
        $rol->givePermissionTo($permisos);

        return $rol;
    }

    private function guardar(Role $rol, array $payload): array
    {
        $respuesta = app(PermissionController::class)->update(
            Request::create('/administracion/permisos/update-permission-for-role/' . $rol->id, 'POST', $payload),
            $rol->id
        );

        return $respuesta->getData(true);
    }

    private function permisosDe(Role $rol): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $rol->fresh()->permissions()->pluck('name')->sort()->values()->all();
    }

    public function test_lo_que_la_ventana_no_mostro_no_se_quita(): void
    {
        $visible = $this->permiso('visible');
        $deOtros = $this->permiso('otros');
        $rol = $this->rol($visible, $deOtros);

        // La pestaña «Otros» no cargó: la ventana solo mostró $visible y se guardó sin marcar nada.
        $r = $this->guardar($rol, [
            'permissions' => [],
            'shown'       => [$visible->name],
            'loaded'      => [$visible->name, $deOtros->name],
        ]);

        $this->assertSame(200, $r['status']);
        $this->assertSame([$deOtros->name], $this->permisosDe($rol));
    }

    public function test_agrega_lo_marcado_y_quita_lo_desmarcado_que_se_mostro(): void
    {
        $queda = $this->permiso('queda');
        $sale = $this->permiso('sale');
        $entra = $this->permiso('entra');
        $rol = $this->rol($queda, $sale);

        $r = $this->guardar($rol, [
            'permissions' => [$queda->name, $entra->name],
            'shown'       => [$queda->name, $sale->name, $entra->name],
            'loaded'      => [$queda->name, $sale->name],
        ]);

        $this->assertSame(200, $r['status']);
        $esperado = [$queda->name, $entra->name];
        sort($esperado);
        $this->assertSame($esperado, $this->permisosDe($rol));
    }

    public function test_si_el_rol_cambio_despues_de_abrir_la_ventana_no_guarda_nada(): void
    {
        $original = $this->permiso('original');
        $agregadoPorOtro = $this->permiso('otro');
        $rol = $this->rol($original, $agregadoPorOtro);

        // La ventana se abrió cuando el rol solo tenía $original.
        $r = $this->guardar($rol, [
            'permissions' => [],
            'shown'       => [$original->name, $agregadoPorOtro->name],
            'loaded'      => [$original->name],
        ]);

        $this->assertSame(409, $r['status']);
        $esperado = [$original->name, $agregadoPorOtro->name];
        sort($esperado);
        $this->assertSame($esperado, $this->permisosDe($rol));
    }

    public function test_sin_la_lista_de_lo_mostrado_no_guarda(): void
    {
        $permiso = $this->permiso('viejo');
        $rol = $this->rol($permiso);

        try {
            // Así guardaba la ventana anterior: solo las casillas marcadas.
            $this->guardar($rol, ['permissions' => []]);
            $this->fail('Debió rechazar el guardado sin `shown`/`loaded`.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shown', $e->errors());
        }

        $this->assertSame([$permiso->name], $this->permisosDe($rol));
    }

    public function test_cada_guardado_con_cambios_queda_registrado(): void
    {
        $sale = $this->permiso('sale');
        $entra = $this->permiso('entra');
        $rol = $this->rol($sale);

        $this->guardar($rol, [
            'permissions' => [$entra->name],
            'shown'       => [$sale->name, $entra->name],
            'loaded'      => [$sale->name],
        ]);

        $registro = DB::table('activity_log')
            ->where('log_name', 'permisos')
            ->where('subject_type', Role::class)
            ->where('subject_id', $rol->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame(auth()->id(), (int) $registro->causer_id);
        $propiedades = json_decode($registro->properties, true);
        $this->assertSame([$entra->name], $propiedades['agregados']);
        $this->assertSame([$sale->name], $propiedades['quitados']);
    }

    public function test_el_alcance_de_un_permiso_no_mostrado_se_conserva(): void
    {
        if (!Schema::hasTable('permission_scopes') || !Schema::hasTable('role_permission_scopes')) {
            $this->markTestSkipped('Sin tablas de alcance en esta base.');
        }

        $deOtros = $this->permiso('alcance');
        $rol = $this->rol($deOtros);
        DB::table('permission_scopes')->insert([
            'permission_id' => $deOtros->id, 'criterio_propios' => 'prueba', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permission_scopes')->insert([
            'role_id' => $rol->id, 'permission_id' => $deOtros->id, 'scope' => 'propios', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->guardar($rol, [
            'permissions' => [],
            'shown'       => [],
            'loaded'      => [$deOtros->name],
        ]);

        $this->assertSame('propios', DB::table('role_permission_scopes')
            ->where('role_id', $rol->id)->where('permission_id', $deOtros->id)->value('scope'));
    }
}
