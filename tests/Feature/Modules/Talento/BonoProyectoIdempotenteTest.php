<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoProject;
use App\Modules\Addons\Talento\Services\ProjectBonusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** UNI-T J2b. Correr SOLO por nombre, APP_ENV=testing (megaisp_test). */
class BonoProyectoIdempotenteTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_segunda_llamada_no_re_acredita_con_interruptor(): void
    {
        $project = TalentoProject::first();
        if (! $project) {
            $this->markTestSkipped('Sin proyectos en la base de pruebas.');
        }
        $col = (int) DB::table('talento_colaboradores')->value('id');
        TalentoLedgerEntry::create([
            'colaborador_id' => $col, 'type' => 'credit', 'concept' => 'bonus', 'amount' => 10,
            'reference_type' => TalentoProject::class, 'reference_id' => $project->id,
            'period_start' => '2026-10-24', 'period_end' => '2026-10-31',
        ]);
        $antes = TalentoLedgerEntry::where('reference_type', TalentoProject::class)->where('reference_id', $project->id)->count();

        config(['unificacion.interruptores.bono_proyecto_idempotente' => true]);
        $r = app(ProjectBonusService::class)->award($project->id);

        $this->assertSame(0, $r['entries_written']);
        $this->assertTrue($r['already_awarded']);
        $this->assertSame($antes, TalentoLedgerEntry::where('reference_type', TalentoProject::class)->where('reference_id', $project->id)->count());
    }
}
