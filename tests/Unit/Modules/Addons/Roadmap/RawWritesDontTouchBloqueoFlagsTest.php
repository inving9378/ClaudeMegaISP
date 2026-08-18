<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

/**
 * Item roadmap #780 (Fase 2A.4b) — AUDITORÍA de las escrituras crudas `DB::table('roadmap_items')`
 * sobre las 4 banderas de bloqueo (`excluir_pool_automatico`, `esperando_merge_irving`,
 * `bloqueado_por_bucle`, `motivo_bloqueo`).
 *
 * El hook `RoadmapItem::booted()` (2A.4) traza todo cambio de esas banderas SOLO para quien
 * escribe por el modelo Eloquent. Las escrituras crudas (claim atómico del scheduler, renovación
 * de lease, migraciones de reconciliación) lo esquivan. Auditado a mano el 2026-08-18: NINGUNA
 * escritura cruda VIVA toca esas banderas — la única excepción histórica (migración
 * `2026_08_18_120000_limpia_flags_huerfanos_excluir_pool`) ya anota su propio rastro a mano en el
 * mismo UPDATE (escribe `log` junto con la bandera).
 *
 * Este test estático (grep + balanceo de paréntesis, SIN bootear Laravel) es el candado que
 * evita que el hueco se vuelva a abrir en silencio: cualquier `DB::table('roadmap_items')->
 * ...->update([...])` NUEVO que toque una de las 4 banderas debe, en el MISMO update, escribir
 * también `log` (rastro a mano) — si no, el test falla señalando archivo y statement.
 * ⚠️ Validar también con tinker si se toca este archivo (regla del proyecto: no correr el test
 * runner contra la BD).
 */
class RawWritesDontTouchBloqueoFlagsTest extends TestCase
{
    private const FLAG_COLUMNS = [
        'excluir_pool_automatico',
        'esperando_merge_irving',
        'bloqueado_por_bucle',
        'motivo_bloqueo',
    ];

    public function test_raw_db_table_updates_que_tocan_banderas_de_bloqueo_tambien_escriben_log(): void
    {
        $root = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap';
        $this->assertDirectoryExists($root);

        $offenders = [];

        foreach ($this->phpFiles($root) as $file) {
            $source = file_get_contents($file);
            foreach ($this->rawRoadmapItemsStatements($source) as $statement) {
                if (! str_contains($statement, '->update(')) {
                    continue; // solo nos interesan escrituras, no lecturas (get/first/pluck/exists/value)
                }

                $tocaFlag = false;
                foreach (self::FLAG_COLUMNS as $col) {
                    if (preg_match('/[\'"]' . preg_quote($col, '/') . '[\'"]\s*=>/', $statement)) {
                        $tocaFlag = true;
                        break;
                    }
                }
                if (! $tocaFlag) {
                    continue;
                }

                // Toca una bandera de bloqueo: EXIGE que el mismo UPDATE escriba 'log' (rastro a mano).
                if (! preg_match('/[\'"]log[\'"]\s*=>/', $statement)) {
                    $offenders[] = str_replace($root . '/', '', $file) . ": " . trim(substr($statement, 0, 200));
                }
            }
        }

        $this->assertSame([], $offenders, "Escritura(s) cruda(s) sobre roadmap_items tocan una bandera de "
            . "bloqueo SIN anotar 'log' en el mismo UPDATE (2A.4b). Cada UPDATE crudo que cambie "
            . "excluir_pool_automatico/esperando_merge_irving/bloqueado_por_bucle/motivo_bloqueo debe "
            . "trazarlo a mano (ver migración 2026_08_18_120000_limpia_flags_huerfanos_excluir_pool "
            . "como referencia) o migrarse al modelo Eloquent para heredar el hook de 2A.4:\n"
            . implode("\n", $offenders));
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

    /**
     * Extrae cada statement completo que arranca en `DB::table('roadmap_items')` (o comillas dobles)
     * hasta el `;` que cierra la cadena fluida, balanceando paréntesis/corchetes para no cortar a
     * mitad de un array multilínea.
     *
     * @return string[]
     */
    private function rawRoadmapItemsStatements(string $source): array
    {
        $statements = [];
        $pattern = "/DB::table\\(\\s*['\"]roadmap_items['\"]\\s*\\)/";
        if (! preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($m[0] as [, $offset]) {
            $depth = 0;
            $len = strlen($source);
            $end = $offset;
            for ($i = $offset; $i < $len; $i++) {
                $ch = $source[$i];
                if ($ch === '(' || $ch === '[') {
                    $depth++;
                } elseif ($ch === ')' || $ch === ']') {
                    $depth--;
                } elseif ($ch === ';' && $depth <= 0) {
                    $end = $i;
                    break;
                }
            }
            $statements[] = substr($source, $offset, $end - $offset + 1);
        }

        return $statements;
    }
}
