<?php

namespace Tests\Feature\Vendedores;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #1199: la lista de Vendedores filtra por estado en el servidor e incluye a los vendedores
 * Inactivos (los que perdieron el rol al quitarles el check «Añadir rol de vendedor») y a los que
 * no tienen tipo. Sin `estado` responde igual que antes (Comisiones y el menú de la ficha).
 * Datos sintéticos en megaisp_test; DatabaseTransactions: nada queda escrito.
 */
class ListaVendedoresEstadoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const URL = '/vendedores/data';

    private User $actor;

    private array $estado = [];

    private int $tipo;

    /** @var array<string, int> nombre del caso → sellers.id */
    private array $vendedores = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['Activo', 'Inactivo', 'Bloqueado'] as $nombre) {
            $this->estado[$nombre] = DB::table('seller_status')->where('name', $nombre)->orderBy('id')->value('id')
                ?? DB::table('seller_status')->insertGetId(['name' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->tipo = DB::table('seller_types')->insertGetId(['name' => 'Tipo de prueba', 'created_at' => now(), 'updated_at' => now()]);
        $this->actor = $this->persona('actor', ['super-administrator']);

        $this->vendedores = [
            'activo' => $this->vendedor('activo', ['Vendedor'], 'Activo'),
            'activo_sin_tipo' => $this->vendedor('activosintipo', ['Vendedor'], 'Activo', false),
            'inactivo_con_rol' => $this->vendedor('inactivoconrol', ['Vendedor'], 'Inactivo'),
            'inactivo_sin_rol' => $this->vendedor('inactivosinrol', ['TECNICO'], 'Inactivo'),
            'bloqueado' => $this->vendedor('bloqueado', ['Vendedor'], 'Bloqueado'),
        ];
    }

    private function persona(string $sufijo, array $roles): User
    {
        $user = User::create([
            'name' => 'Prueba Lista ' . $sufijo,
            'login_user' => 'prueba_lista_' . $sufijo . '_' . uniqid(),
            'estado' => 'activo',
        ])->fresh();
        foreach ($roles as $rol) {
            $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']));
        }

        return $user;
    }

    private function vendedor(string $sufijo, array $roles, string $estado, bool $conTipo = true): int
    {
        return DB::table('sellers')->insertGetId([
            'user_id' => $this->persona($sufijo, $roles)->id, 'status_id' => $this->estado[$estado],
            'type_id' => $conTipo ? $this->tipo : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Casos de esta prueba que salen en la lista, por nombre. */
    private function casos(array $parametros = []): array
    {
        $ids = collect($this->actingAs($this->actor)->getJson(self::URL . ($parametros ? '?' . http_build_query($parametros) : ''))
            ->assertOk()->json())->pluck('seller_id')->all();

        return array_keys(array_filter($this->vendedores, fn ($id) => in_array($id, $ids, true)));
    }

    public function test_sin_estado_responde_como_antes(): void
    {
        // Solo con rol Vendedor y con tipo, de cualquier estado.
        $this->assertSame(['activo', 'inactivo_con_rol', 'bloqueado'], $this->casos());

        $fila = collect($this->actingAs($this->actor)->getJson(self::URL)->json())->firstWhere('seller_id', $this->vendedores['activo']);
        foreach (['seller_id', 'name', 'status_seller', 'type', 'balance'] as $llave) {
            $this->assertArrayHasKey($llave, $fila);
        }
    }

    public function test_filtra_por_estado_e_incluye_inactivos_sin_rol_y_sin_tipo(): void
    {
        $this->assertSame(['activo', 'activo_sin_tipo'], $this->casos(['estado' => 'activo']));
        $this->assertSame(['inactivo_con_rol', 'inactivo_sin_rol'], $this->casos(['estado' => 'inactivo']));
        $this->assertSame(['bloqueado'], $this->casos(['estado' => 'bloqueado']));
        $this->assertSame(array_keys($this->vendedores), $this->casos(['estado' => 'todos']));

        $this->actingAs($this->actor)->getJson(self::URL . '?estado=otro')->assertStatus(422);
    }

    public function test_la_ficha_de_un_vendedor_inactivo_sin_rol_se_abre_y_avisa_su_estado(): void
    {
        $sellerId = $this->vendedores['inactivo_sin_rol'];
        $userId = DB::table('sellers')->where('id', $sellerId)->value('user_id');

        $this->actingAs($this->actor)->get("/vendedores/{$sellerId}/seguimiento-vendedor/{$userId}")
            ->assertOk()
            ->assertViewHas('seller_activo', false)
            ->assertViewHas('seller_estado', 'inactivo');

        $activo = $this->vendedores['activo'];
        $this->actingAs($this->actor)->get("/vendedores/{$activo}/seguimiento-vendedor/" . DB::table('sellers')->where('id', $activo)->value('user_id'))
            ->assertOk()
            ->assertViewHas('seller_activo', true);
    }

    public function test_predeterminado_de_estado_por_usuario(): void
    {
        $otro = $this->persona('otro', ['super-administrator']);

        $this->actingAs($this->actor)->getJson('/preferencias-vista/vendedores')->assertExactJson(['preferencias' => []]);
        $this->actingAs($this->actor)->putJson('/preferencias-vista/vendedores/estado', ['valor' => 'inactivo'])->assertOk();
        $this->actingAs($this->actor)->getJson('/preferencias-vista/vendedores')->assertExactJson(['preferencias' => ['estado' => 'inactivo']]);
        $this->actingAs($otro)->getJson('/preferencias-vista/vendedores')->assertExactJson(['preferencias' => []]);

        $this->actingAs($this->actor)->putJson('/preferencias-vista/vendedores/estado', ['valor' => 'suspendido'])->assertStatus(422);
        $this->actingAs($this->actor)->putJson('/preferencias-vista/vendedores/rol', ['valor' => 'todos'])->assertNotFound();
    }
}
