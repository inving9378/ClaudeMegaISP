<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * FUENTE ÚNICA DE VERDAD del siguiente consecutivo de versión (release pipeline).
 *
 * El 2026-09-08 dev emitió V1.20 cuando producción ya tenía V1.32: el número se calculaba desde la
 * tabla `releases` de dev (`ReleaseController::nextVersion()` con `Release::pluck`), que había
 * perdido los builds 16–32 (salto id=68 V1.15 26-jun → id=69 V1.16 04-sep, consistente con una
 * pérdida de filas). Al recalcular desde una tabla que sólo llegaba a V1.15, el consecutivo
 * RETROCEDIÓ y prod —que compara bien— se creyó actualizado y nunca vio el release.
 *
 * La cura: el consecutivo sale de los TAGS DE GIT, que son la historia real e inmutable de lo
 * publicado, nunca de una tabla que un PITR o un truncado puede regresar hacia atrás.
 *
 * FAIL-CLOSED: si no se puede consultar el remoto (sin red, fetch falla), se ABORTA con excepción.
 * Prohibido caer a un contador local de respaldo — ése es justo el fallo que trajo esto.
 */
class NextVersionResolver
{
    /** Patrón oficial de tag: V<major>.<build>-<dd.mm.yyyy>. `build` es el consecutivo. */
    private const PATRON = '/^V(\d+)\.(\d+)-\d{2}\.\d{2}\.\d{4}$/';

    private const MAJOR = 1;

    /**
     * @return array{build:int, label:string, max_detectado:int, origen:string}
     * @throws RuntimeException si no se puede sincronizar con el remoto (fail-closed)
     */
    public function resolver(): array
    {
        $this->fetchTagsOAbortar();

        $tags = $this->tagsLocales();
        $maxBuild = 0;
        $origen   = '(ninguno)';

        foreach ($tags as $tag) {
            if (preg_match(self::PATRON, $tag, $m)) {
                $build = (int) $m[2];   // ENTERO, nunca float ni string
                if ($build > $maxBuild) {
                    $maxBuild = $build;
                    $origen   = $tag;
                }
            }
        }

        $siguiente = $maxBuild + 1;
        $label     = sprintf('V%d.%d-%s', self::MAJOR, $siguiente, now()->format('d.m.Y'));

        Log::channel('single')->info('[release] siguiente versión calculada desde tags git', [
            'max_detectado' => $maxBuild,
            'origen'        => $origen,
            'build_asignado' => $siguiente,
            'label'         => $label,
            'tags_evaluados' => count($tags),
        ]);

        return [
            'build'         => $siguiente,
            'label'         => $label,
            'max_detectado' => $maxBuild,
            'origen'        => $origen,
        ];
    }

    /** El build máximo ya publicado (para el guard anti-retroceso). Reusa el mismo fetch+parseo. */
    public function buildMaximoPublicado(): int
    {
        return $this->resolver()['max_detectado'];
    }

    /**
     * `git fetch --tags --force`. Si falla, ABORTA: sin la historia real del remoto no se puede
     * garantizar que el número no retroceda, y adivinar es exactamente lo que se viene a evitar.
     */
    private function fetchTagsOAbortar(): void
    {
        $p = Process::fromShellCommandline('git fetch --tags --force 2>&1', base_path(), $this->env(), null, 60);
        $p->run();

        if (! $p->isSuccessful()) {
            $salida = trim($p->getOutput());
            Log::channel('single')->error('[release] git fetch --tags falló: no se puede calcular la versión', [
                'salida' => mb_substr($salida, 0, 500),
            ]);
            throw new RuntimeException(
                'No se pudo sincronizar los tags con el remoto (git fetch falló). '
                . 'El release se aborta para no emitir un número que pueda retroceder. '
                . 'Verifica la conexión con el remoto y reintenta. Detalle: ' . mb_substr($salida, 0, 200)
            );
        }
    }

    /** @return string[] */
    private function tagsLocales(): array
    {
        $p = Process::fromShellCommandline("git tag -l 'V*'", base_path(), $this->env(), null, 30);
        $p->run();

        return array_values(array_filter(array_map('trim', explode("\n", $p->getOutput()))));
    }

    private function env(): array
    {
        return [
            'PATH'               => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => '/root',
            'LC_ALL'             => 'C',
            'LANG'               => 'C',
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
        ];
    }
}
