<?php

namespace Tests\Feature\Core\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #9991149: UserController::update construye $roles solo a partir de
 * $request->role (singular). Si esa key no viene EN ABSOLUTO en el payload,
 * el diff dirigido (assignRole/removeRole) corría igual con $roles=[] y podía
 * remover cualquier rol de staff actual no protegido por $neverRemove.
 *
 * Fix: el bloque de diff (PASO 2) ahora solo corre si $request->has('role')
 * es true — distingue "la key no vino" de "vino vacía/null explícito" (ese
 * caso sigue corriendo el diff igual que antes).
 *
 * USA DatabaseTransactions (rollback al terminar cada test), NUNCA
 * migrate:fresh contra la BD compartida de dev (ver CLAUDE.md sección Tests).
 * Ejecutar selectivo: php artisan test --filter=UserControllerRoleGuardTest
 */
class UserControllerRoleGuardTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function rol(string $nombre): Role
    {
        return Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
    }

    /** Actor con rol super-administrator: bypassea check_route_permission (isAdmin()). */
    private function actor(): User
    {
        $actor = User::create([
            'name'       => 'Actor Prueba Roles',
            'login_user' => 'actor_prueba_roles_' . uniqid(),
            'estado'     => 'activo',
        ])->fresh();
        $actor->assignRole($this->rol('super-administrator'));

        return $actor;
    }

    private function usuarioObjetivo(string $sufijo): User
    {
        return User::create([
            'name'       => 'Usuario Objetivo ' . $sufijo,
            'login_user' => 'usuario_objetivo_' . $sufijo . '_' . uniqid(),
            'estado'     => 'activo',
        ])->fresh();
    }

    private function payloadBase(User $user): array
    {
        return [
            'name'              => $user->name,
            'father_last_name'  => 'Apellido',
            'mother_last_name'  => 'Materno',
            'email'             => $user->login_user . '@example.com',
            'phone'             => '5555555555',
            'address'           => 'Calle de prueba 1',
            'city_municipality' => 'CDMX',
            'state_country'     => 'CDMX',
            'code_postal'       => '00000',
            'rfc'               => 'XAXX010101000',
            'login_user'        => $user->login_user,
            'is_seller'         => 0,
        ];
    }

    /** (a) POST sin la key 'role' -> roles del usuario objetivo no cambian. */
    public function test_update_sin_key_role_no_toca_los_roles_del_usuario(): void
    {
        $this->actingAs($this->actor());

        $roleClient = $this->rol('client');
        $roleStaff  = $this->rol('staff_prueba_' . uniqid());

        $user = $this->usuarioObjetivo('a');
        $user->assignRole([$roleClient->name, $roleStaff->name]);

        $rolesAntes = $user->roles()->pluck('name')->sort()->values()->all();

        $response = $this->postJson(
            route('user.update', $user->id),
            $this->payloadBase($user) // sin 'role' en absoluto
        );

        $response->assertOk();
        $response->assertJson(['status' => 200]);

        $user->refresh();
        $rolesDespues = $user->roles()->pluck('name')->sort()->values()->all();

        $this->assertSame($rolesAntes, $rolesDespues, 'Los roles no debieron cambiar sin la key role en el payload.');
    }

    /**
     * (b) POST con 'role' presente y distinto al actual -> el diff dirigido
     * agrega el nuevo rol, quita el rol de staff anterior no protegido, y
     * NUNCA toca los roles de $neverRemove (aquí: client y super-administrator)
     * aunque no vengan seleccionados en el payload.
     */
    public function test_update_con_key_role_presente_hace_diff_dirigido_y_respeta_never_remove(): void
    {
        $this->actingAs($this->actor());

        $roleClient = $this->rol('client');
        $roleSuper  = $this->rol('super-administrator'); // protegido, ya existe
        $roleOld    = $this->rol('staff_old_' . uniqid());
        $roleNew    = $this->rol('staff_new_' . uniqid());

        $user = $this->usuarioObjetivo('b');
        $user->assignRole([$roleClient->name, $roleSuper->name, $roleOld->name]);

        $payload = $this->payloadBase($user) + ['role' => $roleNew->id];

        $response = $this->postJson(route('user.update', $user->id), $payload);

        $response->assertOk();
        $response->assertJson(['status' => 200]);

        $user->refresh();
        $rolesDespues = $user->roles()->pluck('name')->sort()->values()->all();

        $esperado = collect([$roleClient->name, $roleSuper->name, $roleNew->name])
            ->sort()->values()->all();

        $this->assertSame($esperado, $rolesDespues);
        $this->assertFalse($user->hasRole($roleOld->name), 'El rol de staff anterior no protegido debió quitarse.');
        $this->assertTrue($user->hasRole($roleClient->name), 'client es never-remove: no debe quitarse.');
        $this->assertTrue($user->hasRole($roleSuper->name), 'super-administrator es never-remove: no debe quitarse.');
    }

    /** 'role' presente pero vacío/null explícito -> se trata como el caso (b): el diff SÍ corre. */
    public function test_update_con_key_role_vacia_explicita_si_corre_el_diff(): void
    {
        $this->actingAs($this->actor());

        $roleClient = $this->rol('client');
        $roleOld    = $this->rol('staff_old_vacio_' . uniqid());

        $user = $this->usuarioObjetivo('c');
        $user->assignRole([$roleClient->name, $roleOld->name]);

        $payload = $this->payloadBase($user) + ['role' => ''];

        $response = $this->postJson(route('user.update', $user->id), $payload);

        $response->assertOk();

        $user->refresh();

        // role='' -> Role::findById nunca se llama (if ($request->role) es falsy), $roles=[].
        // El diff SÍ corre (la key está presente) y quita el rol de staff no protegido.
        $this->assertFalse($user->hasRole($roleOld->name), 'Con role vacío explícito el diff debe correr y quitar el rol no protegido.');
        $this->assertTrue($user->hasRole($roleClient->name), 'client sigue never-remove.');
    }
}
