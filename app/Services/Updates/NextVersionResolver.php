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
 * FALLBACK A TAGS LOCALES (2026-09-08, opción 3 de Irving): el `git fetch` al remoto puede fallar
 * cuando el resolver corre como www-data (php-fpm), que no tiene acceso a la llave/known_hosts de
 * GitHub ("Host key verification failed"). Antes eso era fail-closed duro y dejaba el modal SIN
 * número. Ahora el fetch es best-effort: si falla, se cae a los TAGS LOCALES de git (misma historia
 * inmutable, sólo puede faltarle el último tag remoto) y se marca `confirmado_remoto=false` + un
 * `aviso` VISIBLE en pantalla. Esto NO es el fallback prohibido: lo prohibido era caer a la TABLA
 * `releases` (mutable, la que trajo el bug del V1.20). Los tags locales no retroceden.
 *
 * FAIL-CLOSED que se conserva: si NO hay ningún tag de versión del que calcular (ni remoto ni
 * local), se ABORTA con excepción — no se inventa un consecutivo. La red de seguridad final vive
 * aguas abajo: el paso git_tag del pipeline (skip_if_tag_exists) y el push (que corre como meganet,
 * sí llega a GitHub) rechazan un tag que ya exista en el remoto.
 */
class NextVersionResolver
{
    /** Patrón oficial de tag: V<major>.<build>-<dd.mm.yyyy>. `build` es el consecutivo. */
    private const PATRON = '/^V(\d+)\.(\d+)-\d{2}\.\d{2}\.\d{4}$/';

    private const MAJOR = 1;

    /**
     * @return array{build:int, label:string, max_detectado:int, origen:string, confirmado_remoto:bool, aviso:?string}
     * @throws RuntimeException si no hay NINGÚN tag de versión del que calcular (fail-closed)
     */
    public function resolver(): array
    {
        // best-effort: si el fetch falla (p.ej. www-data sin acceso a GitHub) NO abortamos aquí;
        // caemos a los tags locales y lo avisamos. El único fail-closed es "no hay tag alguno".
        $confirmadoRemoto = $this->intentarFetch();

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

        // FAIL-CLOSED (condición #2 de Irving): sin ningún tag válido, no se inventa un número.
        if ($maxBuild === 0) {
            Log::channel('single')->error('[release] no hay tags de versión (V*) para calcular el consecutivo', [
                'fetch_ok'       => $confirmadoRemoto,
                'tags_evaluados' => count($tags),
            ]);
            throw new RuntimeException(
                $confirmadoRemoto
                    ? 'No hay ningún tag de versión (V<major>.<build>-dd.mm.yyyy) del que calcular el siguiente número.'
                    : 'No se pudo confirmar con GitHub (git fetch falló) y no hay tags locales de versión para calcular el número. Se aborta para no inventar un consecutivo.'
            );
        }

        $siguiente = $maxBuild + 1;
        $label     = sprintf('V%d.%d-%s', self::MAJOR, $siguiente, now()->format('d.m.Y'));
        $aviso     = $confirmadoRemoto
            ? null
            : 'Número calculado desde los tags locales (no se pudo confirmar con GitHub). Se validará al publicar.';

        Log::channel('single')->info('[release] siguiente versión calculada desde tags git', [
            'max_detectado'    => $maxBuild,
            'origen'           => $origen,
            'build_asignado'   => $siguiente,
            'label'            => $label,
            'tags_evaluados'   => count($tags),
            'confirmado_remoto' => $confirmadoRemoto,
        ]);

        return [
            'build'             => $siguiente,
            'label'             => $label,
            'max_detectado'     => $maxBuild,
            'origen'            => $origen,
            'confirmado_remoto' => $confirmadoRemoto,
            'aviso'             => $aviso,
        ];
    }

    /** El build máximo ya publicado (para el guard anti-retroceso). Reusa el mismo fetch+parseo. */
    public function buildMaximoPublicado(): int
    {
        return $this->resolver()['max_detectado'];
    }

    /**
     * `git fetch --tags --force` best-effort. Devuelve true si sincronizó con el remoto, false si
     * falló (sin lanzar): el que llama decide caer a tags locales. Ya NO aborta aquí — el
     * fail-closed vive en resolver() y sólo se dispara si tampoco hay tags locales.
     */
    private function intentarFetch(): bool
    {
        $p = Process::fromShellCommandline('git fetch --tags --force 2>&1', base_path(), $this->env(), null, 60);
        $p->run();

        if (! $p->isSuccessful()) {
            $salida = trim($p->getOutput());
            Log::channel('single')->warning('[release] git fetch --tags falló; se usará el respaldo de tags locales', [
                'salida' => mb_substr($salida, 0, 500),
            ]);

            return false;
        }

        return true;
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
