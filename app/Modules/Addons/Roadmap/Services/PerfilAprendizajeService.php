<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;

/**
 * Loop de aprendizaje del perfil de decisiones de Irving (item #351, Opción 2 — semi-automática
 * con revisión batch, recomendada en el brief C y aprobada por Irving al soltar el item al pool).
 *
 * Captura cada aprobación/rechazo de Irving sobre un item de su bandeja, y cada "⚠ Reportar
 * problema" sobre un item ya validado, como candidato CRUDO trazable en
 * `docs/pendientes-perfil-irving.md`. NO inlinea nada automáticamente en
 * `docs/perfil-decisiones-irving.md` — ese archivo lo edita SOLO Irving, en lote, cuando decide
 * que un patrón (≥3 decisiones consistentes) merece volverse preferencia. Frontera dura
 * (dinero/seguridad/prod/negocio) NUNCA se infiere aquí, solo se registra el crudo.
 *
 * Falla-segura y NO crítica: cualquier error de escritura se loguea y se ignora — nunca debe
 * tumbar la decisión real de Irving en `RoadmapController::decidir`/`validacionReportar`.
 */
class PerfilAprendizajeService
{
    private const ANCLA = '<!-- CIRCUITO:APPEND-ANCHOR — no editar esta línea, el capturador automático agrega debajo -->';

    /**
     * Registra una decisión de Irving (log entry de `decidir()`) como candidato crudo.
     * Solo captura acciones con señal de aprendizaje real (aprobar/rechazar); "comentar" y
     * "cerrar"/"cancelar" no expresan una preferencia de ejecución y se ignoran.
     */
    public function capturar(RoadmapItem $item, array $logEntry): void
    {
        $accion = (string) ($logEntry['decision'] ?? '');
        if (! in_array($accion, ['aprobar', 'rechazar'], true)) {
            return;
        }

        $this->escribirBloque($item, $this->formatear($item, $logEntry, $accion));
    }

    /**
     * #545 — Registra un "⚠ Reportar problema" (`RoadmapController::validacionReportar`) como
     * candidato crudo: el circuito dio por buena una ejecución y a Irving NO le gustó el resultado
     * — señal de aprendizaje tan real como un rechazo en bandeja. Mismo archivo, misma ancla, mismo
     * criterio de "solo Irving lo promueve al perfil en lote".
     */
    public function capturarProblema(RoadmapItem $item, string $comentario): void
    {
        $lineas = [
            '### #' . $item->id . ' — PROBLEMA REPORTADO — ' . now()->toIso8601String(),
            '- Título: ' . mb_strimwidth((string) $item->title, 0, 160, '…'),
            '- Módulo: ' . ($item->modulo ?: '(sin módulo)') . ' · Nivel de riesgo: ' . ($item->nivel_riesgo ?: '?'),
        ];
        $comentario = trim($comentario);
        if ($comentario !== '') {
            $lineas[] = '- Comentario: ' . mb_strimwidth($comentario, 0, 600, '…');
        }
        $lineas[] = '';

        $this->escribirBloque($item, implode("\n", $lineas));
    }

    private function escribirBloque(RoadmapItem $item, string $bloque): void
    {
        try {
            $path = $this->path();
            if (! is_file($path)) {
                return; // sin archivo destino, no crea nada nuevo por su cuenta (aditivo, no autoritativo)
            }

            $contenido = (string) file_get_contents($path);
            if (! str_contains($contenido, self::ANCLA)) {
                return;
            }

            $contenido = str_replace(self::ANCLA, self::ANCLA . "\n" . $bloque, $contenido);
            file_put_contents($path, $contenido);
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('perfil-aprendizaje-captura-fallo', [
                'item'  => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatear(RoadmapItem $item, array $logEntry, string $accion): string
    {
        $fecha = (string) ($logEntry['ts'] ?? now()->toIso8601String());
        $por = (string) ($logEntry['por'] ?? 'irving');
        $comentario = trim((string) ($logEntry['comentario'] ?? ''));
        $opcion = trim((string) ($logEntry['opcion_elegida'] ?? ''));
        $veredicto = $accion === 'aprobar' ? 'APROBÓ' : 'RECHAZÓ';

        $lineas = [
            "### #{$item->id} — {$veredicto} — {$fecha}",
            '- Título: ' . mb_strimwidth((string) $item->title, 0, 160, '…'),
            '- Módulo: ' . ($item->modulo ?: '(sin módulo)') . ' · Nivel de riesgo: ' . ($item->nivel_riesgo ?: '?'),
            '- Por: ' . $por,
        ];
        if ($opcion !== '') {
            $lineas[] = '- Opción elegida: ' . mb_strimwidth($opcion, 0, 200, '…');
        }
        if ($comentario !== '') {
            $lineas[] = '- Comentario: ' . mb_strimwidth($comentario, 0, 600, '…');
        }
        $lineas[] = '';

        return implode("\n", $lineas);
    }

    private function path(): string
    {
        return (string) config('circuito.revisor.pendientes_perfil_path', base_path('docs/pendientes-perfil-irving.md'));
    }
}
