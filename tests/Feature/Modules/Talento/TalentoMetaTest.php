<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Models\TalentoMeta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/** UNI-T M2a. Correr SOLO por nombre, APP_ENV=testing (megaisp_test). */
class TalentoMetaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_guarda_lee_y_versiona(): void
    {
        $this->assertTrue(Schema::hasTable('talento_metas'));
        $a = TalentoMeta::create(['concepto' => 'cuota_semanal', 'valor' => 6, 'vigente_desde' => '2026-10-01']);
        $b = TalentoMeta::create(['concepto' => 'cuota_semanal', 'valor' => 8, 'version' => 2,
            'reemplaza_id' => $a->id, 'vigente_desde' => '2026-11-01']);
        $this->assertEquals($a->id, TalentoMeta::find($b->id)->reemplaza->id);
        $this->assertFalse(TalentoMeta::find($a->id)->afecta_pago);
        $this->assertFalse((bool) config('unificacion.metas_m2'));
    }
}
