<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * Item roadmap #9990835 (Fase 2 de #9990833): backfill de
 * client_additional_information.serie_equipo/serie_equipo_norm/serie_equipo_origen
 * vía `clientes:normalizar-series`.
 *
 * Criterios de aceptación del item que este test cubre corriendo el comando
 * DOS veces sobre el mismo dataset:
 *   (a) idempotente — la 2a corrida da el mismo resultado que la 1a.
 *   (b) modem_sn JAMÁS se modifica (D3) — se compara byte a byte contra el
 *       valor original, incluso cuando queda con espacios/minúsculas.
 *   (c) el CSV de excepciones (D5) es coherente entre corridas.
 *
 * Los pares SN-corto→SN-canónico son los mismos que ya cubre
 * tests/Unit/Services/ClienteSearchServiceNormalizarSnTest.php (no se
 * reinventan aquí — este test es de integración del comando, no del
 * algoritmo de normalización).
 *
 * Usa `DatabaseTransactions` (rollback al terminar cada test) + `CreatesApplication`
 * en vez de `Tests\TestCase`: ese `setUp()` corre `migrate:fresh --seed` en CADA
 * test, y hoy eso truena por un bug preexistente y AJENO a este item (migración
 * `2026_09_02_150000_seed_talento_document_templates_personal` referencia una
 * columna `tipo_cambio` inexistente) — no es de este módulo, no se toca aquí.
 * Las tablas que este test necesita (`clients`, `client_additional_information`,
 * `olt_onus`, `olts`, `users`) ya existen en la base de pruebas. Mismo patrón que
 * `tests/Feature/Roadmap/SubItemCommandDependeDeTest.php`.
 */
class NormalizarSeriesClientesCommandTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $userId;
    private int $oltId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name' => 'Test Normalizar Series',
            'login_user' => 'test_normalizar_series_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->oltId = DB::table('olts')->insertGetId([
            'name' => 'OLT de prueba #9990835',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearCliente(): int
    {
        return DB::table('clients')->insertGetId([
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearCai(int $clientId, ?string $modemSn): void
    {
        DB::table('client_additional_information')->insert([
            'client_id' => $clientId,
            'modem_sn' => $modemSn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearOnu(int $clientId, string $sn): void
    {
        DB::table('olt_onus')->insert([
            'client_id' => $clientId,
            'olt_id' => $this->oltId,
            'sn' => $sn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function snapshotCai(int $clientId): array
    {
        return (array) DB::table('client_additional_information')
            ->where('client_id', $clientId)
            ->first();
    }

    private function leerCsv(string $path): array
    {
        $filas = [];
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        while (($fila = fgetcsv($handle)) !== false) {
            $filas[] = array_combine($header, $fila);
        }
        fclose($handle);

        return $filas;
    }

    public function test_backfill_es_idempotente_no_toca_modem_sn_y_el_csv_es_coherente(): void
    {
        // Cliente 1: tiene ONU en la OLT -> la OLT gana sobre modem_sn (D4 #1).
        // modem_sn trae un valor DISTINTO a propósito: si el comando lo tocara
        // por error, esta prueba lo detecta.
        $cliente1 = $this->crearCliente();
        $this->crearOnu($cliente1, 'ECOMC8012F9B');
        $this->crearCai($cliente1, 'MODEM-SN-QUE-NO-DEBE-TOCARSE');

        // Cliente 2: sin fila en olt_onus -> cae a modem_sn (D4 #3, "manual").
        // El valor trae espacios/minúsculas a propósito para que el modem_sn
        // ORIGINAL (con ese formato sucio) se pueda comparar literal después.
        $cliente2 = $this->crearCliente();
        $modemSnSucio = ' hwtc fefc c9a2 ';
        $this->crearCai($cliente2, $modemSnSucio);

        // Cliente 3: modem_sn no normaliza a 16 hex -> excepción (D5), sin tocar la fila.
        $cliente3 = $this->crearCliente();
        $this->crearCai($cliente3, 'GARBAGE-NOT-HEX');

        // Cliente 4: tiene ONU pero NO tiene fila en client_additional_information
        // -> excepción "sin fila" (el UPDATE no tendría dónde escribir).
        // SN distinto al del cliente 1 (olt_onus tiene unique en sn+olt_id).
        $cliente4 = $this->crearCliente();
        $this->crearOnu($cliente4, 'ECOMC8012F9C');

        // Cliente 5: sin ONU y sin modem_sn -> nada que hacer, no es excepción.
        $cliente5 = $this->crearCliente();
        $this->crearCai($cliente5, null);

        $modemSnOriginales = [
            $cliente1 => 'MODEM-SN-QUE-NO-DEBE-TOCARSE',
            $cliente2 => $modemSnSucio,
            $cliente3 => 'GARBAGE-NOT-HEX',
            $cliente5 => null,
        ];

        $csvPath = sys_get_temp_dir() . '/normalizar_series_test_' . uniqid() . '.csv';

        // --- Corrida 1 ---
        Artisan::call('clientes:normalizar-series', ['--csv' => $csvPath]);

        $cai1Post1 = $this->snapshotCai($cliente1);
        $this->assertSame('45434F4DC8012F9B', $cai1Post1['serie_equipo_norm']);
        $this->assertSame('ECOMC8012F9B', $cai1Post1['serie_equipo']);
        $this->assertSame('olt', $cai1Post1['serie_equipo_origen']);
        $this->assertSame($modemSnOriginales[$cliente1], $cai1Post1['modem_sn']);

        $cai2Post1 = $this->snapshotCai($cliente2);
        $this->assertSame('48575443FEFCC9A2', $cai2Post1['serie_equipo_norm']);
        $this->assertSame('HWTCFEFCC9A2', $cai2Post1['serie_equipo']);
        $this->assertSame('manual', $cai2Post1['serie_equipo_origen']);
        $this->assertSame($modemSnOriginales[$cliente2], $cai2Post1['modem_sn']);

        $cai3Post1 = $this->snapshotCai($cliente3);
        $this->assertNull($cai3Post1['serie_equipo_norm']);
        $this->assertNull($cai3Post1['serie_equipo_origen']);
        $this->assertSame($modemSnOriginales[$cliente3], $cai3Post1['modem_sn']);

        $cai5Post1 = $this->snapshotCai($cliente5);
        $this->assertNull($cai5Post1['serie_equipo_norm']);
        $this->assertNull($cai5Post1['modem_sn']);

        $csvPost1 = $this->leerCsv($csvPath);
        $porCliente1 = collect($csvPost1)->keyBy('cliente_id');
        $this->assertTrue($porCliente1->has((string) $cliente3), 'Cliente 3 debe salir en el CSV de excepciones (no normaliza).');
        $this->assertTrue($porCliente1->has((string) $cliente4), 'Cliente 4 debe salir en el CSV de excepciones (sin fila cai).');
        $this->assertFalse($porCliente1->has((string) $cliente1), 'Cliente 1 normalizó correcto, no debe salir en el CSV.');
        $this->assertFalse($porCliente1->has((string) $cliente2), 'Cliente 2 normalizó correcto, no debe salir en el CSV.');
        $this->assertFalse($porCliente1->has((string) $cliente5), 'Cliente 5 no tiene dato, no es una excepción.');
        $this->assertCount(2, $csvPost1);

        // --- Corrida 2 (idempotencia) ---
        Artisan::call('clientes:normalizar-series', ['--csv' => $csvPath]);

        $this->assertSame($cai1Post1, $this->snapshotCai($cliente1), 'La 2a corrida no debe cambiar nada del cliente 1.');
        $this->assertSame($cai2Post1, $this->snapshotCai($cliente2), 'La 2a corrida no debe cambiar nada del cliente 2.');
        $this->assertSame($cai3Post1, $this->snapshotCai($cliente3), 'La 2a corrida no debe cambiar nada del cliente 3.');
        $this->assertSame($cai5Post1, $this->snapshotCai($cliente5), 'La 2a corrida no debe cambiar nada del cliente 5.');

        // (b) modem_sn nunca se toca, en NINGUNA corrida.
        foreach ($modemSnOriginales as $clientId => $original) {
            $this->assertSame($original, $this->snapshotCai($clientId)['modem_sn'], "modem_sn del cliente {$clientId} no debe cambiar jamás.");
        }

        // (c) el CSV de la 2a corrida reporta exactamente las mismas excepciones.
        $csvPost2 = $this->leerCsv($csvPath);
        $porCliente2 = collect($csvPost2)->keyBy('cliente_id');
        $this->assertSame(
            $porCliente1->keys()->sort()->values()->all(),
            $porCliente2->keys()->sort()->values()->all(),
            'El CSV de excepciones debe ser idéntico (mismos clientes) entre corridas.'
        );

        @unlink($csvPath);
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $cliente = $this->crearCliente();
        $this->crearOnu($cliente, 'ECOMC8012F9B');
        $this->crearCai($cliente, null);

        $antes = $this->snapshotCai($cliente);

        Artisan::call('clientes:normalizar-series', ['--dry-run' => true]);

        $this->assertSame($antes, $this->snapshotCai($cliente), '--dry-run no debe escribir nada en la BD.');
    }
}
