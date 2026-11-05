<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Services\MetaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** UNI-T M2b. Correr SOLO por nombre, APP_ENV=testing (megaisp_test). */
class MetaServiceTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_version_vigencia_solape_precedencia_e_historico(): void
    {
        $s = app(MetaService::class);
        $c = 'test_meta_'.uniqid();

        $v1 = $s->crear(['concepto' => $c, 'valor' => 10, 'vigente_desde' => '2026-01-01']);
        $v2 = $s->crear(['concepto' => $c, 'valor' => 12, 'vigente_desde' => '2026-03-01']);

        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->id, $v2->reemplaza_id);
        $this->assertSame('2026-02-28', $v1->fresh()->vigente_hasta->toDateString());

        // Histórico preservado
        $this->assertSame(10.0, (float) $s->resolver($c, [], '2026-02-15')->valor);
        $this->assertSame(12.0, (float) $s->resolver($c, [], '2026-04-01')->valor);
        $this->assertNull($s->resolver($c, [], '2025-12-31'));

        // Solape rechazado
        $this->expectException(\InvalidArgumentException::class);
        try {
            $s->crear(['concepto' => $c, 'valor' => 99, 'vigente_desde' => '2026-02-01']);
        } finally {
            // Precedencia (antes de propagar la excepción esperada)
            $s->crear(['concepto' => $c, 'alcance_tipo' => 'rol', 'alcance_clave' => 'TECNICO', 'valor' => 20, 'vigente_desde' => '2026-01-01']);
            $s->crear(['concepto' => $c, 'alcance_tipo' => 'persona', 'alcance_clave' => '7', 'valor' => 30, 'vigente_desde' => '2026-01-01']);
            $this->assertSame(30.0, (float) $s->resolver($c, ['persona' => 7, 'rol' => 'TECNICO'], '2026-04-01')->valor);
            $this->assertSame(20.0, (float) $s->resolver($c, ['persona' => 8, 'rol' => 'TECNICO'], '2026-04-01')->valor);
            $this->assertSame(12.0, (float) $s->resolver($c, ['persona' => 8], '2026-04-01')->valor);
        }
    }
}
