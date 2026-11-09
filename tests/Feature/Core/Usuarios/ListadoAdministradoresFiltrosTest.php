<?php

namespace Tests\Feature\Core\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Listado de Administradores: filtros por estado y rol en el servidor, orden, tamaño de página y
 * «todos los registros» (exportar) con el permiso del listado. Sin parámetros, el endpoint debe
 * responder igual que antes: lo usan también las listas de pagos de Vendedores (PaymentsList y
 * PaymentsCompleted llaman getAllUsers() sin parámetros y leen response.data.data).
 *
 * Datos sintéticos con una marca única en el nombre; se buscan por esa marca para no depender de
 * lo que ya tenga la base de pruebas. DatabaseTransactions: nada queda escrito.
 */
class ListadoAdministradoresFiltrosTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const URL = '/administracion/user/get-all-users';

    private string $marca;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->marca = 'ZZLISTADO' . strtoupper(substr(uniqid(), -6));
        // Super-administrator (pasa el middleware de rutas); lleva la marca y es el id más bajo.
        $this->actor = $this->persona('Actor', 'activo', ['super-administrator']);
    }

    private function rol(string $nombre): Role
    {
        return Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
    }

    private function persona(string $nombre, string $estado = 'activo', array $roles = ['Vendedor']): User
    {
        $user = User::create([
            'name'       => $this->marca . ' ' . $nombre,
            'login_user' => 'prueba_listado_' . uniqid(),
            'estado'     => $estado,
        ])->fresh();
        foreach ($roles as $rol) {
            $user->assignRole($this->rol($rol));
        }

        return $user;
    }

    private function listar(array $parametros = [], ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->actor)
            ->getJson(self::URL . ($parametros ? '?' . http_build_query($parametros) : ''));
    }

    private function nombres($respuesta): array
    {
        return array_map(fn ($u) => trim(str_replace($this->marca, '', $u['name'])), $respuesta->json('data'));
    }

    public function test_sin_parametros_responde_como_antes(): void
    {
        $actor = $this->actor;
        $activo = $this->persona('Activo', 'activo', ['Vendedor']);
        $inactivo = $this->persona('Inactivo', 'inactivo', ['TECNICO']);
        $bloqueado = $this->persona('Bloqueado', 'bloqueado', ['Mostrador']);
        $cliente = $this->persona('Cliente', 'activo', ['client']);
        $sinRol = $this->persona('SinRol', 'activo', []);

        $respuesta = $this->listar([], $actor)->assertOk();

        $this->assertSame(49, $respuesta->json('per_page'));
        $this->assertSame(1, $respuesta->json('current_page'));
        $ids = array_column($respuesta->json('data'), 'id');
        $this->assertSame($ids, collect($ids)->sort()->values()->all(), 'Orden de siempre: id ascendente');
        foreach ([$actor, $activo, $inactivo, $bloqueado] as $esperado) {
            $this->assertContains($esperado->id, $ids, 'Sin filtros salen todos los estados y roles de personal');
        }
        $this->assertNotContains($cliente->id, $ids);
        $this->assertNotContains($sinRol->id, $ids);

        $fila = collect($respuesta->json('data'))->firstWhere('id', $activo->id);
        foreach (['role_names', 'talento_colaborador_id', 'rol_name', 'sucursal_str', 'estado', 'login_user'] as $llave) {
            $this->assertArrayHasKey($llave, $fila);
        }
        $this->assertArrayNotHasKey('password', $fila);
        $this->assertArrayNotHasKey('remember_token', $fila);
    }

    public function test_filtra_por_estado_en_el_servidor(): void
    {
        $this->persona('Uno', 'activo');
        $this->persona('Dos', 'inactivo');
        $this->persona('Tres', 'bloqueado');

        $base = ['search' => $this->marca, 'rowsPerPage' => 50];
        // El actor (super-administrator, activo) también lleva la marca.
        $this->assertSame(['Actor', 'Uno'], $this->nombres($this->listar($base + ['estado' => 'activo'])));
        $this->assertSame(['Dos'], $this->nombres($this->listar($base + ['estado' => 'inactivo'])));
        $this->assertSame(['Tres'], $this->nombres($this->listar($base + ['estado' => 'bloqueado'])));
        $this->assertSame(4, $this->listar($base + ['estado' => 'todos'])->json('total'));
        $this->listar($base + ['estado' => 'otro'])->assertStatus(422);
    }

    public function test_filtra_por_rol_y_una_persona_con_dos_roles_sale_en_ambos(): void
    {
        $this->persona('Vende', 'activo', ['Vendedor']);
        $this->persona('Repara', 'activo', ['TECNICO']);
        $this->persona('Ambos', 'inactivo', ['TECNICO', 'Vendedor']);

        $base = ['search' => $this->marca, 'rowsPerPage' => 50];
        $this->assertSame(['Vende', 'Ambos'], $this->nombres($this->listar($base + ['rol' => 'Vendedor'])));
        $this->assertSame(['Repara', 'Ambos'], $this->nombres($this->listar($base + ['rol' => 'TECNICO'])));
        $this->assertSame(['Ambos'], $this->nombres($this->listar($base + ['rol' => 'TECNICO', 'estado' => 'inactivo'])));
        $this->assertSame(4, $this->listar($base + ['rol' => 'todos'])->json('total'));
    }

    public function test_paginacion_respeta_filtros_y_tamano_de_pagina(): void
    {
        foreach (range(1, 7) as $i) {
            $this->persona("Activo{$i}", 'activo');
        }
        foreach (range(1, 3) as $i) {
            $this->persona("Inactivo{$i}", 'inactivo');
        }

        $pagina2 = $this->listar(['search' => $this->marca, 'estado' => 'inactivo', 'rowsPerPage' => 2, 'page' => 2]);
        $this->assertSame(3, $pagina2->json('total'));
        $this->assertSame(2, $pagina2->json('per_page'));
        $this->assertSame(['Inactivo3'], $this->nombres($pagina2));

        $activos = $this->listar(['search' => $this->marca, 'estado' => 'activo', 'rowsPerPage' => 5, 'page' => 2]);
        $this->assertSame(8, $activos->json('total'));
        $this->assertCount(3, $activos->json('data'));
    }

    public function test_ordena_por_columna_en_el_servidor(): void
    {
        $this->persona('Beta', 'activo', ['Vendedor']);
        $this->persona('Alfa', 'activo', ['TECNICO']);
        $this->persona('Gama', 'activo', ['Mostrador']);

        $base = ['search' => $this->marca, 'rowsPerPage' => 50];
        $this->assertSame(['Gama', 'Beta', 'Alfa', 'Actor'], $this->nombres($this->listar($base + ['sortBy' => 'name', 'descending' => 1])));
        $porRol = $this->nombres($this->listar($base + ['sortBy' => 'rol_name', 'descending' => 0]));
        $this->assertSame(['Gama', 'Actor', 'Alfa', 'Beta'], $porRol, 'Mostrador, super-administrator, TECNICO, Vendedor (sin distinguir mayúsculas)');
        $this->listar($base + ['sortBy' => 'password'])->assertStatus(422);
    }

    public function test_todos_los_registros_exige_el_permiso_del_listado(): void
    {
        $this->persona('Uno', 'activo');

        // Quien solo entra por el panel de pagos de Vendedores ve la página normal, pero no «todos».
        Permission::findOrCreate('seller_view_panel', 'web');
        $vendedor = $this->persona('PanelPagos', 'activo', []);
        $vendedor->givePermissionTo('seller_view_panel');
        $this->listar([], $vendedor)->assertOk();
        $this->listar(['rowsPerPage' => 0], $vendedor)->assertForbidden();

        Permission::findOrCreate('user_view_user', 'web');
        $lector = $this->persona('Lector', 'activo', []);
        $lector->givePermissionTo('user_view_user');
        $todos = $this->listar(['search' => $this->marca, 'rowsPerPage' => 0], $lector)->assertOk();
        $this->assertSame($todos->json('total'), count($todos->json('data')));
    }
}
