<?php

namespace App\Modules\Addons\LogsTerceros\Services;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Lee como root los archivos que el usuario del servidor web no puede leer (p. ej. lo de
 * /home/<usuario> o /root). Escala igual que los add-ons (deploy/system/lib.sh, `as_root`):
 *   1. ya somos root                              → directo
 *   2. el usuario está en el grupo sudo:
 *      a. sudo funciona sin contraseña            → sudo -n
 *      b. si no                                   → contraseña DEL USUARIO → sudo -S
 *   3. no está en el grupo sudo (www-data normal) → contraseña de ROOT → su (deploy/system/su-pty.py)
 * La contraseña llega por la página en cada pedido y se le pasa a sudo/su por la entrada
 * estándar: no se guarda en disco, ni en la sesión, ni en el log, ni en argumentos.
 *
 * Como root solo corre bin/leer-como-root.php, que lee con LectorLogs (mismas reglas: nunca
 * .env, llaves, /proc…) y deja el resultado en storage/app/logs-terceros/ para este usuario.
 */
class LectorComoRoot
{
    private ?array $via = null;

    /**
     * @param  array<string,string>  $entorno  variables extra para el proceso (pruebas: MEGAISP_SU_BIN)
     * @param  string|null  $forzarVia  'root'|'sudo-n'|'sudo'|'su' (pruebas)
     */
    public function __construct(private ?string $raiz = null, private array $entorno = [], private ?string $forzarVia = null)
    {
        $this->raiz = rtrim($raiz ?? base_path(), '/');
    }

    /**
     * Cómo se conseguiría root y de quién se pide la contraseña.
     *
     * @return array{via: string, pide: ?string}
     */
    public function via(): array
    {
        if ($this->via !== null) {
            return $this->via;
        }
        $via = $this->forzarVia;
        if ($via === null) {
            if (posix_geteuid() === 0) {
                $via = 'root';
            } elseif ($this->enGrupoSudo()) {
                // Solo se prueba sudo si está en el grupo: fuera de él, cada intento queda como incidente.
                $via = (new Process(['sudo', '-n', 'true'], null, $this->env(), null, 10))->run() === 0 ? 'sudo-n' : 'sudo';
            } else {
                $via = 'su';
            }
        }

        return $this->via = [
            'via'  => $via,
            'pide' => match ($via) {
                'sudo'  => $this->usuario(),
                'su'    => 'root',
                default => null,
            },
        ];
    }

    /**
     * Lo mismo que LectorLogs::leer, pero como root.
     *
     * @throws ContrasenaRootIncorrecta|\LengthException|\RuntimeException
     */
    public function leer(string $ruta, ?string $contrasena, ?int $hasta = null, ?int $despues = null, bool $completo = false): array
    {
        $salida = $this->carpeta() . '/' . bin2hex(random_bytes(16)) . '.json';
        try {
            $this->correr(['modo' => 'leer', 'ruta' => $ruta, 'hasta' => $hasta, 'despues' => $despues, 'completo' => $completo], $salida, $contrasena);
            $r = json_decode((string) @file_get_contents($salida), true);
        } finally {
            @unlink($salida);
        }
        if (! is_array($r)) {
            throw new \RuntimeException('El lector de root no devolvió nada.');
        }
        if (! ($r['ok'] ?? false)) {
            throw ($r['tipo'] ?? '') === 'grande' ? new \LengthException($r['error']) : new \RuntimeException($r['error'] ?? 'No se pudo leer.');
        }

        return $r['r'];
    }

    /**
     * Copia el archivo (hasta 100 MB) a un temporal de este usuario para descargarlo. Quien lo
     * llama lo borra al terminar de enviarlo.
     *
     * @throws ContrasenaRootIncorrecta|\LengthException|\RuntimeException
     */
    public function copiar(string $ruta, ?string $contrasena): string
    {
        $salida = $this->carpeta() . '/' . bin2hex(random_bytes(16));
        $this->correr(['modo' => 'copiar', 'ruta' => $ruta], $salida, $contrasena);
        // Cuando no se pudo, el lector deja el motivo en <salida>.json y no hay copia.
        if (is_file($salida . '.json')) {
            $r = json_decode((string) @file_get_contents($salida . '.json'), true);
            @unlink($salida . '.json');
            @unlink($salida);
            $mensaje = is_array($r) ? ($r['error'] ?? 'No se pudo copiar.') : 'No se pudo copiar.';
            throw (is_array($r) && ($r['tipo'] ?? '') === 'grande') ? new \LengthException($mensaje) : new \RuntimeException($mensaje);
        }
        if (! is_file($salida)) {
            throw new \RuntimeException('El lector de root no dejó la copia.');
        }

        return $salida;
    }

    private function correr(array $pedido, string $salida, ?string $contrasena): void
    {
        $comando = [$this->php(), $this->raiz . '/app/Modules/Addons/LogsTerceros/bin/leer-como-root.php',
            $salida, (string) posix_geteuid(), json_encode($pedido, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
        $via = $this->via()['via'];
        $entrada = null;
        if (in_array($via, ['sudo', 'su'], true)) {
            if ($contrasena === null || $contrasena === '' || preg_match('/[\r\n]/', $contrasena)) {
                throw ContrasenaRootIncorrecta::falta('Escribe la contraseña de ' . $this->via()['pide'] . '.');
            }
            $entrada = $contrasena . "\n";
        }
        $comando = match ($via) {
            'root'   => $comando,
            'sudo-n' => array_merge(['sudo', '-n', '--'], $comando),
            // -k: que sudo SIEMPRE lea la contraseña de la entrada (sin credencial en caché).
            'sudo'   => array_merge(['sudo', '-S', '-k', '-p', '', '--'], $comando),
            'su'     => array_merge(['python3', $this->raiz . '/deploy/system/su-pty.py', '--'], $comando),
        };

        $p = new Process($comando, $this->raiz, $this->env(), $entrada, 90);
        $p->run();
        $rc = $p->getExitCode();
        $salidaTexto = trim($p->getOutput() . "\n" . $p->getErrorOutput());
        if ($contrasena !== null && $contrasena !== '') {
            $salidaTexto = str_replace($contrasena, '****', $salidaTexto);   // por si acaso: nunca de vuelta a la página
        }

        if ($via === 'su' && $rc === 125) {
            throw new ContrasenaRootIncorrecta('Contraseña de root incorrecta.');
        }
        if ($via === 'sudo' && $rc !== 0 && preg_match('/incorrect password|Sorry, try again|no password was provided/i', $salidaTexto)) {
            throw new ContrasenaRootIncorrecta('Contraseña de ' . $this->usuario() . ' incorrecta.');
        }
        if ($rc !== 0) {
            $motivo = match (true) {
                $via === 'su' && $rc === 124 => 'su nunca pidió la contraseña.',
                $via === 'su' && $rc === 126 => 'su no se pudo usar (revisa que root tenga contraseña y que su esté permitido).',
                default                      => 'código ' . $rc,
            };
            throw new \RuntimeException('No se pudo leer como root: ' . $motivo . ($salidaTexto !== '' ? ' ' . mb_substr($salidaTexto, 0, 300) : ''));
        }
    }

    /** Carpeta de trabajo (privada); se limpian los restos de más de una hora. */
    private function carpeta(): string
    {
        $dir = $this->raiz . '/storage/app/logs-terceros';
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear {$dir}.");
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 3600) {
                @unlink($f);
            }
        }

        return $dir;
    }

    private function env(): array
    {
        return array_merge([
            // Desde php-fpm (clear_env) el proceso puede no traer PATH.
            'PATH'   => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'LC_ALL' => 'C.UTF-8',
            'MEGAISP_SU_PROMPT_TIMEOUT' => '20',
        ], $this->entorno);
    }

    private function enGrupoSudo(): bool
    {
        foreach (posix_getgroups() ?: [] as $gid) {
            if (in_array(posix_getgrgid($gid)['name'] ?? '', ['sudo', 'wheel', 'admin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function usuario(): string
    {
        return (string) (posix_getpwuid(posix_geteuid())['name'] ?? 'del servidor web');
    }

    private function php(): string
    {
        $cli = PHP_BINDIR . '/php';
        if (PHP_SAPI === 'cli' && is_executable(PHP_BINARY)) {
            return PHP_BINARY;
        }
        if (is_file($cli) && is_executable($cli)) {
            return $cli;
        }
        $php = (new PhpExecutableFinder())->find(false);
        if (is_string($php) && $php !== '') {
            return $php;
        }
        throw new \RuntimeException('No se encontró PHP de línea de comandos.');
    }
}
