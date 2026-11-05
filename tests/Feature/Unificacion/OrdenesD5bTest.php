<?php

namespace Tests\Feature\Unificacion;

use App\Models\Task;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * UNI-T D5b (#919): cierre y cambios de administración detrás de `ordenes_d5b`. Solo megaisp_test.
 */
class OrdenesD5bTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName(), 'Solo en megaisp_test');
    }

    private function tarea(string $status = 'InProgress'): Task
    {
        $typeId = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'UNI-PRUEBA D5b', 'category' => 'interna', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $evId = DB::table('talento_evidence_types')->insertGetId(['name' => 'UNI-PRUEBA fachada', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('talento_ot_type_evidence_requirements')->insert([
            'ot_type_id' => $typeId, 'evidence_type_id' => $evId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $t = new Task();
        $t->forceFill(['title' => 'UNI-PRUEBA D5b ' . uniqid(), 'status' => $status, 'tipo' => 'interna', 'talento_type_id' => $typeId])->save();

        return $t;
    }

    private function svc(): OrdenTrabajoUnifiedService
    {
        return app(OrdenTrabajoUnifiedService::class);
    }

    public function test_on_cierre_admin_sin_evidencias_se_rechaza(): void
    {
        config(['unificacion.interruptores.ordenes_d5b' => true]);
        $t = $this->tarea();
        $r = $this->svc()->cambiarEstadoAdmin($t->id, 'completed');
        $this->assertFalse($r['success']);
        $this->assertSame('InProgress', $t->fresh()->status);
    }

    public function test_off_cierre_admin_como_antes(): void
    {
        config(['unificacion.interruptores.ordenes_d5b' => false]);
        $t = $this->tarea();
        $this->assertTrue($this->svc()->cambiarEstadoAdmin($t->id, 'completed')['success']);
        $this->assertSame('Done', $t->fresh()->status);
    }

    public function test_on_validada_no_cambia_tipo_ni_responsable(): void
    {
        config(['unificacion.interruptores.ordenes_d5b' => true]);
        $t = $this->tarea('Done');
        DB::table('tasks')->where('id', $t->id)->update(['validated_at' => now()]);
        $r = $this->svc()->actualizarAdmin($t->id, ['type_id' => $t->talento_type_id]);
        $this->assertFalse($r['success']);
        $this->assertSame(422, $r['status_code']);
    }

    public function test_on_cancelar_archiva_la_tarea(): void
    {
        config(['unificacion.interruptores.ordenes_d5b' => true]);
        $t = $this->tarea('ToDo');
        $this->assertTrue($this->svc()->cambiarEstadoAdmin($t->id, 'cancelled')['success']);
        $f = $t->fresh();
        $this->assertSame('Archivado', $f->status);
        $this->assertEquals(1, $f->archived);
        $this->assertNotNull($f->archived_at);
    }
}
