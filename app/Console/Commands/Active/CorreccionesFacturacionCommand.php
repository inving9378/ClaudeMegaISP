<?php

namespace App\Console\Commands\Active;

use App\Services\Finance\Billing\CorreccionesFacturacion;
use Illuminate\Console\Command;

/**
 * Item #571, Parte B: corre las correcciones de facturación pendientes (la corrida que deja la
 * migración al actualizar). Va en el schedule cada 10 minutos y cada corrida se toma una sola vez,
 * así que en producción no hay que correr nada a mano. Ver CorreccionesFacturacion.
 *
 *   --simular  decide lo mismo sobre los datos de este servidor y no escribe nada;
 *   --nueva    agrega una corrida y la corre (para volver a revisar sin una versión nueva).
 */
class CorreccionesFacturacionCommand extends Command
{
    protected $signature = 'facturacion:correcciones-automaticas
        {--simular : muestra qué se aplicaría y qué quedaría pendiente, sin escribir}
        {--nueva : agrega una corrida nueva y la corre}';

    protected $description = 'Corrige sola la deuda fantasma determinista y deja el resto en Correcciones pendientes (item #571)';

    public function handle(CorreccionesFacturacion $correcciones): int
    {
        if ($this->option('simular')) {
            $r = $correcciones->correr(null, true);
            $this->imprimir('SIMULACIÓN (nada se escribió)', $r);

            return self::SUCCESS;
        }
        if ($this->option('nueva')) {
            $id = $correcciones->nuevaCorrida('Corrida manual por consola');
            $this->info("Corrida {$id} agregada.");
        }

        $resultados = $correcciones->correrPendientes();
        if (! $resultados) {
            $this->info('No hay corridas pendientes.');

            return self::SUCCESS;
        }
        foreach ($resultados as $id => $r) {
            $this->imprimir("Corrida {$id}", $r);
        }

        return collect($resultados)->contains(fn ($r) => isset($r['resumen']['error'])) ? self::FAILURE : self::SUCCESS;
    }

    private function imprimir(string $titulo, array $r): void
    {
        $this->line('');
        $this->info($titulo);
        if (isset($r['resumen']['error'])) {
            $this->error('Falló: ' . $r['resumen']['error']);

            return;
        }
        $this->table(['Resultado', 'Casos'], collect($r['resumen'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        $filas = collect($r['clientes'])->whereNotIn('decision', ['sin_cambios'])
            ->map(fn ($f) => [$f['cliente'], $f['categoria'], $f['decision'], mb_strimwidth($f['detalle'], 0, 110, '…')])->values()->all();
        if ($filas) {
            $this->table(['Cliente', 'Categoría', 'Resultado', 'Detalle'], $filas);
        }
    }
}
