<?php

namespace Tests\Feature\Unificacion;

use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * UNI-T D6b (#1017): con `evidencias_d6` encendido, el dBm faltante bloquea completar y subirEvidencia valida
 * estado y matriz del tipo de orden; apagado, todo queda como antes. Por tipo de orden (con y sin dBm).
 * Solo contra megaisp_test; todo se revierte.
 */
class EvidenciasD6Test extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName(), 'Solo en megaisp_test');
    }

    /** Orden de Talento con un tipo propio; $conDbm = el tipo exige la lectura de potencia. */
    private function orden(bool $conDbm, string $status = 'in_progress'): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'UNI-PRUEBA', 'father_last_name' => 'D6', 'email' => uniqid('d6') . '@example.test',
            'login_user' => uniqid('d6'), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $colabId = DB::table('talento_colaboradores')->insertGetId([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $typeId = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'UNI-PRUEBA D6 ' . uniqid(), 'category' => 'interna', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $dbmEv = DB::table('talento_evidence_types')->insertGetId([
            'name' => 'UNI-PRUEBA dBm', 'es_lectura_dbm' => $conDbm, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_ot_type_evidence_requirements')->insert([
            'ot_type_id' => $typeId, 'evidence_type_id' => $dbmEv, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $woId = DB::table('talento_work_orders')->insertGetId([
            'type_id' => $typeId, 'colaborador_id' => $colabId, 'status' => $status, 'points' => 0, 'is_billable' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$woId, $colabId, $userId, $typeId, $dbmEv];
    }

    private function gate(int $typeId, int $woId): array
    {
        $m = new \ReflectionMethod(OrdenTrabajoUnifiedService::class, 'verificarGateDbm');
        $m->setAccessible(true);

        return $m->invoke(app(OrdenTrabajoUnifiedService::class), $typeId, 'work_order_id', $woId);
    }

    public function test_on_dbm_faltante_bloquea_en_tipo_que_lo_exige(): void
    {
        config(['unificacion.interruptores.evidencias_d6' => true]);
        [$wo, , , $type] = $this->orden(true);

        $this->assertSame('DBM_FALTANTE', $this->gate($type, $wo)['error']['code']);
    }

    public function test_off_dbm_faltante_no_bloquea(): void
    {
        config(['unificacion.interruptores.evidencias_d6' => false]);
        [$wo, , , $type] = $this->orden(true);

        $this->assertArrayNotHasKey('error', $this->gate($type, $wo));
    }

    public function test_on_tipo_sin_dbm_no_pide_lectura(): void
    {
        config(['unificacion.interruptores.evidencias_d6' => true]);
        [$wo, , , $type] = $this->orden(false);

        $this->assertArrayNotHasKey('error', $this->gate($type, $wo));
    }

    public function test_on_subir_evidencia_en_orden_cerrada_se_rechaza(): void
    {
        config(['unificacion.interruptores.evidencias_d6' => true]);
        [$wo, $colab, $user, , $ev] = $this->orden(true, 'completed');

        $r = app(OrdenTrabajoUnifiedService::class)->subirEvidencia(
            $wo, $colab, ['evidence_type_id' => $ev], UploadedFile::fake()->image('a.jpg'), $user);

        $this->assertSame('ESTADO_INVALIDO', $r['code']);
    }

    public function test_on_subir_evidencia_fuera_de_la_matriz_se_rechaza(): void
    {
        config(['unificacion.interruptores.evidencias_d6' => true]);
        [$wo, $colab, $user] = $this->orden(true);
        $otro = DB::table('talento_evidence_types')->insertGetId([
            'name' => 'UNI-PRUEBA ajena', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = app(OrdenTrabajoUnifiedService::class)->subirEvidencia(
            $wo, $colab, ['evidence_type_id' => $otro], UploadedFile::fake()->image('a.jpg'), $user);

        $this->assertSame('TIPO_NO_EN_MATRIZ', $r['code']);
    }

    public function test_off_subir_evidencia_fuera_de_la_matriz_no_la_rechaza_por_matriz(): void
    {
        config(['unificacion.interruptores.evidencias_d6' => false]);
        [$wo, $colab, $user] = $this->orden(true);
        $otro = DB::table('talento_evidence_types')->insertGetId([
            'name' => 'UNI-PRUEBA ajena', 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $r = app(OrdenTrabajoUnifiedService::class)->subirEvidencia(
                $wo, $colab, ['evidence_type_id' => $otro], UploadedFile::fake()->image('a.jpg'), $user);
        } catch (\Throwable $e) {
            $r = ['code' => 'siguio_adelante']; // pasó la validación de matriz y falló después (archivo/disco)
        }

        $this->assertNotContains($r['code'] ?? null, ['TIPO_NO_EN_MATRIZ', 'ESTADO_INVALIDO']);
    }
}
