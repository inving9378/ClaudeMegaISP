<?php

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * Item #177 — Reconstruye lo recuperable de la Hoja de Ruta perdida en el
 * incidente P0 del 2026-08-24: roadmap_items se restauro desde un snapshot
 * del 29-jun con 168 filas, y todo lo creado entre el 29-jun y el 22-ago se
 * perdio (se veian ids por encima de 1050 en los logs del circuito).
 *
 * Fuente de datos: database/files/roadmap_reconstruccion_item177.json,
 * generado parseando `git log --all --grep="Integra circuito #"` — cada item
 * que llego a mergearse a main dejo un commit "Integra circuito #N (rama) a
 * main" con el id original y el slug de su rama. Eso permite reconstruir,
 * de forma 100% factual (sin juicio de negocio), id original + titulo
 * aproximado (derivado del slug de la rama, truncado igual que la rama) +
 * commit + fecha de integracion para 326 items que SI llegaron a
 * completarse y mergearse (el codigo ya vive en main, solo faltaba el
 * registro).
 *
 * Los items que se perdieron SIN llegar a mergear (pendientes, rechazados,
 * escalados, abandonados a medio camino) no tienen una fuente factual
 * equivalente en git — reconstruirlos exige leer bitacoras sueltas en
 * /home/meganet/circuito/logs/ e inferir su estado final, lo cual es juicio,
 * no un hecho verificable. Eso se dejo registrado como sub-item aparte del
 * #177 en vez de improvisarlo aqui.
 *
 * Todo se inserta como fila NUEVA (id nuevo, autoincrement) — NO se reusan
 * los ids originales, para no arriesgar colision con ids ya reasignados a
 * items reales creados despues del incidente. El id original queda en el
 * titulo, la descripcion y comentarios_claude para trazabilidad.
 *
 * Marcados 'done'/'completado' + excluir_pool_automatico=true: son historial,
 * nunca deben volver a la cola de trabajo.
 *
 * Aditiva e idempotente (firstOrCreate por merge_commit): segura de correr
 * mas de una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('files/roadmap_reconstruccion_item177.json');

        if (!file_exists($path)) {
            return;
        }

        $items = json_decode(file_get_contents($path), true) ?: [];

        foreach ($items as $row) {
            $origId = $row['orig_id'];
            $mergeCommit = $row['last_commit'];
            $tituloBase = $row['title'];
            $modulo = $row['modulo_inferido'] ?? null;
            $fechaIntegracion = Carbon::parse($row['last_date']);

            $commitCorto = substr($mergeCommit, 0, 8);

            RoadmapItem::firstOrCreate(
                ['merge_commit' => $mergeCommit],
                [
                    'title' => "[Reconstruido #{$origId}] {$tituloBase}",
                    'description' => "Item reconstruido tras el incidente P0 del 2026-08-24 ".
                        "(roadmap_items restaurado desde snapshot del 29-jun; se perdio el ".
                        "registro original de este item, creado y mergeado entre el 29-jun y ".
                        "el 22-ago). Titulo aproximado, derivado del slug de la rama de git — el ".
                        "texto original (description/prompt) no es recuperable. El codigo de este ".
                        "item SI vive en main (se mergeo exitosamente); ver commit de integracion: ".
                        "{$row['subject']}.",
                    'modulo' => $modulo,
                    'status' => 'done',
                    'estado_aprobacion' => 'completado',
                    'merge_commit' => $mergeCommit,
                    'branch' => null,
                    'completed_at' => $fechaIntegracion,
                    'excluir_pool_automatico' => true,
                    'reporte_coloquial' => "Reconstruido desde git log: item #{$origId}, mergeado a ".
                        "main el {$fechaIntegracion->toDateString()} (commit {$commitCorto}). Sin ".
                        "descripcion original recuperable — el codigo real ya vive en main.",
                    'sin_ui' => true,
                    'sin_ui_motivo' => 'Registro historico reconstruido de un incidente de perdida '.
                        'de datos (#177); no es una feature con pantalla propia que enlazar.',
                    'comentarios_claude' => "Reconstruido por item #177 (circuito wt-6, 2026-08-26) ".
                        "desde git log. Id original perdido: #{$origId}. Commit de integracion: ".
                        "{$mergeCommit}. Fecha: {$fechaIntegracion->toDateTimeString()}.",
                ]
            );
        }
    }

    public function down(): void
    {
        // Intencionalmente vacio. Reponer un dato reconstruido de un incidente
        // de perdida de datos por su naturaleza no debe des-hacerse con un
        // rollback ciego (volveria a perder la unica reconstruccion factual
        // disponible). Si algun row reconstruido resulta incorrecto, corregirlo
        // o borrarlo puntualmente, no en bloque.
    }
};
