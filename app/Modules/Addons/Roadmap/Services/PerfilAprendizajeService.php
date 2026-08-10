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
 * `storage/app/circuito/pendientes-perfil-irving.md` (fuera de git: es estado que el circuito
 * reescribe en cada vuelta, no fuente versionada). NO inlinea nada automáticamente en
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
                // El archivo salió de git (es estado de runtime, no fuente) → en un checkout
                // nuevo no existe. Antes esto era `return` y el loop moría en silencio; ahora
                // se siembra el esqueleto con su ancla. Sigue sin ser autoritativo: solo
                // acumula candidatos crudos, el perfil real lo promueve Irving a mano.
                if (! $this->sembrar($path)) {
                    return;
                }
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

    /** Crea el esqueleto (directorio + encabezado + ancla). Devuelve false si no se pudo. */
    private function sembrar(string $path): bool
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        $cabecera = "# Pendientes del perfil de Irving — candidatos crudos\n\n"
            . "> Lo escribe el circuito solo (`PerfilAprendizajeService`). NO es fuente versionada:\n"
            . "> vive en `storage/app/circuito/` justamente para no ensuciar el árbol de git en cada\n"
            . "> vuelta. Irving promueve a mano lo que sea patrón real a `docs/perfil-decisiones-irving.md`.\n\n"
            . self::ANCLA . "\n";

        return @file_put_contents($path, $cabecera) !== false;
    }

    private function path(): string
    {
        return (string) config(
            'circuito.revisor.pendientes_perfil_path',
            storage_path('app/circuito/pendientes-perfil-irving.md')
        );
    }
}
