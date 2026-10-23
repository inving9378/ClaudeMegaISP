<?php

namespace Tests\Feature\Facturacion;

use App\Console\Commands\Active\CreateProformaInvoiceCommand;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\CreatesApplication;

/**
 * #572: el candado por cliente de createProformaForClient debe serializar dos corridas simultáneas.
 * Necesita datos COMMITEADOS (dos procesos), por eso no usa DatabaseTransactions: siembra clientes
 * sintéticos en megaisp_test y los borra al final.
 */
class ProformaCandadoConcurrenciaTest extends TestCase
{
    use CreatesApplication;

    private array $clientes = [];

    protected function tearDown(): void
    {
        if ($this->clientes) {
            $ids = Invoice::whereIn('client_id', $this->clientes)->pluck('id');
            DB::table('invoice_items')->whereIn('invoice_id', $ids)->delete();
            Invoice::whereIn('client_id', $this->clientes)->forceDelete();
            DB::table('client_main_information')->whereIn('client_id', $this->clientes)->delete();
            DB::table('clients')->whereIn('id', $this->clientes)->delete();
        }
        parent::tearDown();
    }

    public function test_dos_procesos_simultaneos_crean_una_sola_proforma_por_cliente_y_periodo(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl no disponible');
        }
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName());
        Queue::fake();

        $u = (int) DB::table('users')->value('id');
        for ($i = 0; $i < 6; $i++) {
            $id = DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u,
                'fecha_corte' => '2026-10-25 23:59:59', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'CLIENTE SINTÉTICO #572',
                'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now()]);
            $this->clientes[] = $id;
            // Sin referencia previa, su índice único taparía la carrera (el perdedor revienta y revierte).
            \App\Modules\Addons\Payments\Services\PaymentReferenceService::ensureFor($id);
        }
        DB::disconnect();

        $inicio = microtime(true) + 1.0;
        $pids = [];
        foreach ([0, 1] as $n) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::reconnect();
                    $cmd = $this->app->make(CreateProformaInvoiceCommand::class);
                    $cmd->setLaravel($this->app);
                    $cmd->setOutput(new \Illuminate\Console\OutputStyle(
                        new \Symfony\Component\Console\Input\ArrayInput([]),
                        new \Symfony\Component\Console\Output\NullOutput()));
                    $m = new \ReflectionMethod($cmd, 'createProformaForClient');
                    $m->setAccessible(true);
                    while (microtime(true) < $inicio) {
                        usleep(200);
                    }
                    foreach ($this->clientes as $cid) {
                        try {
                            $m->invoke($cmd, \App\Modules\Core\Clientes\Models\Client::find($cid), '2026-10-25 23:59:59', '2026-10');
                        } catch (\Throwable $e) {
                            fwrite(STDERR, $e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    fwrite(STDERR, $e->getMessage());
                }
                exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $st);
        }
        DB::reconnect();

        foreach ($this->clientes as $cid) {
            $this->assertSame(1, Invoice::where('client_id', $cid)->where('period', '2026-10')->count(),
                "cliente {$cid}: proforma duplicada");
        }
    }
}
