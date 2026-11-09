<?php

namespace Tests\Feature\Core\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Items #1193 y #1194: el check «Añadir rol de vendedor» del formulario de Administradores suma
 * Vendedor al rol base y al quitarlo quita solo Vendedor; el vendedor pasa a Inactivo sin borrar
 * nada y al reactivarlo recupera su estado (un bloqueo se conserva), su tipo y su regla.
 *
 * Datos sintéticos en megaisp_test; DatabaseTransactions: nada queda escrito. La sincronización
 * con Talento se apaga para no disparar sus efectos (documentos, extensión) en la prueba.
 */
class CheckVendedorAditivoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private User $actor;

    private array $estado = [];

    private int $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['talento.autosync_usuarios' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Activo', 'Inactivo', 'Bloqueado'] as $nombre) {
            $this->estado[$nombre] = DB::table('seller_status')->where('name', $nombre)->orderBy('id')->value('id')
                ?? DB::table('seller_status')->insertGetId(['name' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->tipo = DB::table('seller_types')->insertGetId(['name' => 'Tipo de prueba', 'created_at' => now(), 'updated_at' => now()]);

        $this->actor = $this->persona('actor', ['super-administrator']);
    }

    private function rol(string $nombre): Role
    {
        return Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
    }

    private function persona(string $sufijo, array $roles): User
    {
        $user = User::create([
            'name' => 'Prueba Vendedor ' . $sufijo,
            'login_user' => 'prueba_vendedor_' . $sufijo . '_' . uniqid(),
            'estado' => 'activo',
        ])->fresh();
        foreach ($roles as $rol) {
            $user->assignRole($this->rol($rol));
        }

        return $user;
    }

    private function vendedor(User $user, string $estado = 'Activo'): int
    {
        return DB::table('sellers')->insertGetId([
            'user_id' => $user->id, 'status_id' => $this->estado[$estado], 'type_id' => $this->tipo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Lo que manda el formulario de edición: todos los roles salvo Vendedor + el check. */
    private function guardar(User $user, array $rolesBase, bool $vendedor, array $extra = [])
    {
        $payload = array_merge([
            'name' => $user->name,
            'father_last_name' => 'Apellido',
            'mother_last_name' => 'Materno',
            'email' => $user->login_user . '@example.com',
            'phone' => '5555555555',
            'address' => 'Calle de prueba 1',
            'city_municipality' => 'Municipio',
            'state_country' => 'Estado',
            'code_postal' => '00000',
            'rfc' => 'XAXX010101000',
            'login_user' => $user->login_user,
            'is_seller' => $vendedor ? 1 : 0,
            'type_id' => $this->tipo,
            'rule_id' => 'null',
            'roles_enviados' => 1,
            'roles_seleccionados' => array_map(fn ($rol) => $this->rol($rol)->id, $rolesBase),
        ], $extra);
        // Una llave con null cuenta como enviada: para simular que no viene, se quita.
        $payload = array_filter($payload, fn ($valor) => $valor !== null);

        return $this->actingAs($this->actor)->postJson(route('user.update', $user->id), $payload)
            ->assertOk()->assertJson(['status' => 200]);
    }

    private function roles(User $user): array
    {
        return $user->fresh()->roles()->pluck('name')->sort()->values()->all();
    }

    private function seller(User $user): object
    {
        return DB::table('sellers')->where('user_id', $user->id)->first();
    }

    public function test_tecnico_vendedor_editado_en_un_campo_conserva_sus_roles(): void
    {
        $user = $this->persona('tecvend', ['TECNICO', 'Vendedor']);
        $this->vendedor($user);

        $this->guardar($user, ['TECNICO'], true, ['phone' => '5511112222']);

        $this->assertSame(['TECNICO', 'Vendedor'], $this->roles($user));
        $this->assertSame('5511112222', $user->fresh()->phone);
        $this->assertSame((int) $this->estado['Activo'], (int) $this->seller($user)->status_id);
        $this->assertSame(0, DB::table('user_role_change_audits')->where('target_user_id', $user->id)->count(), 'Sin cambio de roles no hay auditoría');
    }

    public function test_un_solo_rol_vendedor_ya_no_reemplaza_el_rol_base(): void
    {
        // Lo que mandaba el formulario anterior a un Técnico + Vendedor: role = Vendedor.
        $user = $this->persona('legado', ['TECNICO', 'Vendedor']);
        $this->vendedor($user);

        $this->guardar($user, [], true, ['roles_enviados' => null, 'roles_seleccionados' => null, 'role' => $this->rol('Vendedor')->id]);

        $this->assertSame(['TECNICO', 'Vendedor'], $this->roles($user));
    }

    public function test_activar_y_desactivar_toca_solo_vendedor_y_conserva_permisos_del_rol_base_y_directos(): void
    {
        $base = 'rol_base_prueba_' . uniqid();
        $this->rol($base)->givePermissionTo(Permission::findOrCreate('user_view_user', 'web'));
        $directo = Permission::findOrCreate('permiso_directo_prueba_' . uniqid(), 'web');
        $user = $this->persona('permisos', [$base]);
        $user->givePermissionTo($directo);
        $ruta = '/administracion/user/get-all-users';

        $this->guardar($user, [$base], true);
        $this->assertSame(collect([$base, 'Vendedor'])->sort()->values()->all(), $this->roles($user));
        $this->assertSame((int) $this->estado['Activo'], (int) $this->seller($user)->status_id);
        $this->assertTrue($user->fresh()->hasDirectPermission($directo->name));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user->fresh())->getJson($ruta)->assertOk();

        $this->guardar($user, [$base], false);
        $this->assertSame([$base], $this->roles($user));
        $this->assertTrue($user->fresh()->hasDirectPermission($directo->name), 'Los permisos directos no se tocan');
        $this->assertTrue($user->fresh()->hasPermissionTo('user_view_user'), 'El rol base conserva sus permisos');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user->fresh())->getJson($ruta)->assertOk();
    }

    public function test_desactivar_deja_al_vendedor_inactivo_y_conserva_prospectos_y_reglas(): void
    {
        $user = $this->persona('historial', ['TECNICO', 'Vendedor']);
        $sellerId = $this->vendedor($user);
        foreach ([1, 2] as $i) {
            DB::table('prospects')->insert([
                'name' => "Prospecto de prueba {$i}", 'phone' => '5500000000', 'email' => "prospecto{$i}@example.com",
                'address' => 'Calle 1', 'colony' => 'Colonia', 'municipality' => 'Municipio', 'state' => 'Estado',
                'cp' => '00000', 'status_prospect' => 'nuevo', 'id_seller' => $sellerId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $regla = DB::table('commissions_rules')->insertGetId(['name' => 'Regla de prueba', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('commissions_rules_sellers')->insert(['commission_rule_id' => $regla, 'seller_id' => $sellerId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('history_sellers_rules')->insert(['seller_id' => $sellerId, 'rule_id' => $regla, 'data' => json_encode(['id' => $regla]), 'created_at' => now(), 'updated_at' => now()]);

        $this->guardar($user, ['TECNICO'], false);

        $this->assertSame(['TECNICO'], $this->roles($user));
        $seller = $this->seller($user);
        $this->assertNotNull($seller, 'La fila de vendedor no se borra');
        $this->assertSame((int) $this->estado['Inactivo'], (int) $seller->status_id);
        $this->assertSame((int) $this->estado['Activo'], (int) $seller->status_id_antes_de_inactivar);
        $this->assertSame(2, DB::table('prospects')->where('id_seller', $sellerId)->count());
        $this->assertSame(1, DB::table('commissions_rules_sellers')->where('seller_id', $sellerId)->count());
        $this->assertSame(1, DB::table('history_sellers_rules')->where('seller_id', $sellerId)->count());

        // Reactivar: mismo vendedor, con su tipo, su regla y su historial.
        $this->guardar($user, ['TECNICO'], true, ['rule_id' => $regla]);

        $this->assertSame(['TECNICO', 'Vendedor'], $this->roles($user));
        $seller = $this->seller($user);
        $this->assertSame($sellerId, (int) $seller->id);
        $this->assertSame((int) $this->estado['Activo'], (int) $seller->status_id);
        $this->assertNull($seller->status_id_antes_de_inactivar);
        $this->assertSame($this->tipo, (int) $seller->type_id);
        $this->assertSame(2, DB::table('prospects')->where('id_seller', $sellerId)->count());
        $this->assertSame(1, DB::table('history_sellers_rules')->where('seller_id', $sellerId)->count(), 'La misma regla no se repite');
        $this->assertSame(1, DB::table('commissions_rules_sellers')->where('seller_id', $sellerId)->count());
    }

    public function test_un_bloqueo_se_conserva_al_guardar_inactivar_y_reactivar(): void
    {
        $user = $this->persona('bloqueo', ['Mostrador', 'Vendedor']);
        $this->vendedor($user, 'Bloqueado');

        $this->guardar($user, ['Mostrador'], true, ['phone' => '5533334444']);
        $this->assertSame((int) $this->estado['Bloqueado'], (int) $this->seller($user)->status_id, 'Guardar no desbloquea');

        $this->guardar($user, ['Mostrador'], false);
        $this->assertSame((int) $this->estado['Inactivo'], (int) $this->seller($user)->status_id);
        $this->assertSame((int) $this->estado['Bloqueado'], (int) $this->seller($user)->status_id_antes_de_inactivar);

        $this->guardar($user, ['Mostrador'], true);
        $this->assertSame((int) $this->estado['Bloqueado'], (int) $this->seller($user)->status_id, 'Al reactivar sigue bloqueado');
        $this->assertSame(['Mostrador', 'Vendedor'], $this->roles($user));
    }

    public function test_administracion_y_desarrollador_no_cambian_con_el_check(): void
    {
        $user = $this->persona('admin', ['super-administrator', 'DESARROLLADOR', 'Vendedor']);
        $this->vendedor($user);

        // Aunque el formulario no los mandara, no se quitan.
        $this->guardar($user, [], false);
        $this->assertSame(['DESARROLLADOR', 'super-administrator'], $this->roles($user));

        $this->guardar($user, ['super-administrator', 'DESARROLLADOR'], true);
        $this->assertSame(['DESARROLLADOR', 'Vendedor', 'super-administrator'], $this->roles($user));
    }

    public function test_comando_de_sospechosos_detecta_y_no_escribe_nada(): void
    {
        $sospechoso = $this->persona('perdio', ['Vendedor']);
        DB::table('talento_role_departments')->updateOrInsert(['role_name' => 'TECNICO'], ['department' => 'Planta externa']);
        DB::table('talento_colaboradores')->insert(['user_id' => $sospechoso->id, 'department' => 'Planta externa', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_role_change_audits')->insert([
            'actor_user_id' => $this->actor->id, 'target_user_id' => $sospechoso->id, 'target_login' => $sospechoso->login_user,
            'roles_antes' => json_encode(['TECNICO', 'Vendedor']), 'roles_despues' => json_encode(['Vendedor']), 'created_at' => '2026-01-15 10:00:00',
        ]);
        $soloVendedor = $this->persona('puro', ['Vendedor']); // sin puesto ni bitácora: no es sospechoso

        $huella = fn () => [
            DB::table('model_has_roles')->count(), DB::table('sellers')->count(),
            DB::table('user_role_change_audits')->count(), DB::table('users')->max('updated_at'),
        ];
        $antes = $huella();

        $this->assertSame(0, Artisan::call('usuarios:sospechosos-rol-vendedor', ['--json' => true, '--sin-logs' => true]));
        $filas = collect(json_decode(Artisan::output(), true))->keyBy('id');

        $this->assertSame($antes, $huella(), 'El comando es de solo lectura');
        $fila = $filas->get($sospechoso->id);
        $this->assertNotNull($fila);
        $this->assertSame('Vendedor', $fila['roles_actuales']);
        $this->assertSame('Planta externa (active)', $fila['puesto_talento']);
        $this->assertSame('TECNICO', $fila['rol_base_propuesto']);
        $this->assertSame('2026-01-15 10:00:00', $fila['fecha']);
        $this->assertFalse($filas->has($soloVendedor->id));
    }
}
