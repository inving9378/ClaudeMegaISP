<?php

namespace App\Modules\Addons\LogsTerceros\Services;

/**
 * Lee archivos de log del servidor para «Logs de terceros». SOLO lectura: no hay nada en
 * este módulo que escriba, mueva o borre un archivo del servidor.
 *
 * Qué se puede registrar y leer (se revisa al guardar la ruta y otra vez al leer, sobre la
 * ruta real, para que un enlace simbólico no sirva de atajo):
 *   - ruta absoluta (desde /), sin bytes nulos;
 *   - que parezca un log: bajo /var/log, bajo storage/logs de MegaISP, o con «log» en alguna
 *     parte de la ruta, o terminado en .out/.err;
 *   - nunca secretos: .env*, llaves y certificados, shadow, respaldos de base de datos, ni
 *     /proc, /sys, /dev, /run, /root/.ssh, /etc/ssl/private, ni el resto del código de MegaISP.
 * Se lee con el usuario del servidor web: si el archivo no le da permiso, se dice tal cual.
 *
 * La ruta puede llevar * (o ? y [..]) para los logs que cambian de nombre al rotar, como
 * storage/logs/laravel-*.log: se muestra el archivo más reciente que coincida y que pase las
 * mismas revisiones.
 */
class LectorLogs
{
    /** Lo que se trae de una vez si no se pide otra cosa (desde el final). */
    public const BYTES_POR_PAGINA = 262144;

    /** Lo máximo que se muestra completo en pantalla; más grande, se descarga. */
    public const MAXIMO_COMPLETO = 16777216;

    private const PREFIJOS_PROHIBIDOS = ['/proc', '/sys', '/dev', '/run', '/root/.ssh', '/etc/ssl/private', '/etc/sudoers.d'];

    private const NOMBRES_PROHIBIDOS = [
        '/^\.env/i', '/\.env(\.|$)/i', '/(secret|credential)/i',
        '/\.(pem|key|crt|p12|pfx|jks|kdbx|sqlite|db)$/i', '/^id_(rsa|dsa|ecdsa|ed25519)/i',
        '/^(shadow|gshadow|sudoers|passwd-|shadow-)$/i', '/\.sql(\.gz)?$/i', '/\.(bak|dump)$/i',
    ];

    public function __construct(private ?string $raizMegaisp = null)
    {
        $this->raizMegaisp = rtrim($raizMegaisp ?? base_path(), '/');
    }

    /** Motivo por el que la ruta no se acepta, o null si se acepta. */
    public function rechazo(string $ruta): ?string
    {
        if ($ruta === '' || $ruta[0] !== '/') {
            return 'La ruta debe ser absoluta (empezar con /).';
        }
        if (str_contains($ruta, "\0") || strlen($ruta) > 1024) {
            return 'La ruta no es válida.';
        }
        $ruta = $this->normalizar($ruta);
        foreach (self::PREFIJOS_PROHIBIDOS as $p) {
            if ($ruta === $p || str_starts_with($ruta, $p . '/')) {
                return "No se permiten archivos bajo {$p}.";
            }
        }
        $nombre = basename($ruta);
        foreach (self::NOMBRES_PROHIBIDOS as $patron) {
            if (preg_match($patron, $nombre)) {
                return 'Ese archivo puede tener contraseñas o llaves; aquí solo se ven logs.';
            }
        }
        $logsMegaisp = $this->raizMegaisp . '/storage/logs';
        if (str_starts_with($ruta, $this->raizMegaisp . '/') && ! str_starts_with($ruta, $logsMegaisp . '/')) {
            return "De MegaISP solo se ven sus logs ({$logsMegaisp}).";
        }
        $pareceLog = str_starts_with($ruta, '/var/log/')
            || str_starts_with($ruta, $logsMegaisp . '/')
            || preg_match('/log/i', $ruta)
            || preg_match('/\.(out|err)(\.\d+)?(\.gz)?$/i', $nombre);

        return $pareceLog ? null : 'Solo archivos de log: bajo /var/log, con «log» en la ruta o terminados en .out/.err.';
    }

    /**
     * Estado del archivo sin leerlo. Con un patrón, el del archivo más reciente que coincide
     * (`actual`). `real` es la ruta real que se lee.
     *
     * @return array{existe: bool, legible: bool, tamano: ?int, modificado: ?int, motivo: ?string, comprimido: bool, actual: ?string, real: ?string}
     */
    public function info(string $ruta): array
    {
        $base = ['existe' => false, 'legible' => false, 'tamano' => null, 'modificado' => null, 'motivo' => null,
            'comprimido' => false, 'actual' => null, 'real' => null];
        if ($motivo = $this->rechazo($ruta)) {
            return array_merge($base, ['motivo' => $motivo]);
        }
        $concreta = $ruta;
        if ($this->esPatron($ruta)) {
            $concreta = $this->masReciente($ruta);
            if ($concreta === null) {
                return array_merge($base, ['motivo' => 'Ningún archivo coincide con ese patrón.']);
            }
        }
        $real = @realpath($concreta);
        if ($real === false || ! @is_file($real)) {
            return array_merge($base, ['motivo' => 'No existe (o no es un archivo).']);
        }
        if ($motivo = $this->rechazo($real)) {
            return array_merge($base, ['existe' => true, 'motivo' => "Apunta a {$real}: {$motivo}"]);
        }
        $legible = @is_readable($real);

        return array_merge($base, [
            'existe'     => true,
            'legible'    => $legible,
            'tamano'     => @filesize($real) ?: 0,
            'modificado' => @filemtime($real) ?: null,
            'motivo'     => $legible ? null : 'MegaISP (usuario ' . $this->usuarioWeb() . ') no tiene permiso para leerlo.',
            'comprimido' => (bool) preg_match('/\.gz$/i', $real),
            'actual'     => $this->esPatron($ruta) ? $real : null,
            'real'       => $real,
        ]);
    }

    private function esPatron(string $ruta): bool
    {
        return strpbrk($ruta, '*?[') !== false;
    }

    /** El archivo más reciente (por fecha de modificación) que coincide y pasa las revisiones. */
    private function masReciente(string $patron): ?string
    {
        $mejor = null;
        $cuando = -1;
        foreach (glob($patron, GLOB_NOSORT) ?: [] as $f) {
            $real = @realpath($f);
            if ($real === false || ! @is_file($real) || $this->rechazo($real)) {
                continue;
            }
            $m = (int) @filemtime($real);
            if ($m > $cuando || ($m === $cuando && strcmp($f, (string) $mejor) > 0)) {
                [$mejor, $cuando] = [$f, $m];
            }
        }

        return $mejor;
    }

    /**
     * Un pedazo del archivo, por bytes, cortado en líneas completas.
     *   - Sin $hasta ni $despues: lo último ($bytes).
     *   - $hasta: lo anterior a ese punto (para «Más antiguas»).
     *   - $despues: lo nuevo desde ese punto (para «Seguir»).
     *   - $completo: todo, si no pasa de MAXIMO_COMPLETO.
     * Para .gz los puntos son sobre el texto ya descomprimido.
     *
     * @return array{texto: string, desde: int, hasta: int, tamano: int, inicio: bool, modificado: ?int}
     */
    public function leer(string $ruta, ?int $hasta = null, ?int $despues = null, int $bytes = self::BYTES_POR_PAGINA, bool $completo = false): array
    {
        $info = $this->info($ruta);
        if (! $info['legible']) {
            throw new \RuntimeException($info['motivo'] ?? 'No se puede leer.');
        }
        $real = $info['real'];
        $bytes = max(1024, min($bytes, self::MAXIMO_COMPLETO));

        [$texto, $desde, $fin, $tamano] = $info['comprimido']
            ? $this->leerGz($real, $hasta, $bytes, $completo)
            : $this->leerPlano($real, $hasta, $despues, $bytes, $completo);

        return [
            'texto'      => $this->limpiar($texto),
            'desde'      => $desde,
            'hasta'      => $fin,
            'tamano'     => $tamano,
            'inicio'     => $desde === 0,
            'modificado' => $info['modificado'],
            'actual'     => $info['actual'],
        ];
    }

    /** @return array{0: string, 1: int, 2: int, 3: int} */
    private function leerPlano(string $real, ?int $hasta, ?int $despues, int $bytes, bool $completo): array
    {
        clearstatcache(true, $real);
        $tamano = (int) filesize($real);
        $f = fopen($real, 'rb');
        if ($f === false) {
            throw new \RuntimeException('No se pudo abrir.');
        }
        try {
            if ($completo) {
                if ($tamano > self::MAXIMO_COMPLETO) {
                    throw new \LengthException($this->demasiadoGrande($tamano));
                }

                return [$tamano ? (string) stream_get_contents($f) : '', 0, $tamano, $tamano];
            }
            if ($despues !== null) {
                // El archivo se rotó o se vació: se empieza de nuevo desde lo último.
                if ($despues > $tamano) {
                    $despues = max(0, $tamano - $bytes);
                }
                $fin = min($tamano, $despues + self::MAXIMO_COMPLETO);
                fseek($f, $despues);
                $texto = $fin > $despues ? (string) fread($f, $fin - $despues) : '';
                // Solo líneas completas: lo que quede a medias llega en la siguiente vuelta.
                $corte = strrpos($texto, "\n");
                $texto = $corte === false ? '' : substr($texto, 0, $corte + 1);

                return [$texto, $despues, $despues + strlen($texto), $tamano];
            }
            $fin = $hasta === null ? $tamano : max(0, min($hasta, $tamano));
            $desde = max(0, $fin - $bytes);
            fseek($f, $desde);
            $texto = $fin > $desde ? (string) fread($f, $fin - $desde) : '';
            if ($desde > 0 && ($salto = strpos($texto, "\n")) !== false) {
                $texto = substr($texto, $salto + 1);
                $desde += $salto + 1;
            }

            return [$texto, $desde, $fin, $tamano];
        } finally {
            fclose($f);
        }
    }

    /** @return array{0: string, 1: int, 2: int, 3: int} */
    private function leerGz(string $real, ?int $hasta, int $bytes, bool $completo): array
    {
        $gz = @gzopen($real, 'rb');
        if ($gz === false) {
            throw new \RuntimeException('No se pudo abrir el .gz.');
        }
        // Se recorre una vez y se guarda solo lo necesario: todo (completo) o una ventana
        // de al menos $bytes que termina en $hasta (o en el final).
        $total = 0;
        $buf = '';
        $bufDesde = 0;
        try {
            while (! gzeof($gz)) {
                $pedazo = gzread($gz, 65536);
                if ($pedazo === false || $pedazo === '') {
                    break;
                }
                $total += strlen($pedazo);
                $buf .= $pedazo;
                if ($completo) {
                    if ($total > self::MAXIMO_COMPLETO) {
                        throw new \LengthException($this->demasiadoGrande($this->tamanoGz($real), true));
                    }
                    continue;
                }
                if ($hasta !== null && $total >= $hasta) {
                    break;
                }
                if (strlen($buf) > 2 * $bytes) {
                    $sobra = strlen($buf) - $bytes;
                    $buf = substr($buf, $sobra);
                    $bufDesde += $sobra;
                }
            }
        } finally {
            gzclose($gz);
        }
        if ($completo) {
            return [$buf, 0, $total, $total];
        }
        $fin = $hasta === null ? $total : min($hasta, $total);
        $desde = max(0, $fin - $bytes);
        $texto = (string) substr($buf, $desde - $bufDesde, $fin - $desde);
        if ($desde > 0 && ($salto = strpos($texto, "\n")) !== false) {
            $texto = substr($texto, $salto + 1);
            $desde += $salto + 1;
        }

        return [$texto, $desde, $fin, max($fin, $this->tamanoGz($real))];
    }

    /** Tamaño descomprimido según el final del .gz (módulo 4 GB; para logs alcanza). */
    private function tamanoGz(string $real): int
    {
        $f = @fopen($real, 'rb');
        if ($f === false || fseek($f, -4, SEEK_END) !== 0) {
            return 0;
        }
        $isize = unpack('V', (string) fread($f, 4));
        fclose($f);

        return (int) ($isize[1] ?? 0);
    }

    /** Texto mostrable: UTF-8 válido y sin los códigos de color de la terminal (Asterisk los usa). */
    private function limpiar(string $texto): string
    {
        $texto = preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $texto) ?? $texto;
        $texto = str_replace("\r\n", "\n", $texto);

        return mb_scrub($texto, 'UTF-8');
    }

    private function demasiadoGrande(int $tamano, bool $gz = false): string
    {
        return sprintf('Pesa %s%s: más de %s no se muestra completo aquí. Usa «Descargar» o «Más antiguas».',
            $this->legible($tamano), $gz ? ' descomprimido' : '', $this->legible(self::MAXIMO_COMPLETO));
    }

    public function legible(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) {
            if ($bytes < 1024 ** ($i + 1) || $u === 'GB') {
                return $i === 0 ? "{$bytes} B" : number_format($bytes / 1024 ** $i, 1) . " {$u}";
            }
        }

        return "{$bytes} B";
    }

    /** /a/./b//c/../d → /a/b/d (sin tocar el disco). */
    private function normalizar(string $ruta): string
    {
        $partes = [];
        foreach (explode('/', $ruta) as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                array_pop($partes);
                continue;
            }
            $partes[] = $p;
        }

        return '/' . implode('/', $partes);
    }

    private function usuarioWeb(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            return (string) (posix_getpwuid(posix_geteuid())['name'] ?? 'del servidor web');
        }

        return 'del servidor web';
    }
}
