<?php

namespace Tests\Feature\Unificacion;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** UNI-T A2: uni:linea-base no emite escrituras SQL y dos corridas dan lo mismo. */
class UniLineaBaseTest extends TestCase
{
    public function test_no_escribe_y_es_repetible(): void
    {
        $escrituras = 0;
        DB::listen(function ($q) use (&$escrituras) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $q->sql)) {
                $escrituras++;
            }
        });

        $this->artisan('uni:linea-base', ['--salida' => 'test-a.json', '--semanas' => 2])->assertExitCode(0);
        $this->artisan('uni:linea-base', ['--salida' => 'test-b.json', '--semanas' => 2, '--comparar' => 'test-a.json'])->assertExitCode(0);

        $this->assertSame(0, $escrituras);
        @unlink(storage_path('app/uni/linea-base/test-a.json'));
        @unlink(storage_path('app/uni/linea-base/test-b.json'));
    }
}
