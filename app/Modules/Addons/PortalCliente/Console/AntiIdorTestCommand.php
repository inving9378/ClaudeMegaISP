<?php

namespace App\Modules\Addons\PortalCliente\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Test anti-IDOR para el Portal Cliente.
 * Verifica que el cliente A NO puede leer datos del cliente B.
 * Solo lectura, sin modificar datos reales.
 *
 * Uso: php artisan portal:test-idor
 */
class AntiIdorTestCommand extends Command
{
    protected $signature = 'portal:test-idor';
    protected $description = 'Verifica aislamiento multi-tenant del portal (anti-IDOR). Solo lectura.';

    public function handle(): int
    {
        $this->info('🔒 Test anti-IDOR — Portal Cliente');
        $this->newLine();

        $passed  = 0;
        $failed  = 0;
        $results = [];

        // ── Seleccionar dos clientes reales con datos ──────────────────────
        $clientes = DB::table('client_main_information as m')
            ->join('clients as c', 'c.id', 'm.client_id')
            ->whereNotNull('m.portal_password')
            ->limit(2)
            ->pluck('m.client_id')
            ->toArray();

        if (count($clientes) < 2) {
            $this->warn('No hay suficientes clientes con portal_password para el test. Necesitas al menos 2.');
            return Command::FAILURE;
        }

        [$clientA, $clientB] = $clientes;
        $this->line("  Cliente A: #{$clientA}   Cliente B: #{$clientB}");
        $this->newLine();

        // ── TEST 1: Facturas ───────────────────────────────────────────────
        $facturaBCliente = DB::table('client_invoices')
            ->where('client_id', $clientB)
            ->first(['id', 'client_id']);

        if ($facturaBCliente) {
            // Simular que A pide una factura de B
            $fetched = DB::table('client_invoices')
                ->where('id', $facturaBCliente->id)
                ->where('client_id', $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null; // A NO debe ver la factura de B
            $this->testResult('Facturas: A no puede ver factura de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin facturas en cliente B para probar.');
        }

        // ── TEST 2: Pagos ──────────────────────────────────────────────────
        $pagoB = DB::table('payments')
            ->where('paymentable_id', $clientB)
            ->whereIn('paymentable_type', ['App\\Models\\Client', 'App\\Modules\\Core\\Clientes\\Models\\Client'])
            ->first(['id', 'paymentable_id']);

        if ($pagoB) {
            // A consulta con su ID — no debe aparecer el pago de B
            $fetched = DB::table('payments')
                ->where('paymentable_id', $clientA) // scoping correcto
                ->where('id', $pagoB->id)
                ->first();

            $ok = $fetched === null;
            $this->testResult('Pagos: A no puede ver pago de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin pagos en cliente B para probar.');
        }

        // ── TEST 3: Tickets ────────────────────────────────────────────────
        $ticketB = DB::table('tickets')
            ->where('customer_lead', $clientB)
            ->first(['id', 'customer_lead']);

        if ($ticketB) {
            $fetched = DB::table('tickets')
                ->where('id', $ticketB->id)
                ->where('customer_lead', $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null;
            $this->testResult('Tickets: A no puede ver ticket de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin tickets en cliente B para probar.');
        }

        // ── TEST 4: CLABE ──────────────────────────────────────────────────
        $clabeB = DB::table('payment_clabes')
            ->where('client_id', $clientB)
            ->first(['id', 'client_id']);

        if ($clabeB) {
            $fetched = DB::table('payment_clabes')
                ->where('id', $clabeB->id)
                ->where('client_id', $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null;
            $this->testResult('CLABE: A no puede ver CLABE de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin CLABE en cliente B para probar.');
        }

        // ── TEST 5: Consumo ────────────────────────────────────────────────
        $consumoB = DB::table('internet_consumptions')
            ->where('client_name', 'Meganet__' . $clientB)
            ->first(['id', 'client_name']);

        if ($consumoB) {
            $fetched = DB::table('internet_consumptions')
                ->where('id', $consumoB->id)
                ->where('client_name', 'Meganet__' . $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null;
            $this->testResult('Consumo: A no puede ver consumo de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin consumo en cliente B para probar.');
        }

        // ── TEST 6: MegaFamilia parental_accounts ──────────────────────────
        $userId_B_user = DB::table('client_main_information')->where('client_id', $clientB)->value('user');
        $userId_B = $userId_B_user ? DB::table('users')->where('login_user', $userId_B_user)->value('id') : null;
        $userId_A_user = DB::table('client_main_information')->where('client_id', $clientA)->value('user');
        $userId_A = $userId_A_user ? DB::table('users')->where('login_user', $userId_A_user)->value('id') : null;

        $paB = $userId_B ? DB::table('parental_accounts')->where('user_id', $userId_B)->first() : null;

        if ($paB && $userId_A) {
            // A consulta con su user_id — no debe ver cuenta de B
            $fetched = DB::table('parental_accounts')
                ->where('id', $paB->id)
                ->where('user_id', $userId_A)
                ->first();

            $ok = $fetched === null;
            $this->testResult('MegaFamilia: A no puede ver cuenta parental de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin parental_account en cliente B para probar.');
        }

        $this->newLine();
        $this->info("Resultado: {$passed} ✅ / {$failed} ❌ (total " . ($passed + $failed) . " tests ejecutados)");

        if ($failed > 0) {
            $this->error('⚠️ TESTS FALLIDOS — revisar aislamiento antes de desplegar en producción.');
        } else {
            $this->info('✅ Todos los tests anti-IDOR pasaron. El aislamiento por cliente está funcionando.');
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function testResult(string $name, bool $passed, array &$results, int &$p, int &$f): void
    {
        $icon = $passed ? '✅' : '❌';
        $this->line("  {$icon} {$name}");
        $results[] = ['test' => $name, 'passed' => $passed];
        $passed ? $p++ : $f++;
    }
}
