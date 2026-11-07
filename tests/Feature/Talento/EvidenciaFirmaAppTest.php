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

/** La firma del cliente llega del pad de la app en PNG; las fotos siguen siendo solo JPG (2026-11-05). */
class EvidenciaFirmaAppTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $tareaId;

    private int $tipoFirma;

    private int $tipoFoto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $userId = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Firma', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_firma_' . Str::random(8), 'password' => 'x',
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tipoFirma = $this->tipoEvidencia(true);
        $this->tipoFoto = $this->tipoEvidencia(false);

        $tipoOrden = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'Orden sintética ' . Str::random(6), 'category' => 'campo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tareaId = DB::table('tasks')->insertGetId([
            'title' => 'Orden de prueba', 'status' => 'ToDo', 'tipo' => 'campo', 'talento_type_id' => $tipoOrden,
            'start_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $this->tareaId, 'user_id' => $userId]);

        Sanctum::actingAs(User::find($userId));
    }

    private function tipoEvidencia(bool $esFirma): int
    {
        return DB::table('talento_evidence_types')->insertGetId([
            'name' => 'Evidencia sintética ' . Str::random(6), 'es_firma' => $esFirma,
            'permite_varias' => false, 'requiere_justificacion' => false, 'es_lectura_dbm' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function subir(int $tipo, UploadedFile $foto)
    {
        return $this->withHeaders(['Accept' => 'application/json'])->post("/talento/api/ots/{$this->tareaId}/evidencia", [
            'foto' => $foto, 'evidence_type_id' => $tipo, 'lat' => 19.43, 'lng' => -99.13,
        ]);
    }

    public function test_la_firma_en_png_se_guarda_con_su_extension(): void
    {
        $res = $this->subir($this->tipoFirma, UploadedFile::fake()->image('firma.png', 600, 300));

        $res->assertStatus(201);
        $media = DB::table('talento_work_order_media')->where('id', $res->json('media_id'))->first();
        $this->assertSame($this->tareaId, (int) $media->tarea_id);
        $this->assertStringEndsWith('.png', $media->file_path);
        Storage::disk('local')->assertExists($media->file_path);
    }

    public function test_una_foto_en_jpg_se_sigue_guardando_como_jpg(): void
    {
        $res = $this->subir($this->tipoFoto, UploadedFile::fake()->image('evidencia.jpg', 800, 600));

        $res->assertStatus(201);
        $media = DB::table('talento_work_order_media')->where('id', $res->json('media_id'))->first();
        $this->assertStringEndsWith('.jpg', $media->file_path);
    }

    public function test_una_foto_que_no_es_firma_no_se_acepta_en_png(): void
    {
        $this->subir($this->tipoFoto, UploadedFile::fake()->image('evidencia.png', 800, 600))
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    public function test_la_lectura_de_senal_le_dice_a_la_app_su_categoria_y_mensaje(): void
    {
        DB::table('talento_dbm_thresholds')->insert([
            'dbm_min' => -21, 'dbm_max' => -19, 'categoria' => 'excelente', 'accion' => 'ok',
            'mensaje' => 'Mensaje sintético.', 'aplica_bono' => true, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->withHeaders(['Accept' => 'application/json'])->post("/talento/api/ots/{$this->tareaId}/evidencia", [
            'foto' => UploadedFile::fake()->image('senal.jpg', 800, 600), 'evidence_type_id' => $this->tipoFoto,
            'lat' => 19.43, 'lng' => -99.13, 'potencia_dbm' => -20,
        ]);

        $res->assertStatus(201);
        $this->assertSame('excelente', $res->json('salud_red.categoria'));
        $this->assertSame('Mensaje sintético.', $res->json('salud_red.mensaje'));
    }
}
