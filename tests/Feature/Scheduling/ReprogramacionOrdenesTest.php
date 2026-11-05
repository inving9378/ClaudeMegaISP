<?php

namespace Tests\Feature\Scheduling;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #697: una orden reprogramada muestra la misma fecha/hora en calendario, Mi día y Órdenes de Talento,
 * y solo la mueven asignados / admins / task_view_full_task. Todo dentro de una transacción que se revierte.
 */
class ReprogramacionOrdenesTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuario(?string $rol, array $permisos = []): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => $rol ?? 'sin rol', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        $rol && $user->assignRole($rol);
        $permisos && $user->givePermissionTo($permisos);

        return $user;
    }

    private function colaborador(User $tecnico): TalentoColaborador
    {
        return TalentoColaborador::create(['user_id' => $tecnico->id, 'type' => 'interno', 'status' => 'active']);
    }

    private function evento(User $quien, int $taskId): ?array
    {
        $r = $this->actingAs($quien)->postJson('/fullcalendar/get-task-events', [])->assertOk()->json();

        return collect($r)->firstWhere('id', $taskId);
    }

    private function mover(User $quien, int $taskId, string $ini, string $fin)
    {
        return $this->actingAs($quien)->postJson('/scheduling/task/update-task-to-calendar', ['data' => ['id' => $taskId, 'start' => $ini, 'end' => $fin]]);
    }

    public function test_misma_fecha_y_hora_en_todas_las_vistas_y_log(): void
    {
        $admin = $this->usuario('super-administrator', ['talento.work_orders.manage', 'talento.work_orders.view']);
        $tecnico = $this->usuario(null);
        $col = $this->colaborador($tecnico);
        $hoy = now()->addDays(1)->setTime(10, 0);

        $crear = $this->actingAs($admin)->postJson('/talento/api/ordenes', [
            'colaborador_id' => $col->id, 'type_id' => 2, 'scheduled_at' => $hoy->format('Y-m-d H:i:s'), 'notes' => 'prueba 697',
        ]);
        $this->assertTrue($crear->status() < 300, $crear->status() . $crear->getContent());
        $task = Task::where('description', 'prueba 697')->latest('id')->firstOrFail();

        $this->assertSame($hoy->toDateString(), $task->start_date);
        $this->assertSame($hoy->format('Y-m-d H:i:s'), $task->start_time);
        $this->assertSame($hoy->format('Y-m-d\TH:i:s'), $this->evento($admin, $task->id)['start']);
        $this->assertSame($hoy->format('Y-m-d H:i:s'), $this->admin($admin, $task->id));
        $this->assertContains($task->id, collect(app(OrdenTrabajoUnifiedService::class)->historial($col->id, null, $hoy->toDateString())['data'])->pluck('id')->all());

        // Arrastre en el calendario: otro día y otra hora
        $nuevo = now()->addDays(3)->setTime(15, 30);
        $this->mover($admin, $task->id, $nuevo->format('Y-m-d\TH:i:s'), $nuevo->copy()->addHour()->format('Y-m-d\TH:i:s'))->assertOk();
        $this->verificarVistas($admin, $col, $task->id, $hoy, $nuevo);

        // Reprogramar desde la edición de la orden
        $otro = now()->addDays(5)->setTime(9, 15);
        $this->actingAs($admin)->putJson("/talento/api/ordenes/{$task->id}", ['scheduled_at' => $otro->format('Y-m-d H:i:s')])->assertSuccessful();
        $this->verificarVistas($admin, $col, $task->id, $nuevo, $otro);

        // activity_log: antes y después
        $log = DB::table('activity_log')->where('description', 'like', 'Reprogramada desde el calendario #' . $task->id . ':%')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($hoy->format('Y-m-d H:i:s'), $log->description);
        $this->assertStringContainsString($nuevo->format('Y-m-d H:i:s'), $log->description);
    }

    private function admin(User $admin, int $id): string
    {
        $d = $this->actingAs($admin)->getJson("/talento/api/ordenes/{$id}")->assertOk()->json();

        return substr(str_replace('T', ' ', $d['scheduled_at']), 0, 19);
    }

    private function verificarVistas(User $admin, TalentoColaborador $col, int $id, $anterior, $esperado): void
    {
        $this->assertSame($esperado->format('Y-m-d\TH:i:s'), $this->evento($admin, $id)['start'], 'calendario');
        $this->assertSame($esperado->format('Y-m-d H:i:s'), $this->admin($admin, $id), 'órdenes de Talento');
        $svc = app(OrdenTrabajoUnifiedService::class);
        $this->assertContains($id, collect($svc->historial($col->id, null, $esperado->toDateString())['data'])->pluck('id')->all(), 'Mi día/historial en el día nuevo');
        $this->assertNotContains($id, collect($svc->historial($col->id, null, $anterior->toDateString())['data'])->pluck('id')->all(), 'ya no en el día viejo');
    }

    public function test_orden_con_formato_viejo_solo_hora_se_lee_con_su_dia(): void
    {
        $admin = $this->usuario('super-administrator', ['talento.work_orders.manage', 'talento.work_orders.view']);
        $tecnico = $this->usuario(null);
        $col = $this->colaborador($tecnico);
        $dia = now()->addDays(2)->toDateString();
        $task = Task::create([
            'tipo' => 'campo', 'talento_type_id' => 2, 'project_id' => 5, 'start_date' => $dia, 'start_time' => '11:00:00',
            'end_time' => null, 'title' => 'viejo 697', 'status' => 'ToDo', 'priority' => 'Media',
        ]);
        $task->users()->sync([$tecnico->id]);

        $this->assertSame("$dia 11:00:00", $this->admin($admin, $task->id));

        $nuevo = now()->addDays(4)->setTime(16, 0);
        $this->mover($admin, $task->id, $nuevo->format('Y-m-d\TH:i:s'), $nuevo->copy()->addHour()->format('Y-m-d\TH:i:s'))->assertOk();
        $this->assertSame($nuevo->format('Y-m-d H:i:s'), $this->admin($admin, $task->id));
        $this->assertSame($nuevo->toDateString(), $task->fresh()->start_date);
    }

    public function test_nadie_mueve_ordenes_que_no_ve(): void
    {
        $tecnico = $this->usuario(null, ['scheduling_view_calendar']);
        $ajeno = $this->usuario(null, ['scheduling_view_calendar']);
        $amplio = $this->usuario(null, ['scheduling_view_calendar', 'task_view_full_task']);
        $task = Task::create([
            'tipo' => 'campo', 'talento_type_id' => 2, 'project_id' => 5, 'start_date' => now()->toDateString(),
            'start_time' => now()->format('Y-m-d 10:00:00'), 'title' => 'alcance 697', 'status' => 'ToDo', 'priority' => 'Media',
        ]);
        $task->users()->sync([$tecnico->id]);
        $f = now()->addDay()->setTime(8, 0);
        $ini = $f->format('Y-m-d\TH:i:s');
        $fin = $f->copy()->addHour()->format('Y-m-d\TH:i:s');

        $this->mover($ajeno, $task->id, $ini, $fin)->assertForbidden();
        $this->assertNotSame($f->toDateString(), $task->fresh()->start_date);
        $this->mover($tecnico, $task->id, $ini, $fin)->assertOk();
        $this->mover($amplio, $task->id, $ini, $fin)->assertOk();
    }
}
