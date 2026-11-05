<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Support\GastoIa;
use Illuminate\Console\Command;

/**
 * #1076 — cuánto gastó la IA del supervisor, por pieza y modelo, en pesos.
 *
 * Solo lectura: suma el log del canal `claude` (storage/logs/claude-api-*.log, 7 días). Sirve para
 * medir el antes y el después de «gastar lo mínimo»: las líneas de antes de #1076 salen como
 * «(sin etiqueta)» con el costo recalculado de su `usage`.
 */
class GastoIaCommand extends Command
{
    protected $signature = 'circuito:gasto-ia
        {--desde= : Y-m-d (por defecto, hace 6 días)}
        {--hasta= : Y-m-d (por defecto, hoy)}
        {--por-dia : desglosa cada día}
        {--ruta= : carpeta de logs (por defecto storage/logs)}
        {--json : salida en JSON}';

    protected $description = 'Gasto de IA del supervisor por pieza y modelo (USD y MXN), leído del log claude.';

    public function handle(): int
    {
        $hasta = (string) ($this->option('hasta') ?: now()->format('Y-m-d'));
        $desde = (string) ($this->option('desde') ?: now()->subDays(6)->format('Y-m-d'));
        $ruta  = (string) ($this->option('ruta') ?: storage_path('logs'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $this->error('Usa fechas Y-m-d en --desde y --hasta.');

            return self::FAILURE;
        }

        $filas = GastoIa::sumar(GastoIa::lineas($ruta, $desde, $hasta), (bool) $this->option('por-dia'));

        if ($this->option('json')) {
            $this->line(json_encode(array_values($filas), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if (! $filas) {
            $this->info("Sin llamadas a Claude registradas entre {$desde} y {$hasta} en {$ruta}.");

            return self::SUCCESS;
        }

        $cab = ['Pieza', 'Modelo', 'Llamadas', 'OK', 'Error', 'Entrada', 'Caché esc.', 'Caché lect.', 'Salida', 'USD', 'MXN', 'MXN/llamada'];
        if ($this->option('por-dia')) {
            array_unshift($cab, 'Día');
        }

        $tot = ['llamadas' => 0, 'ok' => 0, 'usd' => 0.0, 'mxn' => 0.0];
        $rows = [];
        foreach ($filas as $f) {
            $r = [$f['pieza'], $f['modelo'], $f['llamadas'], $f['ok'], $f['errores'],
                number_format($f['entrada']), number_format($f['cache_escritura']), number_format($f['cache_lectura']),
                number_format($f['salida']), number_format($f['usd'], 4), number_format($f['mxn'], 2), number_format($f['mxn_por_llamada'], 3)];
            if ($this->option('por-dia')) {
                array_unshift($r, $f['fecha']);
            }
            $rows[] = $r;
            foreach (['llamadas', 'ok', 'usd', 'mxn'] as $k) {
                $tot[$k] += $f[$k];
            }
        }

        $this->info("Gasto de IA (canal claude) del {$desde} al {$hasta}:");
        $this->table($cab, $rows);
        $this->line(sprintf('TOTAL: %d llamadas (%d OK) · USD %.4f · MXN %.2f', $tot['llamadas'], $tot['ok'], $tot['usd'], $tot['mxn']));

        return self::SUCCESS;
    }
}
