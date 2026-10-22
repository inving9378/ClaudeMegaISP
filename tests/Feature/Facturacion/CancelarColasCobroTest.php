<?php

namespace Tests\Feature\Facturacion;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FAC-07 (item #514): billing:cancelar-colas — manifiesto, dry-run, ejecución con sha256 aprobado,
 * revalidación por fila y reversión acotada. BD de pruebas en transacción revertida, filas
 * sintéticas (via = fac07-sintetico) y disco local falso.
 *
 * ⚠️ Escrita el 2026-10-16 cuando `megaisp_test` estaba a medio migrar (#529): el mismo escenario
 * se validó contra dev en una transacción revertida (ver docs/bitacora/2026-10-16-item-514.md);
 * esta prueba queda para correr cuando se reconstruya la BD de pruebas.
 */
class CancelarColasCobroTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['2025-06-01', '2025-06-02', '2025-06-03'] as $dia) {
            $this->ids[] = $this->fila('pending', "{$dia} 10:00:00");
        }
        $this->ids[] = $this->fila('sent', '2025-06-01 10:00:00');
        $this->ids[] = $this->fila('pending', '2026-10-15 10:00:00');
    }

    /** La carpeta queda sin permiso de escritura, como cuando la creó otro usuario del servidor. */
    private function carpetaSinPermiso(string $dir): void
    {
        Storage::disk('local')->makeDirectory($dir);
        $ruta = Storage::disk('local')->path($dir);
        chmod($ruta, 0500);
        $this->beforeApplicationDestroyed(fn () => @chmod($ruta, 0755));
    }

    private function fila(string $estado, string $fecha): int
    {
        return DB::table('invoice_emails')->insertGetId(['client_id' => 1, 'via' => 'fac07-sintetico', 'status' => $estado,
            'subject' => 'SINTÉTICO FAC-07', 'created_at' => $fecha, 'updated_at' => $fecha]);
    }

    private function correr(array $args): string
    {
        Artisan::call('billing:cancelar-colas', $args);

        return Artisan::output();
    }

    private function estados(): array
    {
        return DB::table('invoice_emails')->whereIn('id', $this->ids)->orderBy('id')->pluck('status')->all();
    }

    private function preparar(): array
    {
        $salida = $this->correr(['--preparar' => true, '--cola' => 'invoice_emails', '--tipos' => 'fac07-sintetico',
            '--estados' => 'pending', '--hasta' => '2025-07-01']);
        preg_match('#storage/app/(\S+\.json)#', $salida, $m);
        preg_match('/sha256: ([0-9a-f]{64})/', $salida, $h);

        return [$m[1], $h[1]];
    }

    public function test_exige_criterios_explicitos_y_estados_cancelables(): void
    {
        $this->assertStringContainsString('obligatorios', $this->correr(['--preparar' => true, '--cola' => 'invoice_emails']));
        $this->assertStringContainsString('no cancelables', $this->correr(['--preparar' => true, '--cola' => 'invoice_emails',
            '--tipos' => 'fac07-sintetico', '--estados' => 'sent', '--hasta' => '2025-07-01']));
    }

    public function test_el_manifiesto_solo_trae_la_seleccion_y_el_dry_run_no_modifica(): void
    {
        [$manifiesto] = $this->preparar();
        $datos = json_decode(Storage::disk('local')->get($manifiesto), true);

        $this->assertSame(array_slice($this->ids, 0, 3), array_column($datos['filas'], 0));
        $this->assertStringContainsString('DRY-RUN', $this->correr(['--manifiesto' => $manifiesto]));
        $this->assertSame(['pending', 'pending', 'pending', 'sent', 'pending'], $this->estados());
    }

    public function test_ejecuta_solo_con_el_sha_aprobado_y_omite_lo_que_cambio(): void
    {
        [$manifiesto, $sha] = $this->preparar();
        DB::table('invoice_emails')->where('id', $this->ids[1])->update(['updated_at' => '2026-10-16 12:00:00']);

        $this->assertStringContainsString('no coincide', $this->correr(['--manifiesto' => $manifiesto, '--ejecutar' => true, '--sha256' => str_repeat('0', 64)]));
        $this->assertSame(['pending', 'pending', 'pending', 'sent', 'pending'], $this->estados());

        $this->correr(['--manifiesto' => $manifiesto, '--ejecutar' => true, '--sha256' => $sha, '--lote' => 1]);
        $this->assertSame(['cancelled', 'pending', 'cancelled', 'sent', 'pending'], $this->estados());

        $this->assertStringContainsString('Cancelados: 0', $this->correr(['--manifiesto' => $manifiesto, '--ejecutar' => true, '--sha256' => $sha]));
    }

    public function test_revertir_solo_lo_que_sigue_como_lo_dejo_la_cancelacion(): void
    {
        [$manifiesto, $sha] = $this->preparar();
        $salida = $this->correr(['--manifiesto' => $manifiesto, '--ejecutar' => true, '--sha256' => $sha]);
        preg_match('#storage/app/(\S+resultado\S+\.json)#', $salida, $m);
        DB::table('invoice_emails')->where('id', $this->ids[0])->update(['status' => 'sent', 'updated_at' => '2026-10-16 23:00:00']);

        $this->correr(['--revertir' => $m[1], '--ejecutar' => true]);

        $this->assertSame(['sent', 'pending', 'pending', 'sent', 'pending'], $this->estados());
    }

    public function test_rechaza_un_manifiesto_de_otra_base(): void
    {
        [$manifiesto] = $this->preparar();
        $datos = json_decode(Storage::disk('local')->get($manifiesto), true);
        $datos['base_datos'] = 'otra_base';
        Storage::disk('local')->put($manifiesto, json_encode($datos));

        $this->assertStringContainsString('No se aplica', $this->correr(['--manifiesto' => $manifiesto]));
    }

    public function test_no_cancela_si_no_puede_guardar_el_resultado_para_revertir(): void
    {
        [$manifiesto, $sha] = $this->preparar();
        $this->carpetaSinPermiso(dirname($manifiesto));

        $salida = $this->correr(['--manifiesto' => $manifiesto, '--ejecutar' => true, '--sha256' => $sha]);
        $this->assertStringContainsString('No se pudo guardar', $salida);
        $this->assertSame(['pending', 'pending', 'pending', 'sent', 'pending'], $this->estados());
    }
}
