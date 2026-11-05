<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Models\TalentoHealthBonusLog;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Services\HealthBonusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** UNI-T J2a. Correr SOLO por nombre, APP_ENV=testing (megaisp_test). */
class BonoSaludIdempotenteTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function llamar(int $col): ?TalentoHealthBonusLog
    {
        $m = new \ReflectionMethod(HealthBonusService::class, 'bonoYaRegistrado');
        $m->setAccessible(true);

        return $m->invoke(app(HealthBonusService::class), 'X\\Orden', 4242, $col, 'work_order_id');
    }

    private function preparar(): int
    {
        $col = (int) (DB::table('talento_colaboradores')->value('id') ?: DB::table('talento_colaboradores')->insertGetId([
            'user_id' => (int) DB::table('users')->value('id'), 'created_at' => now(), 'updated_at' => now(),
        ]));
        TalentoLedgerEntry::create([
            'colaborador_id' => $col, 'type' => 'credit', 'concept' => 'bonus', 'amount' => 30,
            'reference_type' => 'X\\Orden', 'reference_id' => 4242,
            'period_start' => '2026-10-24', 'period_end' => '2026-10-31',
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=0'); // el log apunta a una orden sintética
        TalentoHealthBonusLog::unguarded(fn () => TalentoHealthBonusLog::create([
            'work_order_id' => 4242, 'colaborador_id' => $col, 'max_loss_db' => 1, 'bonus_awarded' => true, 'bonus_amount' => 30,
        ]));

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return $col;
    }

    public function test_con_interruptor_encendido_devuelve_el_log_existente(): void
    {
        config(['unificacion.interruptores.bono_salud_idempotente' => true]);
        $col = $this->preparar();
        $log = $this->llamar($col);
        $this->assertNotNull($log);
        $this->assertTrue($log->ya_existia);
        $this->assertSame(1, TalentoLedgerEntry::where('reference_id', 4242)->count());
    }

    public function test_apagado_no_intercepta(): void
    {
        config(['unificacion.interruptores.bono_salud_idempotente' => false]);
        $col = $this->preparar();
        $this->assertNull($this->llamar($col));
    }
}
