<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedCorrelativo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MR-24 (item #960, Fase 1) — nomenclatura automática de D24, generada en servidor:
 *   - NAP:      `NAP-{ZONA}-{N}`
 *   - Troncal:  `T-{ZONA}-FO{HILOS}-{N}`
 *
 * El correlativo `{N}` es un contador ATÓMICO por zona+tipo (tabla `mapared_correlativos`,
 * `lockForUpdate()` dentro de una transacción) — nunca se calcula por MAX/parseo de nombres
 * existentes, para no dejar huecos ni chocar bajo alta concurrencia (varias terminales/usuarios
 * dando de alta en la misma zona a la vez).
 *
 * Decisión (registrada en el item #960): para troncales el correlativo es ÚNICO por zona
 * (tipo='troncal'), no uno separado por cantidad de hilos — `FO{HILOS}` es solo texto
 * descriptivo dentro del nombre, no una partición del contador. Simplifica la lectura del
 * nombre ("el troncal #3 de esta zona") sin que el spec (D24) lo contradiga.
 *
 * Esta clase NO decide de dónde sale `$zona` (eso es D23 / resolución por punto — fuera de
 * alcance de esta fase, ver sub-items de #960) ni crea ningún registro de NAP/cable: solo
 * genera el nombre siguiente, ya persistido el consumo del correlativo.
 */
class NomenclaturaService
{
    public function siguienteNap(string $zona): string
    {
        $zonaNormalizada = $this->normalizarZona($zona);
        $n = $this->siguienteCorrelativo($zonaNormalizada, 'nap');

        return "NAP-{$zonaNormalizada}-{$n}";
    }

    public function siguienteTroncal(string $zona, int $numeroHilos): string
    {
        $zonaNormalizada = $this->normalizarZona($zona);
        $n = $this->siguienteCorrelativo($zonaNormalizada, 'troncal');

        return "T-{$zonaNormalizada}-FO{$numeroHilos}-{$n}";
    }

    /**
     * Incrementa y devuelve el correlativo zona+tipo. `lockForUpdate()` serializa los
     * incrementos concurrentes sobre la MISMA fila; `firstOrCreate` fuera del lock inicial
     * asegura que la fila exista antes de bloquearla (MySQL no puede lockear una fila que no
     * existe todavía).
     */
    private function siguienteCorrelativo(string $zona, string $tipo): int
    {
        return DB::transaction(function () use ($zona, $tipo) {
            MapaRedCorrelativo::firstOrCreate(['zona' => $zona, 'tipo' => $tipo], ['ultimo' => 0]);

            $correlativo = MapaRedCorrelativo::where('zona', $zona)
                ->where('tipo', $tipo)
                ->lockForUpdate()
                ->first();

            $correlativo->ultimo += 1;
            $correlativo->save();

            return $correlativo->ultimo;
        });
    }

    private function normalizarZona(string $zona): string
    {
        $limpia = trim(Str::of($zona)->ascii()->upper());

        return $limpia !== '' ? $limpia : 'SINZONA';
    }
}
