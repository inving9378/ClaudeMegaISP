<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Limpieza de la Hoja de ruta — ritmo medido para no quemar el plan Max (reglas de Irving, 2026-10-30):
 *  a) máximo 6 terminales a la vez (y nunca más que el paralelismo configurado);
 *  b) si la cuota SEMANAL del plan pasa de 80 %, no se toman pendientes nuevos: lo que ya corre
 *     termina y se avisa en la bandeja de Irving;
 *  c) sin medición reciente de la cuota, se trabaja con 4 terminales y se dice que no hay medición.
 * La espera creciente tras vueltas vacías y el tope de 2 fallos viven en sus propias reglas
 * (`sqlElegibleParaPool`, `circuito:cerrar-vuelta`); aquí solo se decide cuántas terminales.
 */
class RitmoCircuitoService
{
    public const TOPE_TERMINALES = 6;
    public const UMBRAL_SEMANA  = 80;
    public const SIN_MEDICION   = 4;
    public const SETTING        = 'circuito_ritmo';
    public const AVISO_TITULO   = 'Aviso del circuito · cuota semanal del plan';

    public function __construct(private RoadmapCircuitoService $circuito, private CuotaPlanService $cuota)
    {
    }

    /** @return array{max: int, motivo: string, texto: string, semana: ?int} */
    public function limite(): array
    {
        $tope = min(self::TOPE_TERMINALES, max(0, $this->circuito->getParalelismo()));
        $c    = $this->cuota->leer();
        $sem  = $c['semana'] ?? null;
        $pct  = $sem ? ($sem['reiniciada'] ? 0 : $sem['pct']) : null;   // ventana ya reiniciada = arranca de cero

        if ($c === null || $c['vieja'] || $pct === null) {
            return ['max' => min($tope, self::SIN_MEDICION), 'motivo' => 'sin_medicion', 'semana' => null,
                'texto' => 'No hay medición reciente de la cuota del plan: el circuito trabaja con '
                    . min($tope, self::SIN_MEDICION) . ' terminales hasta que la próxima vuelta la mida.'];
        }
        if ($pct > self::UMBRAL_SEMANA) {
            return ['max' => 0, 'motivo' => 'cuota_alta', 'semana' => $pct,
                'texto' => "La cuota semanal del plan va en {$pct} %: no se toman pendientes nuevos y lo que ya corre "
                    . 'termina' . (! empty($sem['reinicia_texto']) ? "; se reinicia {$sem['reinicia_texto']}." : '.')];
        }

        return ['max' => $tope, 'motivo' => 'normal', 'semana' => $pct,
            'texto' => "Cuota semanal del plan en {$pct} %: hasta {$tope} terminales."];
    }

    /** Lo último que decidió el scheduler (para la Torre y Jarvis, sin recalcular). */
    public function ultimo(): ?array
    {
        $v = DB::table('settings')->where('key', self::SETTING)->value('value');

        return $v ? json_decode((string) $v, true) : null;
    }

    /**
     * Corre en cada tick del scheduler: guarda el límite vigente y abre o cierra el aviso en la
     * bandeja de Irving cuando la cuota semanal cruza el umbral. Nunca lanza.
     */
    public function evaluar(): array
    {
        $l = $this->limite();
        try {
            DB::table('settings')->updateOrInsert(['key' => self::SETTING],
                ['value' => json_encode($l + ['at' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            $this->sincronizarAviso($l);
        } catch (Throwable) {
        }

        return $l;
    }

    private function sincronizarAviso(array $l): void
    {
        $abierto = RoadmapItem::query()->where('title', 'like', self::AVISO_TITULO . '%')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])->first();

        if ($l['motivo'] === 'cuota_alta' && ! $abierto) {
            RoadmapItem::create([
                'title'       => self::AVISO_TITULO . " al {$l['semana']} %: tomas nuevas en pausa",
                'description' => $l['texto'] . ' El circuito retoma solo cuando la cuota baje del '
                    . self::UMBRAL_SEMANA . ' % (o al reiniciarse la semana). Si quieres que siga de todos modos, '
                    . 'sube el umbral en RitmoCircuitoService::UMBRAL_SEMANA.',
                'status'            => 'pending',
                'estado_aprobacion' => 'requiere_irving',
                'motivo_espera'     => 'decision',
                'nivel_riesgo'      => 'C',
                'priority'          => 'alta',
                'modulo'            => 'Roadmap / Circuito CC',
                'excluir_pool_automatico' => true,
            ]);

            return;
        }

        if ($l['motivo'] !== 'cuota_alta' && $abierto) {
            $abierto->log = array_merge($abierto->log ?: [], [[
                'ts' => now()->toIso8601String(), 'por' => 'ritmo-circuito', 'evento' => 'aviso_resuelto',
                'motivo' => 'La cuota bajó del umbral: el circuito volvió a tomar pendientes. ' . $l['texto'],
            ]]);
            $abierto->estado_aprobacion = 'cancelado';
            $abierto->save();
        }
    }
}
