<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** UNI-T J1. Sin RefreshDatabase (no migrate:fresh): DatabaseTransactions con rollback. Correr SOLO por nombre, APP_ENV=testing (megaisp_test). */
class LedgerIdentidadUnicaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    /** Colaborador propio de la prueba (se deshace con el rollback); megaisp_test no trae datos sembrados. */
    private function colaborador(): int
    {
        $existente = DB::table('talento_colaboradores')->value('id');
        if ($existente) {
            return (int) $existente;
        }

        return (int) DB::table('talento_colaboradores')->insertGetId([
            'user_id' => (int) DB::table('users')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function attrs(int $col, array $o = []): array
    {
        return array_merge([
            'colaborador_id' => $col, 'type' => 'credit', 'concept' => 'bonus', 'amount' => 10,
            'reference_type' => 'X\\Orden', 'reference_id' => 77,
            'period_start' => '2026-10-24', 'period_end' => '2026-10-31',
        ], $o);
    }

    public function test_reintento_no_duplica_y_distintos_si_coexisten(): void
    {
        config(['unificacion.interruptores.ledger_identidad_unica' => true]);
        DB::beginTransaction();
        try {
            $col = $this->colaborador();

            $a = TalentoLedgerEntry::registrar($this->attrs($col));
            $b = TalentoLedgerEntry::registrar($this->attrs($col));
            $this->assertSame($a->id, $b->id);

            TalentoLedgerEntry::registrar($this->attrs($col, ['reference_id' => 78]));
            TalentoLedgerEntry::registrar($this->attrs($col, ['period_start' => '2026-10-31', 'period_end' => '2026-11-07']));
            $this->assertSame(3, TalentoLedgerEntry::where('colaborador_id', $col)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_interruptor_apagado_conserva_comportamiento_actual(): void
    {
        config(['unificacion.interruptores.ledger_identidad_unica' => false]);
        DB::beginTransaction();
        try {
            $col = $this->colaborador();
            TalentoLedgerEntry::registrar($this->attrs($col));
            TalentoLedgerEntry::registrar($this->attrs($col));
            $this->assertSame(2, TalentoLedgerEntry::where('colaborador_id', $col)->count());
        } finally {
            DB::rollBack();
        }
    }
}
