<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * FASE 2A.5 — CANDADO DE COHERENCIA entre el scope de elegibilidad y el candado atómico del reclamo.
 *
 * POR QUÉ EXISTE. El mismo predicado —"¿puede un worker tomar este item?"— llegó a vivir en cinco
 * dialectos: el scope de Eloquent (`RoadmapItem::scopeElegibleParaPool`), el SQL crudo del UPDATE de
 * reclamo (`RoadmapCircuitoService::claimNextParalelo`), el `preg_match` sobre el título
 * (`tieneRotuloBloqueo`), la copia a mano de `SupervisorService` y la lógica de la Torre en Vue.
 * Cada vez que uno cambió, los otros se quedaron atrás; la del reclamo es la CARA, porque decide
 * qué toca un worker: una deriva ahí no es un tablero mudo, es una terminal trabajando sobre algo
 * que no debía.
 *
 * 2A.3 centralizó (`tieneFrenoHumano` + `sqlSinFrenoHumano`) y 2A.5 terminó de unificar
 * (`RoadmapItem::sqlElegibleParaPool`). Este test es el candado que impide que se vuelvan a separar:
 * si alguien toca uno y no el otro, truena aquí en vez de que lo descubramos en tres semanas
 * persiguiendo por qué una terminal tomó un item frenado.
 *
 * NO toca la BD: compila el SQL de los dos caminos contra una conexión sin PDO. La comparación
 * sobre el CONJUNTO REAL de items vive en `php artisan circuito:coherencia-pool` (read-only).
 */
class PoolGuardCoherenceTest extends TestCase
{
    /** Literales que delatan una re-implementación a mano del predicado de freno. */
    private const LITERALES_PROHIBIDOS = [
        "'%[BLOCKED-%'",
        "'%[PARKED-%'",
        "'excluir_pool_automatico'",
        "'esperando_merge_irving'",
        "'origen_bloqueo'",
    ];

    /**
     * El scope y el candado atómico deben compilar EXACTAMENTE el mismo WHERE (y los mismos
     * bindings). Son dos caminos distintos a propósito —el scope filtra el SELECT, el candado
     * re-verifica dentro del UPDATE— pero tienen que seleccionar el mismo conjunto.
     */
    public function test_scope_y_candado_atomico_compilan_el_mismo_sql_y_bindings(): void
    {
        $scope   = $this->builder();
        (new RoadmapItem)->scopeElegibleParaPool($scope);

        $reclamo = $this->builder();
        $reclamo->where(fn ($q) => RoadmapCircuitoService::guardReclamoAtomico($q));

        $this->assertSame(
            $scope->toSql(),
            $reclamo->toSql(),
            "El scope `RoadmapItem::scopeElegibleParaPool` y el candado atómico del reclamo "
            . "(`RoadmapCircuitoService::guardReclamoAtomico`, usado por `claimNextParalelo`) ya NO "
            . "seleccionan el mismo conjunto. Los dos deben delegar en "
            . "`RoadmapItem::sqlElegibleParaPool()`: si el reclamo tiene que ser MÁS estricto, el "
            . "guard extra va DESPUÉS de la delegación y este test se actualiza a propósito."
        );

        $this->assertSame($scope->getBindings(), $reclamo->getBindings(),
            'Mismo SQL pero bindings distintos: uno de los dos cambió un valor comparado '
            . '(p.ej. el literal del rótulo o el valor de `origen_bloqueo`).');
    }

    /**
     * El UPDATE de `claimNextParalelo` NO debe enumerar banderas de freno a mano. Es exactamente
     * cómo se quedó atrás del guard las veces anteriores: alguien agrega una condición al scope y
     * el candado, que repetía las suyas inline, sigue reclamando lo que el scope ya excluía.
     */
    public function test_el_candado_atomico_no_enumera_banderas_de_freno_a_mano(): void
    {
        $cuerpo = $this->cuerpoDeMetodo(
            $this->raiz() . '/app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php',
            'claimNextParalelo'
        );

        $encontrados = [];
        foreach (self::LITERALES_PROHIBIDOS as $lit) {
            if (str_contains($this->sinComentarios($cuerpo), $lit)) {
                $encontrados[] = $lit;
            }
        }

        $this->assertSame([], $encontrados,
            "`claimNextParalelo()` volvió a enumerar banderas de freno a mano: "
            . implode(', ', $encontrados) . ". El candado atómico debe filtrar SOLO por "
            . "`static::guardReclamoAtomico(\$q)` → `RoadmapItem::sqlElegibleParaPool()`, para que "
            . "cualquier freno nuevo lo herede sin que nadie se acuerde de copiarlo aquí.");
    }

    /**
     * Ningún otro consumidor del pool puede re-implementar el rótulo a mano. El fallback legacy
     * (`title LIKE '%[BLOCKED-%'`) vive en UN solo sitio —`RoadmapItem::sqlSinFrenoHumano()`— para
     * que el día que `contarFallbackRotulo()` marque 0 se retire de un tirón y no queden copias
     * frenando (o dejando de frenar) por su cuenta.
     *
     * Excepciones legítimas: el propio modelo (definición única) y las migraciones (fotos
     * históricas de un momento, no guards vivos).
     */
    public function test_ningun_otro_consumidor_reimplementa_el_rotulo_a_mano(): void
    {
        $raiz     = $this->raiz() . '/app/Modules/Addons/Roadmap';
        $infractores = [];

        foreach ($this->phpFiles($raiz) as $file) {
            $rel = str_replace($raiz . '/', '', $file);
            if ($rel === 'Models/RoadmapItem.php' || str_starts_with($rel, 'migrations/')) {
                continue;
            }
            $src = $this->sinComentarios(file_get_contents($file));
            // Sólo la forma de GUARD: `'title', 'not like', '%[BLOCKED-%'` = "excluye lo frenado".
            // El `like` POSITIVO se deja pasar a propósito: buscar los rotulados es lo contrario a
            // frenar por rótulo — es lo que hace `circuito:backfill-bloqueos` para sellarlos en
            // `origen_bloqueo` (y de paso barre `comentarios_claude`, que el fragmento no cubre).
            if (preg_match("/['\"]title['\"]\s*,\s*['\"]not like['\"]\s*,\s*['\"]%\[(BLOCKED|PARKED)-%['\"]/", $src)) {
                $infractores[] = $rel;
            }
        }

        $this->assertSame([], $infractores,
            "Estos archivos filtran por el rótulo `[BLOCKED-]`/`[PARKED-]` a mano en vez de usar "
            . "`RoadmapItem::sqlSinFrenoHumano()` / `sqlElegibleParaPool()`:\n- "
            . implode("\n- ", $infractores)
            . "\nEs la misma deriva de 2A.3: cinco copias del mismo predicado, cada una envejeciendo aparte.");
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────

    /** Query builder MySQL sin PDO: `toSql()` no abre conexión, así que el test nunca toca la BD. */
    private function builder(): \Illuminate\Database\Query\Builder
    {
        $conn = new \Illuminate\Database\MySqlConnection(
            fn () => throw new \RuntimeException('El test de coherencia NUNCA debe abrir conexión.'),
            'sin_pdo'
        );

        return $conn->query()->from('roadmap_items');
    }

    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    /** Cuerpo fuente de un método, balanceando llaves desde su `{` de apertura. */
    private function cuerpoDeMetodo(string $file, string $metodo): string
    {
        $src = file_get_contents($file);
        $this->assertIsString($src, "No pude leer {$file}");

        $pos = strpos($src, "function {$metodo}(");
        $this->assertNotFalse($pos, "No encontré `{$metodo}()` en {$file} — ¿se renombró? "
            . 'Si el método cambió de nombre, actualiza este candado en el mismo commit.');

        $inicio = strpos($src, '{', $pos);
        $depth  = 0;
        $len    = strlen($src);
        for ($i = $inicio; $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $inicio, $i - $inicio + 1);
                }
            }
        }

        $this->fail("No pude delimitar el cuerpo de `{$metodo}()` en {$file}.");
    }

    /** Quita comentarios `//`, `#` y `/* *\/` para que un comentario explicativo no dispare el guard. */
    private function sinComentarios(string $src): string
    {
        $out = '';
        foreach (token_get_all("<?php\n" . $src) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($t) ? $t[1] : $t;
        }

        return $out;
    }

    /** @return string[] */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
