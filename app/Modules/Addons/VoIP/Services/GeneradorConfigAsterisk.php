<?php

namespace App\Modules\Addons\VoIP\Services;

use RuntimeException;

/**
 * Rellena las plantillas de `resources/asterisk/plantillas/` y las escribe en
 * `/etc/asterisk/` (#9990718 §5).
 *
 * **Nunca sobrescribe un archivo modificado.** Si el destino existe y difiere,
 * escribe su propuesta como `.nuevo` al lado y avisa. Un provisionador que pisa en
 * silencio la configuración de una central se lleva por delante ajustes que alguien
 * hizo a mano por una razón, y de los que no queda rastro.
 */
class GeneradorConfigAsterisk
{
    private string $origen;
    private string $destino;

    public function __construct(?string $origen = null, ?string $destino = null)
    {
        $this->origen  = $origen  ?? base_path('resources/asterisk/plantillas');
        $this->destino = $destino ?? '/etc/asterisk';
    }

    public function generar(?array $valores = null): array
    {
        $valores ??= $this->valoresDelServidor();

        $escritos = $propuestos = $iguales = [];

        foreach (glob($this->origen . '/*.tpl') as $tpl) {
            $nombre    = basename($tpl, '.tpl');
            $contenido = $this->rellenar(file_get_contents($tpl), $valores);
            $ruta      = $this->destino . '/' . $nombre;

            if (is_file($ruta)) {
                if (trim(file_get_contents($ruta)) === trim($contenido)) {
                    $iguales[] = $nombre;
                    continue;
                }
                // Difiere: se propone, no se pisa.
                $this->escribir($ruta . '.nuevo', $contenido);
                $propuestos[] = $nombre;
                continue;
            }

            $this->escribir($ruta, $contenido);
            $escritos[] = $nombre;
        }

        return ['escritos' => $escritos, 'propuestos_nuevo' => $propuestos, 'sin_cambio' => $iguales];
    }

    /**
     * Sustituye `{{CLAVE}}` y resuelve los bloques opcionales
     * `{{#CLAVE}}…{{/CLAVE}}`, que se emiten solo si la clave tiene valor.
     */
    public function rellenar(string $plantilla, array $valores): string
    {
        // Bloques opcionales primero: si la clave está vacía, el bloque desaparece
        // entero en vez de dejar una línea con un marcador sin sustituir dentro.
        $plantilla = preg_replace_callback(
            '/\{\{#([A-Z_]+)\}\}(.*?)\{\{\/\1\}\}\n?/s',
            fn ($m) => ! empty($valores[$m[1]]) ? $m[2] . "\n" : '',
            $plantilla
        );

        foreach ($valores as $clave => $valor) {
            $plantilla = str_replace('{{' . $clave . '}}', (string) $valor, $plantilla);
        }

        // Un marcador que sobrevive es un dato que faltó: mejor fallar que escribir
        // "bind={{BIND_SIP}}" en la configuración de una central.
        if (preg_match('/\{\{[A-Z_#\/]+\}\}/', $plantilla, $m)) {
            throw new RuntimeException("quedó el marcador {$m[0]} sin sustituir en la plantilla");
        }

        return $plantilla;
    }

    /** Valores detectados del servidor + manifiesto. Ninguno hardcodeado. */
    public function valoresDelServidor(): array
    {
        $db = config('database.connections.asterisk_rt');

        return [
            'LIBDIR'             => $this->libdir(),
            'IDIOMA'             => config('requisitos-voip.asterisk.idioma', 'es'),
            'DB_NAME'            => $db['database'] ?? 'asterisk',
            'DB_USER'            => $db['username'] ?? '',
            'DB_PASSWORD'        => $db['password'] ?? '',
            'ODBC_DSN'           => env('ASTERISK_ODBC_DSN', 'asterisk-connector'),
            'BIND_SIP'           => env('ASTERISK_BIND_SIP', '0.0.0.0'),
            'EXTERNAL_MEDIA'     => env('ASTERISK_EXTERNAL_MEDIA', ''),
            'EXTERNAL_SIGNALING' => env('ASTERISK_EXTERNAL_SIGNALING', ''),
            'LOCAL_NET'          => env('ASTERISK_LOCAL_NET', ''),
            'RTP_START'          => env('ASTERISK_RTP_START', 10000),
            'RTP_END'            => env('ASTERISK_RTP_END', 20000),
            'AMI_BIND'           => config('voip.ami_host', '127.0.0.1'),
            'AMI_PORT'           => config('voip.ami_port', 5038),
            'AMI_USER'           => config('voip.ami_user', 'megaisp'),
            'AMI_SECRET'         => config('voip.ami_pass', ''),
            'AMI_PERMIT'         => env('ASTERISK_AMI_PERMIT', '127.0.0.1/255.255.255.255'),
            'ARI_BIND'           => config('voip.ari_host', '127.0.0.1'),
            'ARI_PORT'           => config('voip.ari_port', 8088),
            'ARI_USER'           => config('voip.ari_user', 'medussa'),
            'ARI_PASSWORD'       => config('voip.ari_pass', ''),
        ];
    }

    private function libdir(): string
    {
        $p = \Symfony\Component\Process\Process::fromShellCommandline('dpkg-architecture -qDEB_HOST_MULTIARCH 2>/dev/null');
        $p->run();
        $arch = trim($p->getOutput()) ?: (php_uname('m') . '-linux-gnu');

        return '/usr/lib/' . $arch;
    }

    private function escribir(string $ruta, string $contenido): void
    {
        // 640 y grupo asterisk: la config lleva la contraseña de la base realtime.
        if (@file_put_contents($ruta, $contenido) === false) {
            throw new RuntimeException("no se pudo escribir {$ruta}");
        }
        @chmod($ruta, 0640);
    }
}
