<?php

namespace Tests\Feature\Talento;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #1286: sobre una orden terminada el supervisor pide fotos de corrección con un motivo; el técnico
 * las sube desde la app (aunque el tipo ya tenga foto) y las envía; el supervisor la da por buena. La
 * orden no cambia de estado ni de puntos. Datos sintéticos; correr SOLO por nombre (megaisp_test).
 */
class CorreccionEvidenciasTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const SUPERVISOR = ['talento.work_orders.view', 'talento.work_orders.manage', 'talento.work_orders.validate', 'talento.media.view'];

    private int $tecnicoId;

    private int $tipoFoto;

    private int $tipoFuera;

    private int $tareaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Storage::fake('local');
        Notification::fake();
        foreach (self::SUPERVISOR as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tipoFoto = $this->tipoEvidencia('Equipo sintético');
        $this->tipoFuera = $this->tipoEvidencia('Fuera de la matriz');
        $tipoOrden = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'Orden sintética ' . Str::random(6), 'category' => 'campo', 'points' => 9,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_ot_type_evidence_requirements')->insert([
            'ot_type_id' => $tipoOrden, 'evidence_type_id' => $this->tipoFoto, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->tecnicoId = $this->usuario();
        DB::table('talento_colaboradores')->insert([
            'user_id' => $this->tecnicoId, 'type' => 'interno', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Terminada ayer y validada al terminarse, como hoy con la validación separada apagada.
        $this->tareaId = DB::table('tasks')->insertGetId([
            'title' => 'Orden de prueba', 'status' => 'Done', 'tipo' => 'campo', 'talento_type_id' => $tipoOrden,
            'points' => 9, 'start_date' => now()->subDay()->toDateString(), 'finish_at' => now()->subDay(),
            'validated_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $this->tareaId, 'user_id' => $this->tecnicoId]);
        DB::table('talento_work_order_media')->insert([
            'tarea_id' => $this->tareaId, 'evidence_type_id' => $this->tipoFoto, 'type' => 'completion',
            'file_path' => 'sintetico/original.jpg', 'created_at' => now()->subDay(),
        ]);
    }

    private function tipoEvidencia(string $nombre): int
    {
        return DB::table('talento_evidence_types')->insertGetId([
            'name' => $nombre . ' ' . Str::random(6), 'es_firma' => false, 'permite_varias' => false,
            'requiere_justificacion' => false, 'es_lectura_dbm' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function usuario(array $permisos = []): int
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => Str::random(6), 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permisos && User::find($id)->givePermissionTo($permisos);

        return $id;
    }

    private function comoSupervisor(): static
    {
        return $this->actingAs(User::find($this->usuario(self::SUPERVISOR)));
    }

    private function comoTecnico(): static
    {
        Sanctum::actingAs(User::find($this->tecnicoId));

        return $this;
    }

    private function pedir(string $motivo = 'La foto del equipo salió borrosa')
    {
        return $this->comoSupervisor()->postJson("/talento/api/ordenes/{$this->tareaId}/correccion", ['motivo' => $motivo]);
    }

    private function subirFoto(int $tipo)
    {
        return $this->comoTecnico()->withHeaders(['Accept' => 'application/json'])->post("/talento/api/ots/{$this->tareaId}/evidencia", [
            'foto' => UploadedFile::fake()->image('evidencia.jpg', 800, 600), 'evidence_type_id' => $tipo,
            'lat' => 19.43, 'lng' => -99.13,
        ]);
    }

    private function enMiDia(): ?array
    {
        return collect($this->comoTecnico()->getJson('/talento/api/ots/hoy')->assertOk()->json('ots'))->firstWhere('id', $this->tareaId);
    }

    public function test_el_supervisor_pide_la_correccion_el_tecnico_sube_las_fotos_y_la_envia(): void
    {
        $antes = DB::table('tasks')->where('id', $this->tareaId)->first(['status', 'validated_at', 'points']);
        $this->assertNull($this->enMiDia(), 'terminada ayer: no está en Mi día');

        $this->pedir()->assertOk()->assertJsonPath('correccion.estado', 'pedida');
        $despues = DB::table('tasks')->where('id', $this->tareaId)->first(['status', 'validated_at', 'points']);
        $this->assertEquals($antes, $despues, 'ni estado, ni validación, ni puntos cambian');

        // Vuelve a Mi día con la corrección, aunque su día ya pasó.
        $this->assertSame('pedida', $this->enMiDia()['correccion']['estado']);
        $this->assertSame('La foto del equipo salió borrosa', $this->enMiDia()['correccion']['motivo']);
        $this->assertSame('validated', $this->enMiDia()['status']);
        $this->assertSame('correccion', $this->enMiDia()['correccion']['modo']);
        $aviso = collect($this->comoTecnico()->getJson('/talento/api/avisos')->assertOk()->json('correcciones'))->firstWhere('ot_id', $this->tareaId);
        $this->assertNotNull($aviso);
        $this->assertFalse($aviso['devuelta']);

        // La pantalla de evidencias ofrece los tipos de la orden; ninguno es obligatorio.
        $captura = $this->comoTecnico()->getJson("/talento/api/ots/{$this->tareaId}/tipos-evidencia")->assertOk()->json();
        $this->assertSame('pedida', $captura['correccion']['estado']);
        $this->assertSame([], $captura['requeridos']);
        $ids = array_column($captura['tipos'], 'id');
        $this->assertContains($this->tipoFoto, $ids);
        $this->assertNotContains($this->tipoFuera, $ids);
        $this->subirFoto($this->tipoFuera)->assertStatus(422)->assertJsonPath('code', 'TIPO_NO_EN_MATRIZ');

        // Sin fotos no se envía.
        $this->comoTecnico()->postJson("/talento/api/ots/{$this->tareaId}/correccion/enviar")->assertStatus(422)->assertJsonPath('code', 'SIN_FOTOS');

        // El tipo ya tiene foto y no acepta varias, pero en la corrección sí entra.
        $mediaId = $this->subirFoto($this->tipoFoto)->assertStatus(201)->json('media_id');
        $correccionId = DB::table('talento_orden_correcciones')->where('tarea_id', $this->tareaId)->value('id');
        $this->assertSame($correccionId, DB::table('talento_work_order_media')->where('id', $mediaId)->value('correccion_id'));

        // La foto de la corrección se puede quitar; la original no.
        $fotos = collect($this->comoTecnico()->getJson("/talento/api/ots/{$this->tareaId}/tipos-evidencia")->json('evidencias'));
        $this->assertTrue($fotos->firstWhere('id', $mediaId)['quitable']);
        $original = $fotos->first(fn ($f) => $f['id'] !== $mediaId);
        $this->assertFalse($original['quitable']);
        $this->comoTecnico()->deleteJson("/talento/api/ots/{$this->tareaId}/evidencia/{$original['id']}")->assertStatus(422);
        $this->comoTecnico()->deleteJson("/talento/api/ots/{$this->tareaId}/evidencia/{$mediaId}")->assertOk();
        $this->subirFoto($this->tipoFoto)->assertStatus(201);

        $this->comoTecnico()->postJson("/talento/api/ots/{$this->tareaId}/correccion/enviar", ['nota' => 'Ya se ve el serial'])->assertOk();
        $this->assertNull($this->enMiDia(), 'enviada: sale de Mi día');
        $this->subirFoto($this->tipoFoto)->assertStatus(422)->assertJsonPath('code', 'TIPO_DUPLICADO');

        // El supervisor la ve para revisar, con las fotos aparte de las originales.
        $lista = $this->comoSupervisor()->getJson('/talento/api/ordenes?status=correccion_revisar')->assertOk();
        $this->assertGreaterThanOrEqual(1, $lista->json('correcciones_por_revisar'));
        $this->assertSame('enviada', collect($lista->json('data'))->firstWhere('id', $this->tareaId)['correccion']['estado']);

        $doc = $this->comoSupervisor()->getJson("/talento/api/ordenes/{$this->tareaId}/documentacion")->assertOk();
        $this->assertSame(1, collect($doc->json('fotos'))->whereNotNull('correccion_id')->count());
        $this->assertSame(1, collect($doc->json('requeridas'))->firstWhere('id', $this->tipoFoto)['fotos'], 'lo obligatorio cuenta solo las originales');
        $this->assertSame('Ya se ve el serial', $doc->json('correcciones.0.nota_tecnico'));

        $this->comoSupervisor()->postJson("/talento/api/ordenes/{$this->tareaId}/correccion/cerrar", ['cierre' => 'aceptada'])
            ->assertOk()->assertJsonPath('correccion', null);
        $this->assertSame('aceptada', DB::table('talento_orden_correcciones')->where('id', $correccionId)->value('cierre'));
    }

    public function test_sin_permiso_de_validar_no_se_pide(): void
    {
        $this->actingAs(User::find($this->usuario(['talento.work_orders.view', 'talento.work_orders.manage'])))
            ->postJson("/talento/api/ordenes/{$this->tareaId}/correccion", ['motivo' => 'La foto salió mal'])
            ->assertForbidden();
        $this->assertDatabaseMissing('talento_orden_correcciones', ['tarea_id' => $this->tareaId]);
    }

    public function test_solo_sobre_una_orden_terminada_y_una_a_la_vez(): void
    {
        DB::table('tasks')->where('id', $this->tareaId)->update(['status' => 'InProgress']);
        $this->pedir()->assertStatus(422);

        DB::table('tasks')->where('id', $this->tareaId)->update(['status' => 'Done']);
        $this->pedir()->assertOk();
        $this->pedir('Otra cosa más')->assertStatus(422);
    }

    public function test_pedir_otra_sobre_una_enviada_la_reemplaza(): void
    {
        $this->pedir()->assertOk();
        $this->subirFoto($this->tipoFoto)->assertStatus(201);
        $this->comoTecnico()->postJson("/talento/api/ots/{$this->tareaId}/correccion/enviar")->assertOk();

        $this->pedir('Falta la foto de la roseta')->assertOk()->assertJsonPath('correccion.motivo', 'Falta la foto de la roseta');
        $this->assertSame(['reemplazada', null], DB::table('talento_orden_correcciones')
            ->where('tarea_id', $this->tareaId)->orderBy('id')->pluck('cierre')->all());

        // Cancelar la nueva la saca de Mi día.
        $this->comoSupervisor()->postJson("/talento/api/ordenes/{$this->tareaId}/correccion/cerrar", ['cierre' => 'cancelada'])->assertOk();
        $this->assertNull($this->enMiDia());
    }

    /** Revisión del supervisor encendida: la orden terminada espera su validación (sin puntos todavía). */
    private function terminadaPorValidar(): void
    {
        config(['unificacion.interruptores.validacion_separada' => true]);
        DB::table('tasks')->where('id', $this->tareaId)->update(['validated_at' => null]);
    }

    public function test_el_supervisor_devuelve_la_orden_con_nota_el_tecnico_la_corrige_y_la_valida(): void
    {
        $this->terminadaPorValidar();
        $lista = $this->comoSupervisor()->getJson('/talento/api/ordenes?status=completed')->assertOk();
        $this->assertNotNull(collect($lista->json('data'))->firstWhere('id', $this->tareaId), 'por validar');
        $this->assertGreaterThanOrEqual(1, $lista->json('por_validar'));
        $this->comoSupervisor()->getJson("/talento/api/ordenes/{$this->tareaId}")->assertOk()
            ->assertJsonPath('status', 'completed')->assertJsonPath('puede_devolver', true)->assertJsonPath('puede_pedir_correccion', false);

        // Sin validar no se «pide corrección»: se devuelve.
        $this->pedir()->assertStatus(422);
        $this->comoSupervisor()->postJson("/talento/api/ordenes/{$this->tareaId}/return", ['motivo' => 'No se ve el número de la casa'])
            ->assertOk()->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('correccion.estado', 'pedida')->assertJsonPath('correccion.modo', 'devolucion');

        // El técnico la ve en Mi día, en curso y con la nota; los avisos la marcan devuelta.
        $this->assertSame('in_progress', $this->enMiDia()['status']);
        $this->assertSame('No se ve el número de la casa', $this->enMiDia()['correccion']['motivo']);
        $aviso = collect($this->comoTecnico()->getJson('/talento/api/avisos')->json('correcciones'))->firstWhere('ot_id', $this->tareaId);
        $this->assertTrue($aviso['devuelta']);

        // En curso se edita como cualquier abierta: no se «envía», se vuelve a completar.
        $this->comoTecnico()->postJson("/talento/api/ots/{$this->tareaId}/correccion/enviar")->assertStatus(422)->assertJsonPath('code', 'ES_DEVOLUCION');
        $mediaId = $this->subirFoto($this->tipoFoto)->assertStatus(201)->json('media_id');
        $this->assertNotNull(DB::table('talento_work_order_media')->where('id', $mediaId)->value('correccion_id'));
        $this->comoTecnico()->postJson("/talento/api/ots/{$this->tareaId}/completar", ['nota_tecnico' => 'Tomé la fachada con el número'])->assertOk();

        $tarea = DB::table('tasks')->where('id', $this->tareaId)->first(['status', 'validated_at']);
        $this->assertSame('Done', $tarea->status);
        $this->assertNull($tarea->validated_at, 'vuelve a esperar al supervisor');
        $show = $this->comoSupervisor()->getJson("/talento/api/ordenes/{$this->tareaId}")->assertOk();
        $this->assertSame('enviada', $show->json('correccion.estado'));
        $this->assertSame('Tomé la fachada con el número', $show->json('correccion.nota_tecnico'));

        // Al validarla cuentan sus puntos y la revisión queda cerrada.
        $this->comoSupervisor()->postJson("/talento/api/ordenes/{$this->tareaId}/validate")->assertOk();
        $this->assertNotNull(DB::table('tasks')->where('id', $this->tareaId)->value('validated_at'));
        $this->assertSame('aceptada', DB::table('talento_orden_correcciones')->where('tarea_id', $this->tareaId)->value('cierre'));
        $this->comoSupervisor()->getJson("/talento/api/ordenes/{$this->tareaId}")->assertJsonPath('status', 'validated')
            ->assertJsonPath('correccion', null)->assertJsonPath('puede_pedir_correccion', true);
    }

    public function test_sin_la_revision_del_supervisor_no_se_devuelve(): void
    {
        DB::table('tasks')->where('id', $this->tareaId)->update(['validated_at' => null]);
        config(['unificacion.interruptores.validacion_separada' => false]);

        $this->comoSupervisor()->postJson("/talento/api/ordenes/{$this->tareaId}/return", ['motivo' => 'No se ve el número'])->assertStatus(422);
        $this->assertSame('Done', DB::table('tasks')->where('id', $this->tareaId)->value('status'));
        $this->assertDatabaseMissing('talento_orden_correcciones', ['tarea_id' => $this->tareaId]);
    }

    public function test_lectura_dbm_speedtest_y_foto_despues_aceptan_varias_fotos(): void
    {
        $nombres = ['Lectura dBm', 'Speedtest', 'Foto después', 'Fachada (número visible)'];
        foreach ($nombres as $n) {
            DB::table('talento_evidence_types')->where('name', $n)->exists() || DB::table('talento_evidence_types')->insert([
                'name' => $n, 'permite_varias' => false, 'requiere_justificacion' => false, 'es_lectura_dbm' => false,
                'es_firma' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('talento_evidence_types')->whereIn('name', $nombres)->update(['permite_varias' => false]);

        $migracion = require base_path('app/Modules/Addons/Talento/migrations/2026_11_12_100100_varias_fotos_lectura_dbm_speedtest_foto_despues.php');
        $migracion->up();
        $varias = DB::table('talento_evidence_types')->whereIn('name', $nombres)->pluck('permite_varias', 'name');
        $this->assertEquals(['Lectura dBm' => 1, 'Speedtest' => 1, 'Foto después' => 1, 'Fachada (número visible)' => 0], $varias->all());

        $migracion->down();
        $this->assertSame(0, DB::table('talento_evidence_types')->whereIn('name', $nombres)->where('permite_varias', true)->count());
    }
}
