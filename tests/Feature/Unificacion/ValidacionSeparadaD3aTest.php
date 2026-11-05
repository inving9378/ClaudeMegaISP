<?php

namespace Tests\Feature\Unificacion;

use App\Models\Task;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * UNI-T D3a (#1031): con `validacion_separada` encendido, completar una orden-tarea la deja Done SIN validated_at
 * (0 unidades pagables); apagado = Done + validated_at como siempre. Solo megaisp_test; todo se revierte.
 */
class ValidacionSeparadaD3aTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName(), 'Solo en megaisp_test');
    }

    /** @return array{0:int,1:Task} */
    private function orden(): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'UNI-PRUEBA', 'father_last_name' => 'D3a', 'email' => uniqid('d3a') . '@example.test',
            'login_user' => uniqid('d3a'), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $colabId = DB::table('talento_colaboradores')->insertGetId([
            'user_id' => $userId, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $typeId = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'UNI-PRUEBA D3a', 'category' => 'interna', 'points' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $task = new Task();
        $task->forceFill([
            'title' => 'UNI-PRUEBA D3a ' . uniqid(), 'status' => 'InProgress', 'tipo' => 'interna', 'talento_type_id' => $typeId,
        ])->save();
        $task->users()->attach($userId);

        return [$colabId, $task];
    }

    public function test_interruptor_on_completa_sin_validated_at_y_cero_unidades(): void
    {
        config(['unificacion.interruptores.validacion_separada' => true]);
        [$colabId, $task] = $this->orden();

        $res = app(OrdenTrabajoUnifiedService::class)->completar($task->id, $colabId, []);

        $this->assertTrue($res['success'], json_encode($res));
        $fresh = $task->fresh();
        $this->assertSame('Done', $fresh->status);
        $this->assertNull($fresh->validated_at);

        $window = \App\Modules\Addons\Talento\Support\PayWeek::boundsFor(now());
        $units = app(LiquidationService::class)->countBillableUnits($colabId, $window);
        $this->assertEquals(0, $units['total']);
    }

    public function test_interruptor_off_conserva_validated_at(): void
    {
        config(['unificacion.interruptores.validacion_separada' => false]);
        [$colabId, $task] = $this->orden();

        $res = app(OrdenTrabajoUnifiedService::class)->completar($task->id, $colabId, []);

        $this->assertTrue($res['success'], json_encode($res));
        $this->assertSame('Done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->validated_at);
    }
}
