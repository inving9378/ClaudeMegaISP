<?php

use App\Services\BackupDb\MysqldumpEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * #9991089 — Fase 1 de la deuda dejada por #9991082 (`subs.filter is not a function`) y
 * catalogada por #9991084.
 *
 * `roadmap_items.subtasks` hoy carga dos cosas incompatibles bajo el mismo JSON: la lista de
 * sub-tareas de la UI (`[{title,completed,completed_at}]`) y, cuando el item nace vía
 * `circuito:sub-item`/`DependenciaGate` (llave `DependenciaGate::META_KEY = 'descomposicion'`),
 * el objeto `{"descomposicion":{"depende_de":[...]}}`. #9991082 ya mitiga esto EN MEMORIA
 * (`RoadmapController::normalizarSubtasks()`/`separarSubtasks()`), pero la columna sigue
 * mezclada en la BD. Esta migración las separa de raíz: añade `descomposicion` JSON NULLABLE y
 * mueve ahí la metadata de los items ya nacidos con ese formato, dejando `subtasks='[]'`.
 *
 * NO cablea lectores (`normalizarSubtasks()`, `DependenciaGate`) a la columna nueva — eso es
 * Fase 2 (sub-item hermano, depende de que esta migración esté mergeada).
 */
return new class extends Migration
{
    /** Mismo valor que DependenciaGate::META_KEY (Services/Descomposicion/DependenciaGate.php:31). */
    private const COLUMNA = 'descomposicion';

    public function up(): void
    {
        if (! Schema::hasTable('roadmap_items')) {
            return;
        }

        if (Schema::hasColumn('roadmap_items', self::COLUMNA)) {
            // Ya aplicada (re-ejecución) — no repetir snapshot ni backfill.
            return;
        }

        $this->snapshotDeSeguridad();

        Schema::table('roadmap_items', function (Blueprint $t) {
            $t->json(self::COLUMNA)->nullable()->after('subtasks');
        });

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roadmap_items') || ! Schema::hasColumn('roadmap_items', self::COLUMNA)) {
            return;
        }

        // Reconstruye el formato viejo {"descomposicion": {...}} en subtasks antes de dropear,
        // para las filas que tengan la columna nueva poblada.
        DB::table('roadmap_items')
            ->whereNotNull(self::COLUMNA)
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $meta = json_decode($row->{self::COLUMNA}, true);
                    if (! is_array($meta)) {
                        continue;
                    }

                    DB::table('roadmap_items')->where('id', $row->id)->update([
                        'subtasks' => json_encode([self::COLUMNA => $meta], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });

        Schema::table('roadmap_items', function (Blueprint $t) {
            $t->dropColumn(self::COLUMNA);
        });
    }

    /**
     * Vuelca SOLO roadmap_items a storage/app/circuito/ antes del backfill. Credenciales desde
     * config('database.connections.mysql.*') vía MysqldumpEngine (mismo motor que
     * backup_db:process) — nunca hardcodeadas ni logueadas.
     */
    private function snapshotDeSeguridad(): void
    {
        $dir = storage_path('app/circuito');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/backup-roadmap_items-pre-descomposicion-' . now()->format('Ymd_His') . '.sql';

        try {
            (new MysqldumpEngine())->dumpToFile($file, ['tables' => ['roadmap_items']]);
        } catch (\Throwable $e) {
            Log::error('[migración #9991089] snapshot de seguridad de roadmap_items falló: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Idempotente: solo toca filas donde `subtasks` es un OBJETO (no una lista) con una llave
     * `descomposicion` array — mismo criterio de detección que
     * RoadmapController::separarSubtasks() (Controllers/RoadmapController.php:3657-3669).
     */
    private function backfill(): void
    {
        DB::table('roadmap_items')
            ->whereNotNull('subtasks')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $decoded = json_decode($row->subtasks, true);
                    if (! is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
                        continue;
                    }

                    $meta = $decoded[self::COLUMNA] ?? null;
                    if (! is_array($meta)) {
                        continue;
                    }

                    DB::table('roadmap_items')->where('id', $row->id)->update([
                        self::COLUMNA => json_encode($meta, JSON_UNESCAPED_UNICODE),
                        'subtasks' => '[]',
                    ]);
                }
            });
    }
};
