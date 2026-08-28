<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

/**
 * #980 (sub-item de seguimiento de #935, épica #877 §1) — «¿por qué no se mueve esto?».
 *
 * SOLO LECTURA: ningún método de esta clase hace UPDATE, dispara una acción ni toca una
 * migración. Lee columnas y servicios que YA existen; no inventa un mecanismo de destrabe nuevo.
 *
 * Cubre 4 de las 8 causas de la tabla de #877 §1 (`espera_resolucion`, `aprobado_no_despachable`,
 * `reclamo_huerfano`, `tope_duro`), evaluadas en el ORDEN EXACTO de esa tabla — la más específica
 * primero, quedándose con la primera que aplique. Las 4 restantes (`sin_terminal`,
 * `override_consumido`, `motor_caido`, `sin_causa`) quedan para un sub-item posterior; mientras
 * tanto, si ninguna de las 4 cubiertas aplica, se devuelve `no_determinado` con lo que sí se sabe
 * — «el diagnóstico no adivina» (#877): una explicación inventada es peor que ninguna.
 *
 * Contrato consumido por `RoadmapController::atorados()` (#937, guardado con `class_exists`):
 * llamada ESTÁTICA de un solo item, `$servicio::para($item)`, leyendo `$diag['causa']`.
 */
class DiagnosticoItemService
{
    /**
     * @param  bool|null  $esDespachable  Resultado ya calculado de `scopeDespachable()` para este
     *   item (el item padre #935 lo dejó listo así): pásalo en un batch para evitar re-consultarlo
     *   por cada fila (N+1). Si se omite, se calcula aquí con una consulta puntual.
     * @return array{causa:string, explicacion:string, accion:?string, procedencia:string}
     */
    public static function para(RoadmapItem $item, ?bool $esDespachable = null): array
    {
        return static::porEsperaResolucion($item)
            ?? static::porAprobadoNoDespachable($item, $esDespachable)
            ?? static::porReclamoHuerfano($item)
            ?? static::porTopeDuro($item)
            ?? static::porOtroMotivoNoDespachable($item, $esDespachable)
            ?? static::noDeterminado();
    }

    /** Causa 1 — item en la bandeja de Irving, sin resolver. */
    private static function porEsperaResolucion(RoadmapItem $item): ?array
    {
        if ($item->estado_aprobacion !== 'requiere_irving') {
            return null;
        }

        // `revisado_at` lo sella el revisor/Jarvis en el mismo movimiento que deja el item en
        // `requiere_irving` (RevisorService::aplicarVeredicto, JarvisService) — es la marca de
        // tiempo más cercana a «desde cuándo espera». `updated_at` es el respaldo honesto si un
        // item legacy no la trae.
        $desde = $item->revisado_at ?? $item->updated_at;
        $dias  = $desde ? (int) floor($desde->diffInDays(now())) : null;

        return [
            'causa'       => 'espera_resolucion',
            'explicacion' => $dias !== null
                ? "Esperando tu resolución desde hace {$dias} día" . ($dias === 1 ? '' : 's') . '.'
                : 'Esperando tu resolución.',
            'accion'      => 'resolver',
            'procedencia' => "estado_aprobacion='requiere_irving'; antigüedad desde "
                . ($item->revisado_at ? 'revisado_at' : 'updated_at (sin revisado_at)') . '.',
        ];
    }

    /** Causa 2 — ya aprobado, pero el nivel del item no cabe bajo la política de despacho vigente. */
    private static function porAprobadoNoDespachable(RoadmapItem $item, ?bool $esDespachable): ?array
    {
        if (! in_array($item->estado_aprobacion, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)) {
            return null;
        }

        $motivo = $item->motivoNoDespachable($esDespachable);
        // Los demás `code` de motivoNoDespachable() (freno_humano, bloqueado_por_bucle, agendado,
        // en_progreso, …) no son «nivel vs política»: se bucketean aparte, más abajo.
        if (! $motivo || ($motivo['code'] ?? null) !== 'no_despachable') {
            return null;
        }

        $base  = app(TorreAutomationPolicy::class)->politicaBase();
        $nivel = $item->nivel_riesgo ?: '—';

        return [
            'causa'       => 'aprobado_no_despachable',
            'explicacion' => "Aprobado, pero nivel {$nivel} no se despacha con la política en "
                . ($base ?? 'manual') . '.',
            'accion'      => 'dar_override',
            'procedencia' => "estado_aprobacion='{$item->estado_aprobacion}' (aprobado); "
                . "motivoNoDespachable()='no_despachable'; nivel_riesgo={$nivel} vs política base="
                . ($base ?? 'manual') . ' (TorreAutomationPolicy::politicaBase()).',
        ];
    }

    /** Causa 3 — una terminal lo reclamó (en_progreso) pero ya no hay nadie corriendo ese slot. */
    private static function porReclamoHuerfano(RoadmapItem $item): ?array
    {
        if ($item->estado_aprobacion !== 'en_progreso'
            || $item->en_desarrollo_humano
            || ! $item->worker_sid) {
            return null;
        }

        // Misma ventana de gracia que ReapStuckCommand líneas 43-48: sin ella, un item recién
        // reclamado por el scheduler (antes de que vuelta.sh tome su flock) se leería como huérfano.
        $gracia = max(1, (int) config('circuito.reaper.gracia_minutos', 3));
        if (! $item->claimed_at || $item->claimed_at->gte(now()->subMinutes($gracia))) {
            return null;
        }

        if (! app(RoadmapCircuitoService::class)->slotLibre((string) $item->worker_sid)) {
            return null;
        }

        return [
            'causa'       => 'reclamo_huerfano',
            'explicacion' => "La terminal {$item->worker_sid} lo reclamó y ya terminó.",
            'accion'      => 'liberar_terminal',
            'procedencia' => "estado_aprobacion='en_progreso', worker_sid='{$item->worker_sid}', "
                . "claimed_at hace más de {$gracia} min, y RoadmapCircuitoService::slotLibre() "
                . 'confirma que ninguna vuelta usa ese slot (misma lógica que ReapStuckCommand).',
        ];
    }

    /** Causa 4 — el item toca una frontera dura (producción / borrado / dinero / credenciales). */
    private static function porTopeDuro(RoadmapItem $item): ?array
    {
        $categoria = app(TorreAutomationPolicy::class)->tocaFronteraDura($item);
        if ($categoria === null) {
            return null;
        }

        return [
            'causa'       => 'tope_duro',
            'explicacion' => 'Bloqueado: toca ' . static::etiquetaCategoria($categoria) . '.',
            'accion'      => null,
            'procedencia' => "TorreAutomationPolicy::tocaFronteraDura() → categoría '{$categoria}' "
                . '(JarvisService::fronteraDuraDeItem sobre título+descripción+prompt, honrando '
                . 'la válvula de nacimiento).',
        ];
    }

    private static function etiquetaCategoria(string $categoria): string
    {
        return match ($categoria) {
            'produccion'   => 'producción',
            'borrar_datos' => 'borrado de datos / migración destructiva',
            'dinero'       => 'dinero',
            'credenciales' => 'seguridad / credenciales / permisos',
            default        => $categoria,
        };
    }

    /**
     * DECISIÓN (registrada con `circuito:reportar --tipo=decision`, ver item #980): los `code` de
     * `motivoNoDespachable()` que no tienen causa 1:1 en la tabla de 8 de #877 (freno_humano,
     * bloqueado_por_bucle, sesion_supervisada, fuera_del_pool, desarrollo_humano, agendado,
     * esperando_merge, en_progreso no-huérfano, ya_cerrado, descartado) se bucketean como
     * `no_determinado`, pero NUNCA vacío: usan el `error` que motivoNoDespachable() ya trae, que es
     * información real, no una adivinanza.
     */
    private static function porOtroMotivoNoDespachable(RoadmapItem $item, ?bool $esDespachable): ?array
    {
        $motivo = $item->motivoNoDespachable($esDespachable);
        if (! $motivo) {
            return null;
        }

        return [
            'causa'       => 'no_determinado',
            'explicacion' => (string) ($motivo['error'] ?? 'No se pudo despachar; motivo no clasificado.'),
            'accion'      => $motivo['accion'] ?? null,
            'procedencia' => "motivoNoDespachable() code='" . ($motivo['code'] ?? '?') . "' — sin causa "
                . '1:1 en la tabla de #877; se usa su explicación honesta en vez de forzarlo en un '
                . 'bucket equivocado (decisión registrada en el item #980).',
        ];
    }

    /** Ninguna de las 4 causas cubiertas aplicó, y el item SÍ es despachable normalmente. */
    private static function noDeterminado(): array
    {
        return [
            'causa'       => 'no_determinado',
            'explicacion' => 'No se detectó ninguna de las causas cubiertas por este diagnóstico '
                . '(sin_terminal, override_consumido, motor_caido y sin_causa quedan para el '
                . 'siguiente sub-item).',
            'accion'      => null,
            'procedencia' => 'Ninguna de las 4 causas evaluadas (espera_resolucion, '
                . 'aprobado_no_despachable, reclamo_huerfano, tope_duro) ni motivoNoDespachable() aplicó.',
        ];
    }
}
