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

/** El técnico ve en la app la foto de cada evidencia de su orden, con un enlace firmado (#1327, 2026-11-11). */
class EvidenciaFotoAppTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $tareaId;

    private int $tipoFoto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $userId = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Foto', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_foto_' . Str::random(8), 'password' => 'x',
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tipoFoto = DB::table('talento_evidence_types')->insertGetId([
            'name' => 'Evidencia sintética ' . Str::random(6), 'es_firma' => false,
            'permite_varias' => false, 'requiere_justificacion' => false, 'es_lectura_dbm' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
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

    private function subirFoto(): int
    {
        return (int) $this->withHeaders(['Accept' => 'application/json'])->post("/talento/api/ots/{$this->tareaId}/evidencia", [
            'foto' => UploadedFile::fake()->image('evidencia.jpg', 800, 600), 'evidence_type_id' => $this->tipoFoto,
            'lat' => 19.43, 'lng' => -99.13,
        ])->assertStatus(201)->json('media_id');
    }

    private function evidencias(): array
    {
        return $this->getJson("/talento/api/ots/{$this->tareaId}")->assertOk()->json('ot.evidencias');
    }

    public function test_el_detalle_trae_el_enlace_de_la_foto_y_el_enlace_la_devuelve(): void
    {
        $mediaId = $this->subirFoto();

        $evidencia = collect($this->evidencias())->firstWhere('id', $mediaId);
        $this->assertNotNull($evidencia['foto_url']);
        $this->assertArrayNotHasKey('media_type', $evidencia);

        $this->get($evidencia['foto_url'])->assertOk();
    }

    public function test_sin_firma_el_enlace_no_sirve(): void
    {
        $mediaId = $this->subirFoto();

        $this->get("/talento/api/evidencia-foto-firmada/{$mediaId}")->assertStatus(403);
    }

    public function test_un_documento_sensible_no_lleva_enlace_ni_se_sirve(): void
    {
        $mediaId = DB::table('talento_work_order_media')->insertGetId([
            'tarea_id' => $this->tareaId, 'type' => 'ine_front', 'file_path' => 'sintetico/ine.jpg',
            'created_at' => now(),
        ]);

        $evidencia = collect($this->evidencias())->firstWhere('id', $mediaId);
        $this->assertNull($evidencia['foto_url']);

        $firmada = \Illuminate\Support\Facades\URL::temporarySignedRoute('talento.evidencia.foto.firmada', now()->addHour(), ['id' => $mediaId]);
        $this->get($firmada)->assertNotFound();
    }
}
