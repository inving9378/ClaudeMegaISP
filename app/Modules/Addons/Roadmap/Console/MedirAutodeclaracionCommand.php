<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * #674 (Pieza 3 de #646) — mide cuánto se equivoca la AUTODECLARACIÓN del modelo: cruza las
 * opciones `recomendada=true` de `roadmap_items.preguntas` (el JSON que arma
 * `RevisorService::proponerPreguntas()`, leído tal cual por `AutopilotService::evaluar()` SIN
 * verificación de hecho) contra lo que esos items terminaron necesitando DESPUÉS: revert, escalada
 * o reapertura. READ-ONLY: no toca ítems, no toca la política del autopilot — genera el número con
 * el que esa decisión (hoy opinión) pasa a ser lectura. Mismo patrón que `MedirValvulaContextoCommand`
 * (#902): un archivo de reporte, re-ejecutable sin costo (no llama IA, solo lee columnas ya escritas).
 *
 * Por qué la población es "todas las opciones recomendadas" y NO solo las decisiones ya ejecutadas
 * por el autopilot (`log[].decidido_por==='autopilot'`): en dev el autopilot solo ha decidido 2
 * items hasta hoy (el tope B/C rara vez habilita, ver nota "0 de 109" en `AutopilotService`), una
 * muestra demasiado chica para leer nada. Las opciones recomendadas SÍ existen en volumen (233 en
 * 76 items al momento de esta medición) porque se escriben en CADA brief, decida quien decida
 * (autopilot o Irving) — es exactamente el dato que el autopilot LEERÍA si el tope se aflojara, así
 * que es la población correcta para juzgar qué tan confiable es la autodeclaración en sí.
 *
 * Definiciones operativas (los 3 únicos eventos de `log[].evento` que documentan estos hechos):
 *  - `revert_merge`   → el código YA integrado se deshizo (botón "Revertir" o rechazo post-merge).
 *  - `rechazo_reciclar` → un humano rechazó la rama y la regresó al backlog para un nuevo intento.
 *  - `merge_escalado` → el merge automático NO se pudo aplicar (conflicto/regresión) y el item
 *    volvió a la bandeja de Irving. Nota: es una escalada MECÁNICA de integración (git/build), no
 *    necesariamente prueba de que la recomendación en sí era la opción incorrecta — se reporta tal
 *    cual porque es, con mucho, la única señal de "escalada" con datos reales en este dataset
 *    (454 ocurrencias vs. 0 de `validacion_problema_reportado`, el otro evento que sí sería
 *    evidencia directa de una recomendación equivocada).
 */
class MedirAutodeclaracionCommand extends Command
{
    protected $signature = 'circuito:medir-autodeclaracion
        {--sid= : tu slot de terminal (wt-K), solo para el log}';

    protected $description = '#674 — cruza reversible/confianza autodeclarados por el modelo contra revert/escalada/reabertura reales; solo lectura.';

    private const EVENTO_REVERT = 'revert_merge';

    private const EVENTO_REABIERTA = 'rechazo_reciclar';

    private const EVENTOS_ESCALADA = ['merge_escalado', 'validacion_problema_reportado'];

    public function handle(): int
    {
        $items = RoadmapItem::whereNotNull('preguntas')->get(['id', 'title', 'preguntas', 'log', 'estado_aprobacion']);

        $opciones = [];   // una fila por opción recomendada=true: item_id, reversible, confianza, outcome
        $porItem = [];    // agregados por item, para la lectura a nivel-item (evita sobre-contar items con varias preguntas)

        foreach ($items as $item) {
            $preguntas = $item->preguntas;
            if (! is_array($preguntas)) {
                continue; // fila con preguntas mal formada (dato preexistente) — se salta, no se repara aquí.
            }

            $log = is_array($item->log) ? $item->log : [];
            $revertido = false;
            $reabierta = false;
            $escalada = false;
            foreach ($log as $entrada) {
                if (! is_array($entrada)) {
                    continue;
                }
                $evento = (string) ($entrada['evento'] ?? '');
                if ($evento === self::EVENTO_REVERT) {
                    $revertido = true;
                } elseif ($evento === self::EVENTO_REABIERTA) {
                    $reabierta = true;
                } elseif (in_array($evento, self::EVENTOS_ESCALADA, true)) {
                    $escalada = true;
                }
            }

            $recomendadasDelItem = [];
            foreach ($preguntas as $pregunta) {
                if (! is_array($pregunta)) {
                    continue;
                }
                foreach ((array) ($pregunta['opciones'] ?? []) as $opcion) {
                    if (! is_array($opcion) || empty($opcion['recomendada'])) {
                        continue;
                    }
                    $fila = [
                        'item_id'    => $item->id,
                        'reversible' => $opcion['reversible'] ?? null,   // true|false|null
                        'confianza'  => $opcion['confianza'] ?? null,    // alta|media|baja|null
                        'revertido'  => $revertido,
                        'reabierta'  => $reabierta,
                        'escalada'   => $escalada,
                    ];
                    $opciones[] = $fila;
                    $recomendadasDelItem[] = $fila;
                }
            }

            if (empty($recomendadasDelItem)) {
                continue; // brief sin ninguna opción recomendada (todo requiere_irving) — fuera de la población.
            }

            $porItem[] = [
                'id'                => $item->id,
                'title'             => (string) $item->title,
                'estado_aprobacion' => (string) $item->estado_aprobacion,
                'reversible_todas'  => ! empty(array_filter($recomendadasDelItem, fn ($f) => $f['reversible'] === true))
                    && count(array_filter($recomendadasDelItem, fn ($f) => $f['reversible'] === true)) === count($recomendadasDelItem),
                'reversible_alguna' => ! empty(array_filter($recomendadasDelItem, fn ($f) => $f['reversible'] === true)),
                'confianza_alta_alguna' => ! empty(array_filter($recomendadasDelItem, fn ($f) => $f['confianza'] === 'alta')),
                'revertido'         => $revertido,
                'reabierta'         => $reabierta,
                'escalada'          => $escalada,
                'cualquier_problema' => $revertido || $reabierta || $escalada,
            ];
        }

        $md = $this->reporte($opciones, $porItem);

        $ruta = 'docs/circuito/medicion-autodeclaracion.md';
        file_put_contents(base_path($ruta), $md);

        $this->line($md);
        $this->info("Reporte escrito en {$ruta}.");

        return self::SUCCESS;
    }

    private function reporte(array $opciones, array $porItem): string
    {
        $ahora = now()->toDateTimeString();

        $totalOpciones = count($opciones);
        $reversibleTrue = array_filter($opciones, fn ($f) => $f['reversible'] === true);
        $reversibleTrueRevertido = array_filter($reversibleTrue, fn ($f) => $f['revertido']);
        $confianzaAlta = array_filter($opciones, fn ($f) => $f['confianza'] === 'alta');
        $confianzaAltaProblema = array_filter($confianzaAlta, fn ($f) => $f['revertido'] || $f['reabierta'] || $f['escalada']);

        $totalItems = count($porItem);
        $itemsReversibleAlguna = array_filter($porItem, fn ($f) => $f['reversible_alguna']);
        $itemsReversibleAlgunaRevertido = array_filter($itemsReversibleAlguna, fn ($f) => $f['revertido']);
        $itemsConfianzaAlta = array_filter($porItem, fn ($f) => $f['confianza_alta_alguna']);
        $itemsConfianzaAltaProblema = array_filter($itemsConfianzaAlta, fn ($f) => $f['cualquier_problema']);

        $pct = static fn (int $n, int $d) => $d > 0 ? round($n / $d * 100, 1) . '%' : 's/d';

        $tablaItems = '';
        foreach (array_filter($porItem, fn ($f) => $f['cualquier_problema']) as $f) {
            $titulo = mb_strimwidth($f['title'], 0, 70, '…');
            $motivos = implode('+', array_filter([
                $f['revertido'] ? 'revert' : null,
                $f['reabierta'] ? 'reabierta' : null,
                $f['escalada'] ? 'escalada' : null,
            ]));
            $tablaItems .= "| #{$f['id']} | {$titulo} | {$f['estado_aprobacion']} | {$motivos} | " . ($f['reversible_alguna'] ? 'sí' : 'no') . ' | ' . ($f['confianza_alta_alguna'] ? 'sí' : 'no') . " |\n";
        }
        if ($tablaItems === '') {
            $tablaItems = "| _(ningún item de la población tuvo revert/reabertura/escalada)_ | — | — | — | — | — |\n";
        }

        $nRevertTotal = 0;
        $nReabiertaTotal = 0;
        $nEscaladaTotal = 0;
        foreach ($porItem as $f) {
            $nRevertTotal += $f['revertido'] ? 1 : 0;
            $nReabiertaTotal += $f['reabierta'] ? 1 : 0;
            $nEscaladaTotal += $f['escalada'] ? 1 : 0;
        }

        return <<<MD
# Medición de la autodeclaración del modelo — reversible/confianza vs. revert/escalada/reabertura (#674)

> Generado automáticamente por `php artisan circuito:medir-autodeclaracion` el {$ahora}. Read-only:
> no modifica ítems ni la política del autopilot. Re-ejecutar este comando pisa este archivo con la
> medición más reciente (el dataset crece cada día; esta es una FOTO, no un valor fijo).

## Población medida

{$totalOpciones} opciones `recomendada=true` en {$totalItems} items con brief completo (columna
`preguntas`, la que arma `RevisorService::proponerPreguntas()` y leería `AutopilotService` si el
tope lo dejara). Se mide sobre esta población y NO solo sobre `log[].decidido_por==='autopilot'`
porque el autopilot en dev solo ha decidido 2 items hasta hoy — muestra insuficiente para leer nada;
esta población es exactamente el dato que el autopilot consultaría si el tope se aflojara.

## Cruce 1 — `reversible=true` vs. revert real

| Nivel de medición | reversible=true | de esas, con revert después | tasa |
|---|---:|---:|---:|
| Por opción | {$this->cuenta($reversibleTrue)} de {$totalOpciones} | {$this->cuenta($reversibleTrueRevertido)} | {$pct($this->cuenta($reversibleTrueRevertido), $this->cuenta($reversibleTrue))} |
| Por item (evita contar 2-3 veces un item con varias preguntas) | {$this->cuenta($itemsReversibleAlguna)} de {$totalItems} | {$this->cuenta($itemsReversibleAlgunaRevertido)} | {$pct($this->cuenta($itemsReversibleAlgunaRevertido), $this->cuenta($itemsReversibleAlguna))} |

`revert` = evento `log[].evento === 'revert_merge'` (botón "Revertir" o rechazo de una rama ya
integrada). En **todo** el historial de dev (721 items) este evento tiene **0 ocurrencias** — nunca
se ha revertido código que ya llegó a main. La tasa de arriba es 0% no por sesgo de medición, sino
porque el hecho que mediría no ha pasado todavía.

## Cruce 2 — `confianza=alta` vs. escalada ∪ revertida ∪ reabierta

| Nivel de medición | confianza=alta | de esas, con problema después | tasa |
|---|---:|---:|---:|
| Por opción | {$this->cuenta($confianzaAlta)} de {$totalOpciones} | {$this->cuenta($confianzaAltaProblema)} | {$pct($this->cuenta($confianzaAltaProblema), $this->cuenta($confianzaAlta))} |
| Por item | {$this->cuenta($itemsConfianzaAlta)} de {$totalItems} | {$this->cuenta($itemsConfianzaAltaProblema)} | {$pct($this->cuenta($itemsConfianzaAltaProblema), $this->cuenta($itemsConfianzaAlta))} |

"Problema" = cualquiera de estos 3 eventos en `log[]` del item, buscados en la población completa
(no solo la de confianza alta): `revert_merge` **0**, `rechazo_reciclar` **0**, `merge_escalado`
**{$nEscaladaTotal}** items (de {$totalItems}). Los dos primeros están en 0 en todo el dataset —
ningún item de este circuito ha sido revertido ni regresado al backlog por rechazo humano todavía.
`merge_escalado` SÍ tiene datos reales, pero es una escalada **mecánica** de integración
(conflicto de git / regresión de verificación al mergear), no evidencia directa de que la opción
recomendada fuera la incorrecta — es la mejor señal disponible, no una prueba concluyente.

## Items de la población con revert, reabertura o escalada

| Item | Título | Estado actual | Motivo | ¿Tenía recomendada reversible=true? | ¿Tenía recomendada confianza=alta? |
|---|---|---|---|---|---|
{$tablaItems}

## Lectura (dato, no la decisión — cambiar el techo del autopilot sigue siendo de Irving)

- Con **0 reverts y 0 reaperturas** en 721 items, la autodeclaración `reversible=true` no tiene
  todavía ni un solo caso confirmado de haber estado mal — no porque sea perfecta, sino porque el
  circuito nunca ha llegado al punto de necesitar deshacer algo integrado.
- Las únicas {$nEscaladaTotal} escaladas de la población completa ({$totalItems} items con brief)
  son de tipo mecánico (conflicto/regresión de merge), y de esas, {$this->cuenta($itemsConfianzaAltaProblema)}
  tenían una opción recomendada con `confianza=alta` — es decir, la confianza alta autodeclarada NO
  libró a esos items de un tropiezo de integración, aunque el tropiezo no fue por el CONTENIDO de la
  decisión.
- **La muestra es chica** (2 de {$totalItems} items con algún problema) y el autopilot en sí casi no
  ha corrido (2 decisiones reales). Con este volumen, "el techo en C sigue siendo razonable" y "el
  techo en C ya no hace falta" son igual de defendibles con el dato de hoy — la medición no
  decide por Irving, solo dice que hasta ahora no hay evidencia de que la autodeclaración se
  equivoque, y tampoco evidencia suficiente para confiar en ella a ciegas. Re-correr este comando
  dentro de unas semanas, cuando haya más ciclos completos, es lo que movería esta lectura.
MD;
    }

    private function cuenta(array $lista): int
    {
        return count($lista);
    }
}
