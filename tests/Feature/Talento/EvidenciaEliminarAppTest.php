<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;

/**
 * El técnico ve en la pantalla de evidencias las fotos que ya tomó y quita la que salió mal para
 * tomarla de nuevo, solo mientras la orden sigue abierta (#1347, 2026-11-11). Datos sintéticos;
 * correr SOLO por nombre (megaisp_test, transacción que se deshace).
 */
class EvidenciaEliminarAppTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $tareaId;

    private int $tipoFoto;

    private int $tipoOrden;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tipoFoto = DB::table('talento_evidence_types')->insertGetId([
            'name' => 'Evidencia sintética ' . Str::random(6), 'es_firma' => false,
            'permite_varias' => false, 'requiere_justificacion' => false, 'es_lectura_dbm' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tipoOrden = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'Orden sintética ' . Str::random(6), 'category' => 'campo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = $this->tecnico();
        $this->tareaId = $this->orden($userId);
        Sanctum::actingAs(User::find($userId));
    }

    private function tecnico(): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Evidencia', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_ev_' . Str::random(8), 'password' => 'x',
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $userId;
    }

    private function orden(int $userId): int
    {
        $tareaId = DB::table('tasks')->insertGetId([
            'title' => 'Orden de prueba', 'status' => 'ToDo', 'tipo' => 'campo', 'talento_type_id' => $this->tipoOrden,
            'start_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $tareaId, 'user_id' => $userId]);

        return $tareaId;
    }

    private function subirFoto(): int
    {
        return (int) $this->withHeaders(['Accept' => 'application/json'])->post("/talento/api/ots/{$this->tareaId}/evidencia", [
            'foto' => UploadedFile::fake()->image('evidencia.jpg', 800, 600), 'evidence_type_id' => $this->tipoFoto,
            'lat' => 19.43, 'lng' => -99.13,
        ])->assertStatus(201)->json('media_id');
    }

    private function captura(): array
    {
        return $this->getJson("/talento/api/ots/{$this->tareaId}/tipos-evidencia")->assertOk()->json();
    }

    public function test_la_foto_sale_en_la_pantalla_de_evidencias_y_se_quita_para_tomarla_de_nuevo(): void
    {
        $mediaId = $this->subirFoto();
        $archivo = DB::table('talento_work_order_media')->where('id', $mediaId)->value('file_path');

        $captura = $this->captura();
        $this->assertTrue($captura['puede_eliminar']);
        $foto = collect($captura['evidencias'])->firstWhere('id', $mediaId);
        $this->assertSame($this->tipoFoto, $foto['type_id']);
        $this->assertNotNull($foto['foto_url']);
        $this->assertArrayNotHasKey('media_type', $foto);

        $this->deleteJson("/talento/api/ots/{$this->tareaId}/evidencia/{$mediaId}")->assertOk();

        $this->assertDatabaseMissing('talento_work_order_media', ['id' => $mediaId]);
        Storage::disk('local')->assertMissing($archivo);
        $this->assertNotContains($this->tipoFoto, $this->captura()['subidos']);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'talento', 'subject_id' => $this->tareaId,
            'description' => 'Evidencia quitada desde la app para tomarla de nuevo',
        ]);

        // El tipo es de una sola foto: ya se puede tomar otra.
        $this->subirFoto();
    }

    public function test_con_la_orden_terminada_ya_no_se_quita(): void
    {
        $mediaId = $this->subirFoto();
        DB::table('tasks')->where('id', $this->tareaId)->update(['status' => 'Done']);

        $this->assertFalse($this->captura()['puede_eliminar']);
        $this->deleteJson("/talento/api/ots/{$this->tareaId}/evidencia/{$mediaId}")->assertStatus(422);
        $this->assertDatabaseHas('talento_work_order_media', ['id' => $mediaId]);
    }

    public function test_no_se_quita_la_foto_de_una_orden_ajena(): void
    {
        $otraOrden = $this->orden($this->tecnico());
        $ajena = DB::table('talento_work_order_media')->insertGetId([
            'tarea_id' => $otraOrden, 'evidence_type_id' => $this->tipoFoto, 'type' => 'completion',
            'file_path' => 'sintetico/ajena.jpg', 'created_at' => now(),
        ]);

        // Ni por la orden propia ni por la ajena (no está asignada a quien pide).
        $this->deleteJson("/talento/api/ots/{$this->tareaId}/evidencia/{$ajena}")->assertNotFound();
        $this->deleteJson("/talento/api/ots/{$otraOrden}/evidencia/{$ajena}")->assertNotFound();
        $this->assertDatabaseHas('talento_work_order_media', ['id' => $ajena]);
    }

    public function test_un_documento_sensible_no_se_quita_por_aqui(): void
    {
        $sensible = DB::table('talento_work_order_media')->insertGetId([
            'tarea_id' => $this->tareaId, 'evidence_type_id' => $this->tipoFoto, 'type' => 'ine_front',
            'file_path' => 'sintetico/ine.jpg', 'created_at' => now(),
        ]);

        $this->deleteJson("/talento/api/ots/{$this->tareaId}/evidencia/{$sensible}")->assertNotFound();
        $this->assertDatabaseHas('talento_work_order_media', ['id' => $sensible]);
    }
}
