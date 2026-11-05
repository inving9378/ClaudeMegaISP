<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Models\TalentoActivityReportParticipant;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderSignature;
use Database\Seeders\UniPruebaSeeder;
use Tests\TestCase;

class UniPruebaOrdenesTest extends TestCase
{
    public function test_siembra_ordenes_evidencias_firmas_y_participantes(): void
    {
        $seeder = new UniPruebaSeeder();
        $colabs = $seeder->perfiles();
        $r = $seeder->ordenes($colabs);

        $this->assertSame(4, TalentoWorkOrder::where('notes', 'like', 'UNI-PRUEBA %')->count());
        $this->assertSame(['pending', 'in_progress', 'completed', 'validated'],
            [$r['pendiente']->status, $r['curso']->status, $r['completada']->status, $r['validada']->status]);
        $this->assertSame(2, TalentoWorkOrderMedia::where('work_order_id', $r['validada']->id)->count());
        $this->assertSame(2, TalentoWorkOrderSignature::where('work_order_id', $r['validada']->id)->count());
        $this->assertSame(2, TalentoActivityReportParticipant::where('report_id', $r['reporte']->id)->count());

        // Idempotente
        $seeder->ordenes($seeder->perfiles());
        $this->assertSame(4, TalentoWorkOrder::where('notes', 'like', 'UNI-PRUEBA %')->count());
        $this->assertSame(2, TalentoActivityReportParticipant::where('report_id', $r['reporte']->id)->count());
    }
}
