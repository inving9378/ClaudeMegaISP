<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Services\Security\PasswordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\CreatesApplication;

/**
 * 19-nov-2026: todo el personal activo entra a la app de campo. Una cuenta creada en Administración sin ficha
 * de colaborador (vendedores, administración, dueños) entra y la ficha se crea al entrar; una ficha inactiva
 * sigue sin entrar, ahora con el motivo; un cliente no entra. Usuarios sintéticos en una transacción.
 */
class AccesoAppPersonalTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const PASS = 'Prueba-acceso-app';

    private function usuario(array $roles, ?string $statusFicha = null): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'AccesoApp', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'acceso_app_' . Str::random(8), 'password' => PasswordService::make(self::PASS),
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        foreach ($roles as $rol) {
            $user->assignRole(Role::findOrCreate($rol, 'web'));
        }
        if ($statusFicha) {
            DB::table('talento_colaboradores')->insert([
                'user_id' => $id, 'type' => 'interno', 'status' => $statusFicha, 'department' => 'Ventas',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function login(User $u)
    {
        return $this->postJson('/talento/api/auth/login', ['usuario' => $u->login_user, 'password' => self::PASS]);
    }

    public function test_vendedor_sin_ficha_entra_y_se_le_crea_la_ficha_sin_alta_de_rh(): void
    {
        $u = $this->usuario(['Vendedor']);

        $this->login($u)->assertOk()->assertJsonPath('colaborador.es_vendedor', true);

        $ficha = TalentoColaborador::where('user_id', $u->id)->first();
        $this->assertSame('active', $ficha->status);
        $this->assertSame('Ventas', $ficha->department);
        // Sin el alta de RH del observer: no recibe el permiso del portal.
        $this->assertFalse($u->fresh()->hasDirectPermission('portal.colaborador'));
    }

    public function test_rol_de_sistema_sin_ficha_entra(): void
    {
        $u = $this->usuario(['DESARROLLADOR']);

        $this->login($u)->assertOk()->assertJsonStructure(['token', 'colaborador' => ['id']]);
        $this->assertSame(1, TalentoColaborador::where('user_id', $u->id)->count());
    }

    public function test_ficha_inactiva_no_entra_y_dice_el_motivo(): void
    {
        $u = $this->usuario(['Vendedor'], 'inactive');

        $this->login($u)->assertStatus(403)->assertJsonPath('message', 'Tu ficha de colaborador está inactiva. Pide que la activen en Talento → Colaboradores.');
        $this->assertSame('inactive', TalentoColaborador::where('user_id', $u->id)->value('status'));
    }

    public function test_cliente_no_entra_ni_se_le_crea_ficha(): void
    {
        $u = $this->usuario(['client']);

        $this->login($u)->assertStatus(403)->assertJsonPath('message', 'Esta app es solo para el personal de la empresa.');
        $this->assertFalse(TalentoColaborador::withTrashed()->where('user_id', $u->id)->exists());
    }

    public function test_la_sincronizacion_ya_no_inactiva_vendedores_pero_si_a_quien_quedo_como_cliente(): void
    {
        $vendedor = $this->usuario(['Vendedor'], 'active');
        $cliente = $this->usuario(['client'], 'active');

        $this->artisan('talento:sync-colaboradores')->assertSuccessful();

        $this->assertSame('active', TalentoColaborador::where('user_id', $vendedor->id)->value('status'));
        $this->assertSame('inactive', TalentoColaborador::where('user_id', $cliente->id)->value('status'));
    }
}
