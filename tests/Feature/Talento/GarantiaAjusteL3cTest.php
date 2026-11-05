<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Services\WarrantyReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\CreatesApplication;

/** UNI-T L3c (#1101): el efecto sobre el trabajo original solo existe tras revisión resuelta y como ajuste J3. */
class GarantiaAjusteL3cTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function revision(int $ticket, int $userId): array
    {
        $ev = DB::table('talento_warranty_events')->insertGetId([
            'ticket_id' => $ticket, 'client_id' => 777100, 'orden_origen' => 'task', 'orden_id' => 888100, 'orden_ref' => 'T-888100',
            'participantes' => json_encode([['user_id' => $userId, 'rol' => 'principal']]), 'orden_validada_at' => now()->subDays(20),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $s = app(WarrantyReviewService::class);
        return [$s, $s->abrir($ev)];
    }

    private function userColaborador(): int
    {
        $uid = (int) DB::table('users')->value('id');
        if (! $uid) {
            $this->markTestSkipped('Sin usuarios en la BD de prueba.');
        }
        $fila = ['user_id' => $uid, 'created_at' => now(), 'updated_at' => now()];
        foreach (\Illuminate\Support\Facades\Schema::getColumns('talento_colaboradores') as $c) {
            if (! $c['nullable'] && $c['default'] === null && ! $c['auto_increment'] && ! array_key_exists($c['name'], $fila)) {
                $t = $c['type_name'];
                $fila[$c['name']] = str_contains($t, 'int') ? 1 : (in_array($t, ['datetime', 'timestamp', 'date']) ? now() : 'x');
            }
        }
        DB::table('talento_colaboradores')->insert($fila);
        return $uid;
    }

    public function test_ningun_efecto_antes_de_revision_resuelta(): void
    {
        [$s, $r] = $this->revision(991101, $this->userColaborador());
        foreach ([fn () => null, fn () => $s->tomar($r->id, 900)] as $paso) {
            $paso();
            try {
                $s->aplicarAjuste($r->id, 900);
                $this->fail('debía rechazar');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(0, DB::table('talento_period_adjustments')->where('original_type', 'warranty_review')->where('original_id', $r->id)->count());
            }
        }
    }

    public function test_con_responsabilidad_registra_ajuste_idempotente(): void
    {
        [$s, $r] = $this->revision(991102, $this->userColaborador());
        $s->tomar($r->id, 900);
        $s->resolver($r->id, DB::table('talento_warranty_causes')->value('id'), 'trabajo_original', 'atribuida_trabajo_original');
        $a = $s->aplicarAjuste($r->id, 900, -3);
        $this->assertNotNull($a);
        $this->assertSame(-3.0, (float) $a->points_delta);
        $this->assertSame($a->id, $s->aplicarAjuste($r->id, 900, -3)->id);
        $this->assertSame(1, DB::table('talento_period_adjustments')->where('original_type', 'warranty_review')->where('original_id', $r->id)->count());
    }

    public function test_sin_responsabilidad_no_hay_ajuste(): void
    {
        [$s, $r] = $this->revision(991103, $this->userColaborador());
        $s->tomar($r->id, 900);
        $s->resolver($r->id, DB::table('talento_warranty_causes')->value('id'), 'cliente', 'sin_efecto');
        $this->assertNull($s->aplicarAjuste($r->id, 900));
        $this->assertSame(0, DB::table('talento_period_adjustments')->where('original_type', 'warranty_review')->where('original_id', $r->id)->count());
    }
}
