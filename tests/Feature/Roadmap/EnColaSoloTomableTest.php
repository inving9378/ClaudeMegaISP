<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** #1122 — «en cola» solo para lo que una terminal puede tomar; lo demás dice por qué espera. */
class EnColaSoloTomableTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function item(array $extra = []): RoadmapItem
    {
        return RoadmapItem::create($extra + [
            'title'             => 'Item prueba 1122 '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'pending',
        ]);
    }

    public function test_tomable_es_en_cola(): void
    {
        $i = $this->item();
        $this->assertSame('en_cola', $i->estado_cola);
        $this->assertNull($i->motivoEsperaCola());
    }

    public function test_apartado_del_pool_espera(): void
    {
        $i = $this->item(['excluir_pool_automatico' => true, 'motivo_bloqueo' => 'vueltas-vacias']);
        $this->assertSame('en_espera', $i->estado_cola);
        $this->assertStringStartsWith('apartado:', $i->motivoEsperaCola());
    }

    public function test_anti_bucle_espera(): void
    {
        $i = $this->item(['bloqueado_por_bucle' => true, 'excluir_pool_automatico' => true]);
        $this->assertSame('en_espera', $i->estado_cola);
        $this->assertSame('anti-bucle: falta tu decisión', $i->motivoEsperaCola());
    }

    public function test_espera_a_otro_item(): void
    {
        $dep = $this->item(['estado_aprobacion' => 'aprobado_irving']);
        $i   = $this->item(['depende_de' => [$dep->id]]);
        $this->assertSame('en_espera', $i->estado_cola);
        $this->assertSame('espera a #'.$dep->id, $i->motivoEsperaCola());
    }

    public function test_paraguas_espera(): void
    {
        $padre = $this->item();
        $this->item(['origen_item_id' => $padre->id]);
        $this->assertSame('en_espera', $padre->fresh()->estado_cola);
        $this->assertSame('paraguas', $padre->fresh()->motivoEsperaCola());
    }
}
