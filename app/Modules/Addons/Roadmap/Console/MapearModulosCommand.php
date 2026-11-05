<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #1050 (paso A de #1030) — llena `roadmap_items.modulo_slug` desde la etiqueta `modulo` con el mapa
 * de `config/roadmap_modulos.php`, y corrige las grafías listadas en `correcciones`.
 *
 * Escribe con `DB::table` a propósito: guardar con Eloquent dispararía los ganchos del modelo (cierre
 * en cascada, verificación de cierre) sobre items ya terminados, y aquí solo se toca la etiqueta.
 * Cada corrección de grafía queda en el log del item (`modulo_corregido`, con el valor anterior) y
 * `--revertir` la deshace desde ahí; el slug simplemente vuelve a NULL.
 */
class MapearModulosCommand extends Command
{
    protected $signature = 'roadmap:mapear-modulos
        {--aplicar : Escribe en la base (sin esta opción solo muestra lo que haría)}
        {--revertir : Deshace las correcciones de grafía y deja modulo_slug en NULL}';

    protected $description = 'Llena modulo_slug de la Hoja de ruta desde la etiqueta de módulo y corrige grafías (simulación por defecto).';

    public function handle(): int
    {
        if ($this->option('revertir')) {
            return $this->revertir();
        }

        $aplicar      = (bool) $this->option('aplicar');
        $correcciones = (array) config('roadmap_modulos.correcciones', []);
        $porSlug = [];
        $sinAlias = [];
        $corregidos = 0;
        $cambiosSlug = 0;

        foreach (DB::table('roadmap_items')->select('id', 'title', 'modulo', 'modulo_slug', 'log')->orderBy('id')->cursor() as $r) {
            $modulo = $r->modulo;
            $nuevoModulo = $modulo;
            // Comparación binaria: «flotas» se corrige, «Flotas» no.
            foreach ($correcciones as $de => $a) {
                if ($modulo !== null && $modulo === $de) {
                    $nuevoModulo = $a;
                }
            }
            $slug = RoadmapItem::slugDeModulo($nuevoModulo, $r->title) ?? $r->modulo_slug;
            if ($slug === null) {
                if (trim((string) $nuevoModulo) !== '') {
                    $sinAlias[$nuevoModulo] = ($sinAlias[$nuevoModulo] ?? 0) + 1;
                }
            } else {
                $porSlug[$slug] = ($porSlug[$slug] ?? 0) + 1;
            }

            $cambia = [];
            if ($nuevoModulo !== $modulo) {
                $corregidos++;
                $cambia['modulo'] = $nuevoModulo;
                $log = json_decode((string) $r->log, true) ?: [];
                $log[] = ['ts' => now()->toIso8601String(), 'por' => 'roadmap:mapear-modulos', 'evento' => 'modulo_corregido',
                    'antes' => $modulo, 'despues' => $nuevoModulo, 'motivo' => 'Corrección de grafía de la etiqueta (#1050).'];
                $cambia['log'] = json_encode($log, JSON_UNESCAPED_UNICODE);
            }
            if ($slug !== $r->modulo_slug) {
                $cambiosSlug++;
                $cambia['modulo_slug'] = $slug;
            }
            if ($aplicar && $cambia) {
                DB::table('roadmap_items')->where('id', $r->id)->update($cambia);
            }
        }

        arsort($porSlug);
        $this->table(['módulo', 'items'], collect($porSlug)->map(fn ($n, $s) => [$s, $n])->values()->all());
        if ($sinAlias) {
            $this->warn('Etiquetas sin alias (quedan con modulo_slug NULL): ' . collect($sinAlias)->map(fn ($n, $m) => "{$m} ({$n})")->implode(', '));
        }
        $this->line(($aplicar ? '' : '[simulación] ') . "Grafías corregidas: {$corregidos} · modulo_slug asignados o cambiados: {$cambiosSlug}");
        if (! $aplicar) {
            $this->line('Nada se escribió. Corre con --aplicar para guardar.');
        }

        return self::SUCCESS;
    }

    private function revertir(): int
    {
        $restaurados = 0;
        foreach (DB::table('roadmap_items')->where('log', 'like', '%modulo_corregido%')->select('id', 'modulo', 'log')->cursor() as $r) {
            $log = json_decode((string) $r->log, true) ?: [];
            $ultima = collect($log)->last(fn ($e) => ($e['evento'] ?? null) === 'modulo_corregido');
            if (! $ultima || ($ultima['despues'] ?? null) !== $r->modulo) {
                continue;
            }
            $log[] = ['ts' => now()->toIso8601String(), 'por' => 'roadmap:mapear-modulos', 'evento' => 'modulo_corregido_revertido',
                'antes' => $r->modulo, 'despues' => $ultima['antes'], 'motivo' => 'Reversión de la corrección de grafía (#1050).'];
            DB::table('roadmap_items')->where('id', $r->id)->update(['modulo' => $ultima['antes'], 'log' => json_encode($log, JSON_UNESCAPED_UNICODE)]);
            $restaurados++;
        }
        $limpios = DB::table('roadmap_items')->whereNotNull('modulo_slug')->update(['modulo_slug' => null]);
        $this->line("Grafías restauradas: {$restaurados} · modulo_slug vaciados: {$limpios}");

        return self::SUCCESS;
    }
}
