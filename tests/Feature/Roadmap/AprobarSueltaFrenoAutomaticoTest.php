<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** #1121 — «Aprobar» ofrece quitar el freno cuando lo puso el sistema; paraguas y esperando_merge no. */
class AprobarSueltaFrenoAutomaticoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function item(array $extra = []): RoadmapItem
    {
        return RoadmapItem::create($extra + [
            'title'             => 'Item prueba 1121 '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'pending',
        ]);
    }

    public function test_apartado_por_cerrar_vuelta_es_desbloqueable(): void
    {
        $i = $this->item(['excluir_pool_automatico' => true, 'motivo_bloqueo' => 'vueltas-vacias']);
        $m = $i->motivoNoDespachable();
        $this->assertSame('fuera_del_pool', $m['code']);
        $this->assertTrue($m['desbloqueable']);
    }

    public function test_bloqueado_por_bucle_es_desbloqueable(): void
    {
        $i = $this->item(['bloqueado_por_bucle' => true, 'excluir_pool_automatico' => true]);
        $m = $i->motivoNoDespachable();
        $this->assertSame('bloqueado_por_bucle', $m['code']);
        $this->assertTrue($m['desbloqueable']);
    }

    public function test_esperando_merge_no_es_desbloqueable(): void
    {
        $i = $this->item(['esperando_merge_irving' => true]);
        $m = $i->motivoNoDespachable();
        $this->assertSame('esperando_merge', $m['code']);
        $this->assertFalse($m['desbloqueable'] ?? false);
    }

    public function test_paraguas_con_hijos_abiertos_no_es_desbloqueable(): void
    {
        $p = $this->item(['excluir_pool_automatico' => true]);
        $this->item(['origen_item_id' => $p->id, 'estado_aprobacion' => 'aprobado_irving']);
        $m = $p->fresh()->motivoNoDespachable();
        $this->assertSame('fuera_del_pool', $m['code']);
        $this->assertFalse($m['desbloqueable']);
    }

    public function test_forzar_devuelve_al_pool(): void
    {
        $i = $this->item(['excluir_pool_automatico' => true, 'bloqueado_por_bucle' => true]);
        $i->excluir_pool_automatico = false;
        $i->bloqueado_por_bucle = false;
        $i->save();
        $this->assertNull($i->fresh()->motivoNoDespachable());
    }
}
