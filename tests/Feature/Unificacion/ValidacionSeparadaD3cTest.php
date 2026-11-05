<?php

namespace Tests\Feature\Unificacion;

use App\Models\Task;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use App\Modules\Addons\Talento\Support\PayWeek;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * UNI-T D3c (#1033): punta a punta de la validación separada. crear -> iniciar -> completar (0 unidades)
 * -> validar (unidades en la semana de la validación). Solo megaisp_test; todo se revierte.
 */
class ValidacionSeparadaD3cTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName(), 'Solo en megaisp_test');
    }

    private function unidades(int $colabId): float
    {
        return (float) app(LiquidationService::class)->countBillableUnits($colabId, PayWeek::boundsFor(now()))['total'];
    }

    public function test_ciclo_completo_con_validacion_separada(): void
    {
        config(['unificacion.interruptores.validacion_separada' => true]);
        $svc = app(OrdenTrabajoUnifiedService::class);

        $userId = DB::table('users')->insertGetId([
            'name' => 'UNI-PRUEBA', 'father_last_name' => 'D3c', 'email' => uniqid('d3c') . '@example.test',
            'login_user' => uniqid('d3c'), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $colabId = DB::table('talento_colaboradores')->insertGetId([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $typeId = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'UNI-PRUEBA D3c', 'category' => 'interna', 'points' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $task = new Task();
        $task->forceFill([
            'title' => 'UNI-PRUEBA D3c ' . uniqid(), 'status' => 'ToDo', 'tipo' => 'interna',
            'talento_type_id' => $typeId, 'points' => 3, 'is_billable' => 1,
        ])->save();
        $task->users()->attach($userId);

        $antes = $this->unidades($colabId);

        // Validar una orden aún no completada se rechaza.
        $task->forceFill(['status' => 'InProgress'])->save();
        $rechazo = $svc->validarAdmin($task->id);
        $this->assertFalse($rechazo['success']);
        $this->assertNull($task->fresh()->validated_at);

        // Completar: Done sin validar, 0 unidades nuevas.
        $res = $svc->completar($task->id, $colabId, []);
        $this->assertTrue($res['success'], json_encode($res));
        $this->assertSame('Done', $task->fresh()->status);
        $this->assertNull($task->fresh()->validated_at);
        $this->assertEquals($antes, $this->unidades($colabId));

        // Validar: ya suma, dentro de la semana de la validación.
        $val = $svc->validarAdmin($task->id);
        $this->assertTrue($val['success'], json_encode($val));
        $fresh = $task->fresh();
        $this->assertNotNull($fresh->validated_at);
        $this->assertTrue(PayWeek::boundsFor(now())['start_instant']->lte($fresh->validated_at));
        $this->assertGreaterThan($antes, $this->unidades($colabId));

        // Una vez validada no se re-valida.
        $this->assertFalse($svc->validarAdmin($task->id)['success']);
    }

    public function test_devolver_regresa_a_en_curso_con_motivo(): void
    {
        config(['unificacion.interruptores.validacion_separada' => true]);
        $svc = app(OrdenTrabajoUnifiedService::class);
        $typeId = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'UNI-PRUEBA D3c-b', 'category' => 'interna', 'points' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $task = new Task();
        $task->forceFill(['title' => 'UNI-PRUEBA D3c-b ' . uniqid(), 'status' => 'Done', 'tipo' => 'interna', 'talento_type_id' => $typeId])->save();

        $this->assertFalse($svc->devolver($task->id, '  ')['success']);
        $this->assertTrue($svc->devolver($task->id, 'Falta evidencia')['success']);
        $this->assertSame('InProgress', $task->fresh()->status);
    }

    public function test_interruptor_apagado_conserva_comportamiento_actual(): void
    {
        config(['unificacion.interruptores.validacion_separada' => false]);
        $svc = app(OrdenTrabajoUnifiedService::class);
        $this->assertFalse($svc->devolver(1, 'x')['success']);
    }
}
