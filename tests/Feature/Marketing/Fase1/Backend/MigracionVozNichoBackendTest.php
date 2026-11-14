<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/** Hallazgo de QA: `marketing_niches.preferred_voice_model` no tenía migración en el repo. */
class MigracionVozNichoBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    private const MIGRACION = 'app/Modules/Addons/Marketing/migrations/2026_11_13_100100_agrega_preferred_voice_model_a_marketing_niches.php';

    public function test_la_columna_existe_con_la_definicion_de_dev(): void
    {
        $this->assertTrue(Schema::hasColumn('marketing_niches', 'preferred_voice_model'));

        $col = collect(DB::select("SHOW COLUMNS FROM marketing_niches LIKE 'preferred_voice_model'"))->first();
        $this->assertSame('varchar(30)', $col->Type);
        $this->assertSame('NO', $col->Null);
        $this->assertSame('tts-1', $col->Default);
    }

    public function test_up_con_la_columna_ya_puesta_no_hace_nada_y_down_no_la_borra(): void
    {
        $migracion = require base_path(self::MIGRACION);
        $migracion->up();
        $migracion->up();
        $migracion->down();

        $this->assertTrue(Schema::hasColumn('marketing_niches', 'preferred_voice_model'));
    }

    public function test_el_comparador_de_voces_ya_no_da_500(): void
    {
        $admin = $this->adminMeganet();
        $this->darPermisosDeModulo($admin, ['test-voices']);

        $this->actingAs($admin)->getJson('/api/marketing/voice-comparator/voices')->assertOk()->assertJsonStructure(['niches']);
    }
}
