<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\TalentoColaboradorSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * 28-nov-2026 (David): al crear o editar un usuario o admin en Administración ya sale como colaborador (entra a la app
 * y se le pueden asignar órdenes), con el interruptor de la conexión automática apagado; y una migración se la crea a
 * quienes ya existían sin ficha. Clientes y «Solo lectura» no; una ficha dada de baja no se rehace. Datos sintéticos
 * en una transacción.
 */
class FichaColaboradorAlGuardarUsuarioTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName(), 'Solo sobre una BD de pruebas');
        config(['talento.autosync_usuarios' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('talento_role_departments')->updateOrInsert(['role_name' => 'TECNICO_PLANTA'], ['department' => 'Planta externa']);
        $this->actor = $this->persona(['super-administrator']);
    }

    private function rol(string $nombre): Role
    {
        return Role::findOrCreate($nombre, 'web');
    }

    private function persona(array $roles, string $estado = 'activo'): User
    {
        $user = User::create([
            'name' => 'Sintético', 'father_last_name' => 'Ficha', 'login_user' => 'ficha_' . Str::random(8),
            'email' => Str::random(10) . '@example.test', 'estado' => $estado,
        ])->fresh();
        foreach ($roles as $rol) {
            $user->assignRole($this->rol($rol));
        }

        return $user;
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'name' => 'Nuevo', 'father_last_name' => 'Sintético', 'mother_last_name' => 'Prueba',
            'email' => Str::random(10) . '@example.test', 'phone' => '5555555555', 'address' => 'Calle 1',
            'city_municipality' => 'Municipio', 'state_country' => 'Estado', 'code_postal' => '00000',
            'rfc' => 'XAXX010101000', 'is_seller' => 0,
        ], $extra);
    }

    public function test_al_crear_un_usuario_de_planta_externa_ya_sale_como_colaborador(): void
    {
        $login = 'ficha_alta_' . Str::random(6);
        $this->actingAs($this->actor)->postJson(route('user.store'), $this->datos([
            'login_user' => $login, 'password' => 'Prueba-alta-1', 'role' => $this->rol('TECNICO_PLANTA')->id,
        ]))->assertOk()->assertJson(['status' => 200]);

        $ficha = TalentoColaborador::where('user_id', User::where('login_user', $login)->value('id'))->first();
        $this->assertNotNull($ficha, 'Sale como colaborador sin esperar a que entre a la app');
        $this->assertSame(['active', 'Planta externa'], [$ficha->status, $ficha->department]);
    }

    public function test_al_crear_un_admin_tambien(): void
    {
        $login = 'ficha_admin_' . Str::random(6);
        $this->actingAs($this->actor)->postJson(route('user.store'), $this->datos([
            'login_user' => $login, 'password' => 'Prueba-alta-1', 'role' => $this->rol('ADMINISTRADOR_COMPLETO')->id,
        ]))->assertOk()->assertJson(['status' => 200]);

        $this->assertSame('active', TalentoColaborador::where('user_id', User::where('login_user', $login)->value('id'))->value('status'));
    }

    public function test_al_editar_le_pone_el_departamento_de_su_nuevo_rol_si_no_tenia(): void
    {
        $u = $this->persona(['Vendedor']);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $u->id, 'type' => 'interno', 'status' => 'active', 'department' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->actor)->postJson(route('user.update', $u->id), $this->datos([
            'name' => $u->name, 'login_user' => $u->login_user, 'email' => $u->email, 'rule_id' => 'null',
            'roles_enviados' => 1, 'roles_seleccionados' => [$this->rol('TECNICO_PLANTA')->id],
        ]))->assertOk()->assertJson(['status' => 200]);

        $this->assertSame(['Planta externa'], TalentoColaborador::where('user_id', $u->id)->pluck('department')->all());
    }

    public function test_clientes_solo_lectura_bajas_e_inactivos_no(): void
    {
        $servicio = app(TalentoColaboradorSyncService::class);
        $cliente = $this->persona(['client']);
        $lectura = $this->persona(['Solo lectura']);
        $inactivo = $this->persona(['TECNICO_PLANTA'], 'inactivo');
        $baja = $this->persona(['TECNICO_PLANTA']);
        $fichaBaja = TalentoColaborador::query()->forceCreate(['user_id' => $baja->id, 'type' => 'interno', 'status' => 'inactive']);
        $fichaBaja->delete();

        foreach ([$cliente, $lectura, $inactivo, $baja] as $u) {
            $this->assertNull($servicio->asegurarFicha($u));
        }
        $this->assertSame(0, TalentoColaborador::whereIn('user_id', [$cliente->id, $lectura->id, $inactivo->id])->count());
        $this->assertSame(1, TalentoColaborador::withTrashed()->where('user_id', $baja->id)->count(), 'La baja no se rehace');
    }

    public function test_la_migracion_se_la_crea_a_quienes_ya_existian_sin_ficha(): void
    {
        $tecnico = $this->persona(['TECNICO_PLANTA']);
        $cliente = $this->persona(['client']);

        (require base_path('app/Modules/Addons/Talento/migrations/2026_11_28_090000_fichas_colaborador_personal_sin_ficha.php'))->up();

        $this->assertSame('Planta externa', TalentoColaborador::where('user_id', $tecnico->id)->value('department'));
        $this->assertFalse(TalentoColaborador::where('user_id', $cliente->id)->exists());
    }
}
