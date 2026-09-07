<?php

namespace App\Modules\Addons\MapaRed\Console;

use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Services\OpticalBudgetService;
use Illuminate\Console\Command;

/**
 * MR-18 (item #954, seguimiento #9990440) — validación de solo-lectura del DoD cuantitativo:
 * para una muestra de enlaces reales con RX real de MultiOLT, diferencia calculado-vs-real
 * < TOLERANCIA_DB en al menos 7 de cada 10 evaluados.
 *
 * SOLO LECTURA: no crea, modifica ni borra nada. Muestrea `mapared_enlaces_servicio` con
 * `ont_serie` no nulo (los únicos emparejables contra `olt_onus.sn`, ver
 * `OpticalBudgetService::rxRealDeMultiOlt()`), corre `calcular()` sobre cada uno y reporta
 * cuántos caen dentro de tolerancia, con el motivo explícito de los que no.
 *
 * Si la muestra tiene menos de `--muestra` enlaces evaluables (o cero), lo reporta tal cual —
 * NUNCA rellena con datos sintéticos: un resultado del DoD solo vale si viene de enlaces reales.
 */
class ValidarPresupuestoOpticoCommand extends Command
{
    protected $signature = 'mapared:validar-presupuesto-optico {--muestra=10}';

    protected $description = 'MR-18: valida (solo lectura) el presupuesto óptico calculado contra el RX real de MultiOLT para una muestra de enlaces reales.';

    public function handle(OpticalBudgetService $service): int
    {
        $muestra = max(1, (int) $this->option('muestra'));

        $candidatos = MapaRedEnlaceServicio::query()
            ->whereNotNull('ont_serie')
            ->orderBy('id')
            ->get();

        $this->info("== Validación DoD MR-18 (#954) — muestra solicitada: {$muestra} ==");
        $this->line("Enlaces con ont_serie registrado en mapared_enlaces_servicio: {$candidatos->count()}");

        if ($candidatos->isEmpty()) {
            $this->warn(
                'Cero enlaces evaluables: mapared_enlaces_servicio no tiene filas con ont_serie. '
                . 'El DoD de #954 (10 clientes reales, diff <3dB en ≥7) NO se puede correr hasta que '
                . 'existan enlaces reales cargados (MR-14/#950 y el resto de la cadena de datos del '
                . 'grafo — mapared_puertos/hilos/empalmes/splitters — siguen en 0 filas al día de hoy).'
            );

            return self::SUCCESS;
        }

        $evaluados = [];
        foreach ($candidatos->take($muestra) as $enlace) {
            $resultado = $service->calcular($enlace);
            $evaluados[] = [
                'enlace_id' => $enlace->id,
                'cliente' => $enlace->cliente_nombre,
                'ruta_completa' => $resultado['ruta_completa'],
                'motivo_corte' => $resultado['motivo_corte'],
                'rx_estimado' => $resultado['rx_estimado_dbm'],
                'rx_real' => $resultado['rx_real_dbm'],
                'diferencia_db' => $resultado['diferencia_db'],
                'dentro_de_tolerancia' => $resultado['dentro_de_tolerancia'],
            ];
        }

        $this->table(
            ['Enlace', 'Cliente', 'Ruta completa', 'RX estimado', 'RX real', 'Diferencia dB', 'Dentro tolerancia'],
            array_map(fn ($e) => [
                $e['enlace_id'],
                $e['cliente'],
                $e['ruta_completa'] ? 'sí' : "no ({$e['motivo_corte']})",
                $e['rx_estimado'] ?? '—',
                $e['rx_real'] ?? '(sin lectura MultiOLT)',
                $e['diferencia_db'] ?? '—',
                $e['dentro_de_tolerancia'] === null ? 'n/a' : ($e['dentro_de_tolerancia'] ? 'sí' : 'NO'),
            ], $evaluados)
        );

        $conRxReal = array_filter($evaluados, fn ($e) => $e['rx_real'] !== null);
        $dentro = array_filter($conRxReal, fn ($e) => $e['dentro_de_tolerancia'] === true);

        $this->line('');
        $this->line('Evaluados: ' . count($evaluados) . ' | Con RX real de MultiOLT: ' . count($conRxReal)
            . ' | Dentro de tolerancia (<' . OpticalBudgetService::TOLERANCIA_DB . 'dB): ' . count($dentro));

        if (count($conRxReal) < $muestra) {
            $this->warn(
                'DoD NO cumplido por falta de muestra: se pedían ' . $muestra . ' enlaces con RX real '
                . 'comparable y solo hay ' . count($conRxReal) . '. Los enlaces sin RX real no tienen '
                . 'ONU emparejada en olt_onus por serie (ver rxRealDeMultiOlt()).'
            );

            return self::SUCCESS;
        }

        if (count($dentro) >= 7) {
            $this->info('DoD CUMPLIDO: ' . count($dentro) . ' de ' . count($conRxReal) . ' dentro de tolerancia (≥7 requerido).');
        } else {
            $this->error('DoD NO cumplido: solo ' . count($dentro) . ' de ' . count($conRxReal) . ' dentro de tolerancia (≥7 requerido).');
        }

        return self::SUCCESS;
    }
}
