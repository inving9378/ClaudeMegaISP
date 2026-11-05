<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Services\WarrantyWindowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/** UNI-T L1 (#927): un ticket dentro de la ventana queda ligado a la orden original; fuera, no. */
class GarantiaEventoL1Test extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function ventana(int $clientId, string $inicio, string $fin): int
    {
        Schema::disableForeignKeyConstraints();
        return DB::table('talento_responsibility_windows')->insertGetId([
            'colaborador_id' => 999001, 'client_id' => $clientId, 'caja_id' => null,
            'source_work_order_id' => null, 'tarea_id' => 888001,
            'starts_at' => $inicio, 'expires_at' => $fin, 'active' => true,
        ]);
    }

    public function test_apagado_no_registra(): void
    {
        config(['unificacion.interruptores.garantia_l1' => false]);
        $this->ventana(777001, now()->subMonth(), now()->addMonths(5));
        $this->assertNull(app(WarrantyWindowService::class)->registrarEventoTicket(990001, 777001));
    }

    public function test_dentro_de_ventana_liga_a_la_orden_original(): void
    {
        config(['unificacion.interruptores.garantia_l1' => true]);
        $w = $this->ventana(777002, now()->subMonth(), now()->addMonths(5));
        $e = app(WarrantyWindowService::class)->registrarEventoTicket(990002, 777002);
        $this->assertNotNull($e);
        $this->assertSame('T-888001', $e->orden_ref);
        $this->assertSame($w, (int) $e->window_id);
        $this->assertSame(777002, (int) $e->client_id);
        // idempotente
        $this->assertNull(app(WarrantyWindowService::class)->registrarEventoTicket(990002, 777002));
    }

    public function test_fuera_de_ventana_no_liga(): void
    {
        config(['unificacion.interruptores.garantia_l1' => true]);
        $this->ventana(777003, now()->subMonths(8), now()->subMonths(2));
        $this->assertNull(app(WarrantyWindowService::class)->registrarEventoTicket(990003, 777003));
    }
}
