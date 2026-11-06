<?php

namespace App\Services\Deploy;

/**
 * Commits que no pueden llegar a producción hasta que alguien los valide.
 *
 * La lista vive en `deploy/release-retenidos.txt`, versionada: liberar un commit es borrar su línea
 * y dejar constancia en git de quién lo hizo. Producción solo ve lo publicado en GitHub Releases, así
 * que el candado va en las dos puertas que publican: el pipeline de emisión y
 * `releases:publish-github`. Un commit se detecta en la historia de la versión o traído por
 * cherry-pick (el procedimiento habitual de versión parcial).
 */
class CommitsRetenidos
{
    public const ARCHIVO = 'deploy/release-retenidos.txt';

    /**
     * @return array<int,array{hash:string,item:string,motivo:string}>
     */
    public static function lista(string $archivo): array
    {
        if (!is_file($archivo)) {
            return [];
        }

        $retenidos = [];
        foreach (file($archivo, FILE_IGNORE_NEW_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#') {
                continue;
            }
            $partes = preg_split('/\s+/', $linea, 3);
            $retenidos[] = ['hash' => $partes[0], 'item' => $partes[1] ?? '', 'motivo' => $partes[2] ?? ''];
        }

        return $retenidos;
    }

    /**
     * Los retenidos que trae $ref. Si un retenido no se puede evaluar, se reporta igual: ante la duda
     * esto detiene la publicación. Un $ref inexistente devuelve vacío: ahí responde el guard de cada
     * puerta con su propio mensaje.
     *
     * @param  array<int,array{hash:string,item:string,motivo:string}>  $retenidos
     * @return array<int,array{hash:string,item:string,motivo:string,via:string}>
     */
    public static function en(string $repo, string $ref, array $retenidos): array
    {
        if ($retenidos === [] || self::git($repo, ['rev-parse', '--verify', '-q', "{$ref}^{commit}"])[1] !== 0) {
            return [];
        }

        $encontrados = [];
        $parchesDeLaVersion = null;

        foreach ($retenidos as $retenido) {
            [, $codigo] = self::git($repo, ['merge-base', '--is-ancestor', $retenido['hash'], $ref]);

            if ($codigo === 0) {
                $encontrados[] = $retenido + ['via' => 'historia'];
                continue;
            }
            if ($codigo !== 1) {
                $encontrados[] = $retenido + ['via' => 'no se pudo evaluar'];
                continue;
            }

            $parchesDeLaVersion ??= self::parchesDesdeElTagAnterior($repo, $ref);
            if (in_array(self::parche($repo, $retenido['hash']), $parchesDeLaVersion, true)) {
                $encontrados[] = $retenido + ['via' => 'cherry-pick'];
            }
        }

        return $encontrados;
    }

    /**
     * @param  array<int,array{hash:string,item:string,motivo:string,via:string}>  $encontrados
     */
    public static function mensaje(string $ref, array $encontrados): string
    {
        $lineas = array_map(
            fn (array $r) => '  ' . substr($r['hash'], 0, 12) . " {$r['item']} ({$r['via']}): {$r['motivo']}",
            $encontrados
        );

        return "«{$ref}» contiene commits retenidos para release:\n" . implode("\n", $lineas)
            . "\nPara liberar uno, quien lo valida borra su línea en " . self::ARCHIVO . ' y lo commitea.';
    }

    /** @return string[] */
    private static function parchesDesdeElTagAnterior(string $repo, string $ref): array
    {
        [$tag, $codigo] = self::git($repo, ['describe', '--tags', '--abbrev=0', "{$ref}^"]);
        $rango = $codigo === 0 ? trim($tag) . "..{$ref}" : $ref;

        // Una sola tubería: entre el último tag y main puede haber cientos de commits.
        $comando = 'git -C ' . escapeshellarg($repo) . " log -p --no-merges --pretty=format:'commit %H' "
            . escapeshellarg($rango) . ' 2>/dev/null | git patch-id --stable 2>/dev/null';

        $parches = [];
        foreach (array_filter(explode("\n", (string) shell_exec($comando))) as $linea) {
            $parches[] = explode(' ', $linea)[0];
        }

        return $parches;
    }

    private static function parche(string $repo, string $hash): string
    {
        $comando = 'git -C ' . escapeshellarg($repo) . ' show ' . escapeshellarg($hash)
            . ' --format= 2>/dev/null | git patch-id --stable 2>/dev/null';

        return explode(' ', trim((string) shell_exec($comando)))[0] ?? '';
    }

    /** @return array{0:string,1:int} salida y código de salida */
    private static function git(string $repo, array $argumentos): array
    {
        $comando = 'git -C ' . escapeshellarg($repo) . ' ' . implode(' ', array_map('escapeshellarg', $argumentos)) . ' 2>/dev/null';
        exec($comando, $salida, $codigo);

        return [implode("\n", $salida), $codigo];
    }
}
