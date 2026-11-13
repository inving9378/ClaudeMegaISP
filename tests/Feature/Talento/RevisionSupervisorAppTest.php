<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Revisión del supervisor en la app (13-nov-2026, sobre #1286): el supervisor ve las órdenes
 * terminadas de su equipo, abre su detalle con las fotos y las valida o devuelve con una nota.
 * Nunca las suyas ni las de otro equipo. Datos sintéticos; correr SOLO por nombre (megaisp_test).
 */
class RevisionSupervisorAppTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $supervisorId;

    private int $tecnicoId;

    private int $tipoOrden;

    private int $tipoFoto;

    /** Terminada por el técnico del equipo, esperando la revisión. */
    private int $ordenEquipo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        foreach (['talento.work_orders.validate', 'talento.work_orders.manage', 'talento.employees.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['unificacion.interruptores.validacion_separada' => true]);

        $this->tipoFoto = DB::table('talento_evidence_types')->insertGetId([
            'name' => 'Equipo sintético ' . Str::random(6), 'es_firma' => false, 'permite_varias' => false,
            'requiere_justificacion' => false, 'es_lectura_dbm' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tipoOrden = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'Orden sintética ' . Str::random(6), 'category' => 'campo', 'points' => 9,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->supervisorId = $this->usuario(['talento.work_orders.validate']);
        $colSupervisor = $this->colaborador($this->supervisorId);
        $this->tecnicoId = $this->usuario();
        $this->colaborador($this->tecnicoId, $colSupervisor);

        $this->ordenEquipo = $this->orden($this->tecnicoId);
        DB::table('talento_work_order_media')->insert([
            'tarea_id' => $this->ordenEquipo, 'evidence_type_id' => $this->tipoFoto, 'type' => 'completion',
            'file_path' => 'sintetico/equipo.jpg', 'created_at' => now(),
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

    private function colaborador(int $userId, ?int $supervisorId = null): int
    {
        return DB::table('talento_colaboradores')->insertGetId([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active', 'supervisor_id' => $supervisorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function orden(int $userId, string $status = 'Done'): int
    {
        $id = DB::table('tasks')->insertGetId([
            'title' => 'Orden de prueba', 'status' => $status, 'tipo' => 'campo', 'talento_type_id' => $this->tipoOrden,
            'points' => 9, 'start_date' => now()->toDateString(), 'finish_at' => $status === 'Done' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $id, 'user_id' => $userId]);

        return $id;
    }

    private function como(int $userId): static
    {
        Sanctum::actingAs(User::find($userId));

        return $this;
    }

    private function idsPorValidar(int $userId): array
    {
        return collect($this->como($userId)->getJson('/talento/api/revision/ordenes')->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_el_supervisor_ve_las_de_su_equipo_y_no_las_suyas_ni_las_de_otro_equipo(): void
    {
        $propia = $this->orden($this->supervisorId);
        $ajenoId = $this->usuario();
        $this->colaborador($ajenoId);
        $deOtroEquipo = $this->orden($ajenoId);
        $sinTerminar = $this->orden($this->tecnicoId, 'InProgress');

        $ids = $this->idsPorValidar($this->supervisorId);
        $this->assertSame([$this->ordenEquipo], $ids);
        $this->assertNotContains($propia, $ids);
        $this->assertNotContains($deOtroEquipo, $ids);
        $this->assertNotContains($sinTerminar, $ids);

        $this->como($this->supervisorId)->getJson('/talento/api/me')->assertOk()->assertJsonPath('puede_validar_ordenes', true);
        $this->como($this->supervisorId)->getJson("/talento/api/revision/ordenes/{$deOtroEquipo}")->assertNotFound();
        $this->como($this->supervisorId)->postJson("/talento/api/revision/ordenes/{$deOtroEquipo}/validar")->assertNotFound();
        $this->assertNull(DB::table('tasks')->where('id', $deOtroEquipo)->value('validated_at'));
    }

    public function test_el_detalle_trae_las_fotos_y_quien_la_hizo(): void
    {
        $ot = $this->como($this->supervisorId)->getJson("/talento/api/revision/ordenes/{$this->ordenEquipo}")->assertOk()->json('ot');

        $this->assertSame('completed', $ot['status']);
        $this->assertTrue($ot['puede_validar']);
        $this->assertTrue($ot['puede_devolver']);
        $this->assertStringStartsWith('Sintético', $ot['tecnico']);
        $this->assertCount(1, $ot['evidencias']);
        $this->assertNotEmpty($ot['evidencias'][0]['foto_url'], 'foto firmada para verla en el teléfono');
    }

    public function test_validar_cuenta_la_orden_y_sale_de_la_lista(): void
    {
        $this->como($this->supervisorId)->postJson("/talento/api/revision/ordenes/{$this->ordenEquipo}/validar")
            ->assertOk()->assertJsonPath('ot.status', 'validated')->assertJsonPath('ot.puede_validar', false);

        $this->assertNotNull(DB::table('tasks')->where('id', $this->ordenEquipo)->value('validated_at'));
        $this->assertSame([], $this->idsPorValidar($this->supervisorId));
        $this->como($this->supervisorId)->postJson("/talento/api/revision/ordenes/{$this->ordenEquipo}/validar")->assertNotFound();
    }

    public function test_devolver_con_nota_y_al_corregirla_vuelve_a_avisar(): void
    {
        $aviso = collect($this->como($this->supervisorId)->getJson('/talento/api/avisos')->assertOk()->json('por_validar'))
            ->firstWhere('ot_id', $this->ordenEquipo);
        $this->assertSame((string) $this->ordenEquipo, (string) $aviso['id']);
        $this->assertFalse($aviso['corregida']);

        $this->como($this->supervisorId)->postJson("/talento/api/revision/ordenes/{$this->ordenEquipo}/devolver", ['motivo' => 'no'])
            ->assertStatus(422);
        $this->como($this->supervisorId)->postJson("/talento/api/revision/ordenes/{$this->ordenEquipo}/devolver", ['motivo' => 'No se ve el número de la casa'])
            ->assertOk();

        $this->assertSame('InProgress', DB::table('tasks')->where('id', $this->ordenEquipo)->value('status'));
        $this->assertSame([], $this->idsPorValidar($this->supervisorId));
        $mio = collect($this->como($this->tecnicoId)->getJson('/talento/api/ots/hoy')->assertOk()->json('ots'))->firstWhere('id', $this->ordenEquipo);
        $this->assertSame('No se ve el número de la casa', $mio['correccion']['motivo']);

        $this->como($this->tecnicoId)->postJson("/talento/api/ots/{$this->ordenEquipo}/completar", ['nota_tecnico' => 'Ya se ve'])->assertOk();

        $item = collect($this->como($this->supervisorId)->getJson('/talento/api/revision/ordenes')->json('data'))->firstWhere('id', $this->ordenEquipo);
        $this->assertTrue($item['corregida']);
        $aviso = collect($this->como($this->supervisorId)->getJson('/talento/api/avisos')->json('por_validar'))
            ->firstWhere('ot_id', $this->ordenEquipo);
        $this->assertNotSame((string) $this->ordenEquipo, (string) $aviso['id'], 'corregida: otro id para que el teléfono vuelva a avisar');
        $this->assertTrue($aviso['corregida']);
    }

    public function test_sin_permiso_o_con_la_revision_apagada_no_se_usa(): void
    {
        $this->como($this->tecnicoId)->getJson('/talento/api/revision/ordenes')->assertForbidden();
        $this->como($this->tecnicoId)->postJson("/talento/api/revision/ordenes/{$this->ordenEquipo}/validar")->assertForbidden();
        $this->como($this->tecnicoId)->getJson('/talento/api/me')->assertOk()->assertJsonPath('puede_validar_ordenes', false);
        $this->assertSame([], $this->como($this->tecnicoId)->getJson('/talento/api/avisos')->json('por_validar'));

        config(['unificacion.interruptores.validacion_separada' => false]);
        $this->como($this->supervisorId)->getJson('/talento/api/revision/ordenes')->assertStatus(422);
        $this->como($this->supervisorId)->getJson('/talento/api/me')->assertJsonPath('puede_validar_ordenes', false);
        $this->assertNull(DB::table('tasks')->where('id', $this->ordenEquipo)->value('validated_at'));
    }

    public function test_quien_gestiona_ordenes_ve_las_de_todos_menos_las_suyas(): void
    {
        $adminId = $this->usuario(['talento.work_orders.validate', 'talento.work_orders.manage']);
        $this->colaborador($adminId);
        $propia = $this->orden($adminId);
        $ajenoId = $this->usuario();
        $this->colaborador($ajenoId);
        $deOtroEquipo = $this->orden($ajenoId);

        $ids = $this->idsPorValidar($adminId);
        $this->assertContains($this->ordenEquipo, $ids);
        $this->assertContains($deOtroEquipo, $ids);
        $this->assertNotContains($propia, $ids);
    }
}
