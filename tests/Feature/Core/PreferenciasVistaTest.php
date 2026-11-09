<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * Preferencias de vista por usuario (/preferencias-vista): cada quien lee y escribe solo lo suyo,
 * con lista cerrada de vistas y preferencias, validación del valor y tope de tamaño.
 * Datos sintéticos; DatabaseTransactions: nada queda escrito.
 */
class PreferenciasVistaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function usuario(string $sufijo): User
    {
        return User::create([
            'name'       => 'Prueba Preferencias ' . $sufijo,
            'login_user' => 'prueba_preferencias_' . $sufijo . '_' . uniqid(),
            'estado'     => 'activo',
        ])->fresh();
    }

    private function leer(User $user)
    {
        return $this->actingAs($user)->getJson('/preferencias-vista/administradores');
    }

    private function guardar(User $user, string $preferencia, $valor, string $vista = 'administradores')
    {
        return $this->actingAs($user)->putJson("/preferencias-vista/{$vista}/{$preferencia}", ['valor' => $valor]);
    }

    public function test_sin_preferencia_guardada_no_hay_predeterminado(): void
    {
        // La pantalla aplica entonces su valor inicial (Activos, todos los roles).
        $this->leer($this->usuario('nuevo'))->assertOk()->assertExactJson(['preferencias' => []]);
    }

    public function test_guarda_y_lee_la_preferencia_del_usuario(): void
    {
        $user = $this->usuario('guarda');

        $this->guardar($user, 'estado', 'todos')->assertOk();
        $this->guardar($user, 'rol', 'Vendedor')->assertOk();
        $this->guardar($user, 'colores_estado', false)->assertOk();
        $this->guardar($user, 'estado', 'inactivo')->assertOk();

        $this->leer($user)->assertOk()->assertExactJson(['preferencias' => [
            'estado' => 'inactivo',
            'rol' => 'Vendedor',
            'colores_estado' => false,
        ]]);
        $this->assertSame(1, DB::table('preferencias_vista_usuario')
            ->where(['user_id' => $user->id, 'vista' => 'administradores', 'preferencia' => 'estado'])->count());
    }

    public function test_restablecer_borra_la_preferencia(): void
    {
        $user = $this->usuario('restablece');
        $this->guardar($user, 'estado', 'todos')->assertOk();

        $this->guardar($user, 'estado', null)->assertOk()->assertJson(['valor' => null]);

        $this->leer($user)->assertExactJson(['preferencias' => []]);
    }

    public function test_cada_usuario_solo_ve_y_cambia_lo_suyo(): void
    {
        $uno = $this->usuario('uno');
        $otro = $this->usuario('otro');

        $this->guardar($uno, 'estado', 'todos')->assertOk();
        $this->leer($otro)->assertExactJson(['preferencias' => []]);

        // Aunque mande otro user_id, se guarda para quien tiene la sesión.
        $this->actingAs($otro)->putJson('/preferencias-vista/administradores/estado', ['valor' => 'bloqueado', 'user_id' => $uno->id])
            ->assertOk();
        $this->leer($uno)->assertExactJson(['preferencias' => ['estado' => 'todos']]);
        $this->leer($otro)->assertExactJson(['preferencias' => ['estado' => 'bloqueado']]);
    }

    public function test_lista_cerrada_valor_valido_y_tope_de_tamano(): void
    {
        $user = $this->usuario('reglas');

        $this->guardar($user, 'estado', 'todos', 'clientes')->assertNotFound();
        $this->actingAs($user)->getJson('/preferencias-vista/clientes')->assertNotFound();
        $this->guardar($user, 'columnas', ['id'])->assertNotFound();
        $this->guardar($user, 'estado', 'archivado')->assertStatus(422);
        $this->guardar($user, 'estado', ['activo'])->assertStatus(422);
        $this->guardar($user, 'rol', 'client')->assertStatus(422);
        $this->guardar($user, 'colores_estado', 'tal vez')->assertStatus(422);
        $this->guardar($user, 'rol', str_repeat('x', 3000))->assertStatus(422);
        $this->actingAs($user)->putJson('/preferencias-vista/administradores/estado', [])->assertStatus(422);

        $this->leer($user)->assertExactJson(['preferencias' => []]);
    }

    public function test_sin_sesion_no_responde(): void
    {
        $this->getJson('/preferencias-vista/administradores')->assertUnauthorized();
        $this->putJson('/preferencias-vista/administradores/estado', ['valor' => 'todos'])->assertUnauthorized();
    }
}
