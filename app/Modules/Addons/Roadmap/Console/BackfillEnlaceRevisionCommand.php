<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * #1006 — BACKFILL de `enlace_revision` en items ya CERRADOS (estado_aprobacion=completado) que lo
 * tienen vacío, con la pantalla de RESPALDO por módulo (tier 2 del spec de #432 ADENDA B): el mismo
 * `module_sidebar_config` que `RoadmapController::moduloUrl()` ya usa en vivo como fallback cuando
 * `enlace_revision` viene vacío (bandeja, integración, validación funcional).
 *
 * Este comando NO inventa URLs específicas de pantalla (esa sería tier 1: el ejecutor la declara al
 * cerrar). Solo escribe el respaldo de módulo, y SOLO cuando la URL resuelta verifica contra una ruta
 * GET registrada de verdad (`rutaExiste()`) — así un `module_sidebar_config` con un dato viejo/roto
 * no contamina el backfill.
 *
 * DRY-RUN por defecto: escribe SOLO con `--apply`. Idempotente: solo toca items con
 * `enlace_revision` vacío, así que una re-corrida no pisa nada ya poblado (por este backfill o a mano).
 */
class BackfillEnlaceRevisionCommand extends Command
{
    protected $signature = 'circuito:backfill-enlace-revision {--apply : escribe (por defecto solo reporta)}';

    protected $description = 'Backfill de enlace_revision (tier 2: URL de respaldo del módulo) en items completados que lo tienen vacío (#1006).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $moduloUrlMap = $this->moduloUrlMap();

        $items = RoadmapItem::query()
            ->where('estado_aprobacion', 'completado')
            ->where(function ($q) {
                $q->whereNull('enlace_revision')->orWhere('enlace_revision', '');
            })
            // Prioridad del spec: primero los que están (o van a re-entrar) en la cola de validación
            // de Irving; el resto es housekeeping de menor urgencia, pero se backfillea igual.
            ->orderByDesc('pendiente_validacion_irving')
            ->orderBy('id')
            ->get(['id', 'title', 'modulo', 'enlace_revision', 'pendiente_validacion_irving']);

        if ($items->isEmpty()) {
            $this->info('Backfill de enlace_revision: nada que hacer.');

            return self::SUCCESS;
        }

        $plan = [];
        foreach ($items as $it) {
            $base = trim(explode('/', (string) $it->modulo)[0]);
            $key  = $this->normalizeModulo($base);
            $url  = $moduloUrlMap[$key] ?? null;

            if ($url !== null && ! $this->rutaExiste($url)) {
                // module_sidebar_config trae una URL que ya no resuelve a ninguna ruta GET viva:
                // no se inventa, se deja fuera del backfill (candidato aparte para auditar el catálogo).
                $url = null;
            }

            $plan[] = [
                'id'       => $it->id,
                'modulo'   => (string) $it->modulo,
                'url'      => $url,
                'resuelve' => $url !== null,
                'title'    => mb_substr((string) $it->title, 0, 50),
            ];
        }

        $resuelven = collect($plan)->where('resuelve', true)->count();

        $this->newLine();
        $this->info('BACKFILL DE enlace_revision (tier 2 — respaldo por módulo) ' . ($apply ? '(APLICANDO)' : '(DRY-RUN — no escribe)'));
        $this->line('  candidatos (completado, enlace_revision vacío) : ' . count($plan));
        $this->line("  → resuelven a URL de módulo verificada          : {$resuelven}");
        $this->line('  → sin módulo mapeado / ruta no verifica         : ' . (count($plan) - $resuelven) . '   (quedan sin tocar)');
        $this->newLine();

        $this->table(
            ['item', 'módulo', 'enlace_revision (tier 2)', 'título'],
            collect($plan)->where('resuelve', true)->map(fn ($p) => [
                '#' . $p['id'], $p['modulo'], $p['url'], $p['title'],
            ])->all()
        );

        if (! $apply) {
            $this->comment('Dry-run. Vuelve a correr con --apply para escribir.');

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($plan as $p) {
            if (! $p['resuelve']) {
                continue;
            }
            // Por el MODELO a propósito (igual que circuito:backfill-bloqueos): guarda con los
            // observers/casts normales del item, no una escritura masiva ciega.
            $it = RoadmapItem::find($p['id']);
            if (! $it || trim((string) $it->enlace_revision) !== '') {
                continue;   // se pobló entre el plan y aquí (a mano o por otra corrida) — no se pisa.
            }
            $it->enlace_revision = $p['url'];
            $it->save();
            $n++;
        }

        $this->info("Backfill aplicado a {$n} item(s).");

        DB::table('settings')->updateOrInsert(
            ['key' => 'circuito_backfill_enlace_revision_at'],
            ['value' => now()->toDateTimeString()]
        );

        return self::SUCCESS;
    }

    /** Mapa normalizado module_key→sidebar_url — MISMA fuente que `RoadmapController::moduloUrl()`. */
    private function moduloUrlMap(): array
    {
        $map = [];
        $rows = DB::table('module_sidebar_config')
            ->whereNotNull('sidebar_url')->where('sidebar_url', '!=', '')
            ->get(['module_key', 'sidebar_url']);
        foreach ($rows as $row) {
            $k = $this->normalizeModulo((string) $row->module_key);
            if ($k !== '') {
                $map[$k] = $row->sidebar_url;
            }
        }

        return $map;
    }

    private function normalizeModulo(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($s)));
    }

    /**
     * ¿La URL de respaldo resuelve a una ruta GET realmente registrada? Compara el path contra
     * `Route::getRoutes()` (match literal o por patrón, para rutas con segmentos `{id}` etc.).
     * Conservador: si no puede probarlo, la trata como que NO existe (false) — nunca inventa.
     */
    private function rutaExiste(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = trim($route->uri(), '/');
            if ($uri === $path) {
                return true;
            }
            $pattern = '#^' . preg_replace('/\{[^}]+\}/', '[^/]+', preg_quote($uri, '#')) . '$#';
            if (@preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
