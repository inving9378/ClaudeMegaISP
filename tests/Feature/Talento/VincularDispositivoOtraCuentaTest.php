<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Controllers\TalentoDeviceController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\CreatesApplication;

/**
 * device_key es único en toda la tabla. Vincular un teléfono ya registrado a otra cuenta daba 500.
 */
class VincularDispositivoOtraCuentaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function colaborador(string $login): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => $login, 'login_user' => $login, 'email' => $login.'@prueba.test', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('talento_colaboradores')->insertGetId($this->filaColaborador($userId));
    }

    private function filaColaborador(int $userId): array
    {
        $fila = ['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()];
        $obligatorias = DB::select("select column_name c, data_type t from information_schema.columns
            where table_schema = database() and table_name = 'talento_colaboradores' and is_nullable = 'NO'
            and column_default is null and extra not like '%auto_increment%'");
        foreach ($obligatorias as $col) {
            $fila[$col->c] ??= in_array($col->t, ['int', 'bigint', 'tinyint', 'decimal', 'smallint'], true)
                ? 0
                : ($col->t === 'date' ? '2026-01-01' : ($col->t === 'datetime' || $col->t === 'timestamp' ? now() : 'x'));
        }

        return $fila;
    }

    private function vincular(int $colaboradorId, string $llave)
    {
        Gate::before(fn () => true);
        $this->actingAs(new \App\Models\User());

        return app(TalentoDeviceController::class)->bind(
            Request::create('/', 'POST', ['device_key' => $llave, 'platform' => 'android']),
            $colaboradorId
        );
    }

    private function userId(int $colaboradorId): int
    {
        return (int) DB::table('talento_colaboradores')->where('id', $colaboradorId)->value('user_id');
    }

    public function test_telefono_activo_de_otra_cuenta_responde_409_y_no_se_le_quita(): void
    {
        $a = $this->colaborador('prueba_disp_a');
        $b = $this->colaborador('prueba_disp_b');
        $this->assertSame(201, $this->vincular($a, 'llave-prueba-1')->getStatusCode());

        $respuesta = $this->vincular($b, 'llave-prueba-1');

        $this->assertSame(409, $respuesta->getStatusCode());
        $this->assertSame('device_in_use', $respuesta->getData()->status);
        $fila = DB::table('talento_devices')->where('device_key', 'llave-prueba-1')->first();
        $this->assertSame($this->userId($a), (int) $fila->user_id);
        $this->assertNull($fila->revoked_at);
    }

    public function test_telefono_revocado_de_otra_cuenta_pasa_a_la_nueva(): void
    {
        $a = $this->colaborador('prueba_disp_c');
        $b = $this->colaborador('prueba_disp_d');
        $this->vincular($a, 'llave-prueba-2');
        DB::table('talento_devices')->where('device_key', 'llave-prueba-2')->update(['revoked_at' => now()]);

        $respuesta = $this->vincular($b, 'llave-prueba-2');

        $this->assertSame(201, $respuesta->getStatusCode());
        $fila = DB::table('talento_devices')->where('device_key', 'llave-prueba-2')->first();
        $this->assertSame($this->userId($b), (int) $fila->user_id);
        $this->assertNull($fila->revoked_at);
    }

    public function test_el_mismo_telefono_de_la_misma_cuenta_sigue_activo(): void
    {
        $a = $this->colaborador('prueba_disp_e');
        $this->vincular($a, 'llave-prueba-3');

        $this->assertSame('already_active', $this->vincular($a, 'llave-prueba-3')->getData()->status);
    }
}
