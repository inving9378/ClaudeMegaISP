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

        $ejemplos = $this->huellas('huellas-ejemplos.json');   // lo que dejó `make samples`
        $nuestras = $this->huellas('huellas-megaisp.json');    // lo que escribimos nosotros

        $escritos = $propuestos = $iguales = [];

        foreach (glob($this->origen . '/*.tpl') as $tpl) {
            $nombre    = basename($tpl, '.tpl');
            $contenido = $this->rellenar(file_get_contents($tpl), $valores);
            $ruta      = $this->destino . '/' . $nombre;

            if (is_file($ruta)) {
                $actual = @file_get_contents($ruta);

                if (trim((string) $actual) === trim($contenido)) {
                    $iguales[] = $nombre;
                    $nuestras[$nombre] = hash('sha256', (string) $actual);
                    continue;
                }

                // ¿De quién es lo que hay ahí? Si coincide con el ejemplo que
                // instaló `make samples`, o con lo último que escribimos
                // nosotros, nadie lo ha tocado y es nuestro para reemplazar.
                $huella = hash('sha256', (string) $actual);
                $ajeno  = $huella !== ($ejemplos[$nombre] ?? null)
                       && $huella !== ($nuestras[$nombre] ?? null);

                if ($ajeno) {
                    // Alguien lo editó a mano, por una razón que no está aquí.
                    $this->escribir($ruta . '.nuevo', $contenido);
                    $propuestos[] = $nombre;
                    continue;
                }
            }

            $this->escribir($ruta, $contenido);
            $escritos[] = $nombre;
            $nuestras[$nombre] = hash('sha256', $contenido);
        }

        $this->guardarHuellas('huellas-megaisp.json', $nuestras);

        return ['escritos' => $escritos, 'propuestos_nuevo' => $propuestos, 'sin_cambio' => $iguales];
    }

    /**
     * Dónde viven las huellas: junto al árbol de Alembic, fuera del árbol web.
     *
     * Mismo criterio que el resto del soporte de la central (#9990718 §6): son
     * datos de root sobre el estado del servidor, y no tienen por qué ser
     * legibles —ni escribibles— desde la aplicación web.
     */
    private function rutaHuellas(string $archivo): string
    {
        $soporte = config('requisitos-voip.asterisk.soporte_dir', '/usr/share/megaisp-asterisk');

        return rtrim($soporte, '/') . '/' . $archivo;
    }

    /**
     * Las huellas conocidas, o vacío si no hay archivo.
     *
     * Vacío es el caso seguro: sin huella conocida, todo destino existente se
     * considera AJENO y se propone en vez de pisarse. Se pierde la comodidad,
     * nunca la configuración de nadie.
     */
    private function huellas(string $archivo): array
    {
        $ruta = $this->rutaHuellas($archivo);

        if (! is_file($ruta)) {
            return [];
        }

        $datos = json_decode((string) @file_get_contents($ruta), true);

        return is_array($datos) ? $datos : [];
    }

    private function guardarHuellas(string $archivo, array $huellas): void
    {
        $ruta = $this->rutaHuellas($archivo);

        if (! is_dir(dirname($ruta))) {
            return;
        }

        // Un fallo al guardar la huella NO debe tumbar la provisión: la
        // configuración ya quedó escrita, que es lo que importa. Lo que se
        // pierde es poder distinguir en la corrida siguiente, y ahí el caso
        // seguro (proponer en vez de pisar) ya está cubierto arriba.
        if (@file_put_contents($ruta, json_encode($huellas, JSON_PRETTY_PRINT) . "\n") !== false) {
            @chmod($ruta, 0644);
        }
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
            // El mismo nombre que referencian las extensiones publicadas al
            // realtime. Si estos dos dejan de coincidir, Asterisk rechaza cada
            // llamada con «Unable to retrieve PJSIP transport».
            'TRANSPORTE'         => config('requisitos-voip.asterisk.transporte', 'transport-udp'),
            // Dónde deja MegaISP los .conf que genera (grupos, ruteo entrante,
            // contexto restringido). extensions.conf los incluye por esta ruta.
            'GENERADOS_DIR'      => rtrim(config('requisitos-voip.asterisk.generados_dir', '/etc/asterisk/megaisp.d'), '/'),
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
        $nuevo = ! is_file($ruta);

        // 640: la configuración lleva la contraseña de la base realtime dentro.
        if (@file_put_contents($ruta, $contenido) === false) {
            throw new RuntimeException("no se pudo escribir {$ruta}");
        }
        @chmod($ruta, 0640);

        // El dueño se hereda del directorio, y sólo hace falta al CREAR.
        //
        // Al sobrescribir un archivo existente se conserva su dueño, y todos los
        // destinos de estas plantillas existen ya —los dejó `make samples`, y la
        // fase de generación los pasa a asterisk:asterisk—. Pero eso es cierto
        // por casualidad, no por diseño: una plantilla nueva sin ejemplo
        // correspondiente nacería como root:root con permisos 640, y Asterisk
        // —que corre como el usuario asterisk— no podría leerla. Fallaría al
        // arrancar por un archivo que se acaba de escribir «bien».
        if ($nuevo && function_exists('posix_geteuid') && posix_geteuid() === 0) {
            if ($dir = @stat(dirname($ruta))) {
                @chown($ruta, $dir['uid']);
                @chgrp($ruta, $dir['gid']);
            }
        }
    }
}
