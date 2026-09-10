<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\ProvisionEstado;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Orquesta la provisión completa de Asterisk (#9990718 §3).
 *
 * El orden es el contrato: **paquete → esquema → configuración → arranque**.
 * Invertir cualquiera deja la central en un estado inconsistente — arrancar antes
 * de configurar levanta un Asterisk que no sabe dónde está su base; configurar
 * antes de crear el esquema escribe un mapeo a tablas que no existen.
 *
 * ─── POR QUÉ QUINCE PASOS Y NO DIEZ ───────────────────────────────────────
 *
 * Los pasos eran diez, pero tres de ellos mentían: `descargar` invocaba el script
 * de shell **entero** —bajar, compilar, instalar, preservar Alembic y generar
 * configuración— mientras `dependencias` y `compilar` no hacían nada y se
 * asentaban como completados igual.
 *
 * El coste no era cosmético. Un fallo al generar `alembic.ini` (el último 5% del
 * script) marcaba `descargar: fallido`, y reintentar volvía a lanzar el script
 * completo: media hora de `make` para morir en el mismo sitio. La tabla de estado
 * y `reutilizablePor()` existen justamente para evitar eso, y con un paso que
 * abarcaba el 95% del trabajo no tenían nada que reutilizar.
 *
 * Ahora cada tramo caro es su propio paso, con su propio estado, y el script se
 * invoca una vez por fase (`--fase=…`).
 *
 * ─── LA VERDAD ESTÁ EN EL SERVIDOR, NO EN LA TABLA ────────────────────────
 *
 * Saltarse trabajo por lo que dice la tabla es frágil: basta actualizar MegaISP a
 * media provisión para que el estado registrado ya no corresponda a la lógica que
 * va a continuar. Por eso `reutilizablePor()` descarta lo que dejó una versión
 * anterior — y por eso, además, **cada fase comprueba el disco**: si Asterisk ya
 * está instalado en la versión del manifiesto, `instalar` lo dice y sale sin
 * tocar nada, aunque la tabla esté vacía.
 *
 * Las dos cosas juntas son lo que hace barato el reintento: la tabla evita repetir
 * dentro de una misma versión, y la comprobación del servidor evita repetir entre
 * versiones distintas.
 *
 * ─── EL CONTRATO SE EXIGE ANTES DE TRABAJAR ───────────────────────────────
 *
 * El primer paso valida el manifiesto y la conexión realtime, y además invoca
 * `--fase=contrato` para que sea **el propio script** quien confirme que tiene
 * todo lo que consume. Así el emisor y el consumidor no pueden discrepar en
 * silencio, que es como una provisión llegó a morir en el último paso con
 * `KeyError: 'ASTERISK_DB_DRIVER'` después de compilar media hora.
 *
 * ─── QUÉ NO HACE ──────────────────────────────────────────────────────────
 *
 * No compila: eso lo hace `provisioning/provisionar-asterisk.sh`, que necesita
 * root. Esta clase lo invoca fase a fase y lee su resultado. La frontera está ahí
 * a propósito: PHP corre como www-data y no debe instalar paquetes ni tocar
 * systemd.
 *
 * No escribe credenciales a mano: usa `EscritorEnv`, que respalda, escribe
 * atómico y valida.
 */
class ProvisionadorAsterisk
{
    /** Los pasos, en orden de ejecución. El orden ES el contrato. */
    public const PASOS = [
        'verificar'         => 'Contrato, manifiesto y estado del servidor',
        'descargar'         => 'Descarga del tarball',
        'verificar_hash'    => 'Verificación sha256 contra el manifiesto',
        'dependencias'      => 'Dependencias de compilación y cadena de Alembic',
        'compilar'          => 'Compilación',
        'instalar'          => 'Instalación de binarios y sonidos',
        'preservar_alembic' => 'Preservación del árbol de Alembic',
        'generar_config'    => 'asterisk.conf, unit de systemd y alembic.ini',
        'crear_base'        => 'Base de datos realtime',
        'migrar_esquema'    => 'alembic upgrade head',
        'config'            => 'Configuración de MegaISP desde plantillas',
        'credenciales'      => 'Credenciales AMI y ARI',
        'siembra'           => 'Plan de numeración y extensiones',
        'arrancar'          => 'Arranque del servicio',
        'validar'           => 'Validación final',
    ];

    /**
     * Pasos que delega al script de shell, con el tiempo máximo de cada uno.
     *
     * Los timeouts son por fase y no uno global de 90 minutos: una descarga que
     * se cuelga no tiene por qué esperar lo que espera un `make -j`, y un tope
     * ajustado hace que un cuelgue se note en minutos en vez de en una hora.
     */
    private const FASES_SCRIPT = [
        'descargar'         => 1200,   // 20 min: red lenta, tarball de ~30 MB
        'verificar_hash'    => 300,
        'dependencias'      => 1800,   // apt puede tardar en un servidor recién puesto
        'compilar'          => 5400,   // 90 min: es el tramo caro, y en una VM modesta lo usa
        'instalar'          => 1800,
        'preservar_alembic' => 600,
        'generar_config'    => 300,
    ];

    private string $uuid;
    private string $version;
    private array  $manifiesto;
    private bool   $modoDescubrimiento;
    private bool   $continuada;
    private string $restoSalida = '';   // media línea que aún no termina en \n
    private $salida;   // callable(string) para reportar en vivo

    /**
     * Continúa la última provisión inconclusa, o abre una nueva si no hay.
     *
     * Antes se generaba un UUID nuevo en cada corrida, y como los pasos se buscan
     * por UUID, `$previo` salía siempre null: nada se reutilizaba jamás. La tabla
     * registraba el avance correctamente y aun así reintentar empezaba de cero.
     *
     * Retomar no es dar por bueno lo anterior a ciegas: cada paso sigue pasando
     * por `reutilizablePor()`, que rechaza lo que dejó una versión distinta del
     * provisionador, y lo que falló se reintenta siempre.
     */
    public function __construct(bool $modoDescubrimiento = false, ?callable $salida = null, bool $nueva = false)
    {
        $inconclusa = $nueva ? null : self::ejecucionInconclusa();

        $this->uuid               = $inconclusa ?? (string) Str::uuid();
        $this->continuada         = $inconclusa !== null;
        $this->version            = (string) config('requisitos-voip.provisionador_version', '0.0.0');
        $this->manifiesto         = (array) config('requisitos-voip.asterisk', []);
        $this->modoDescubrimiento = $modoDescubrimiento;
        $this->salida             = $salida ?? fn (string $m) => null;
    }

    /**
     * La última ejecución que no llegó a completar `validar`, si la hay.
     *
     * `validar` es el último paso: mientras no esté completado, la central no
     * quedó en pie y la corrida sigue abierta.
     */
    private static function ejecucionInconclusa(): ?string
    {
        $ultima = ProvisionEstado::orderByDesc('id')->value('ejecucion_uuid');

        if (! $ultima) {
            return null;
        }

        $cerrada = ProvisionEstado::deEjecucion($ultima)
            ->where('paso', 'validar')
            ->where('estado', 'completado')
            ->exists();

        return $cerrada ? null : $ultima;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return array{ok: bool, uuid: string, pasos: array, revision: ?string, mensaje: string}
     */
    public function ejecutar(): array
    {
        $this->di("Provisión {$this->uuid} — provisionador v{$this->version}");

        if ($this->continuada) {
            $this->di('Retoma una provisión anterior: los pasos que complete esta misma versión');
            $this->di('se reutilizan, lo que falló se reintenta. Para empezar de cero: --nueva.');
        }

        if ($this->modoDescubrimiento) {
            $this->di('MODO DESCUBRIMIENTO: la revisión de Alembic se reportará al final.');
        }

        $revision = null;

        try {
            // Primero el contrato. Si falta un valor, se sabe aquí y no después
            // de compilar: es la diferencia entre un fallo de un segundo y uno
            // de media hora.
            $this->paso('verificar', fn () => $this->verificarContratoYEstado());

            // Las fases caras, cada una con su estado propio.
            foreach (self::FASES_SCRIPT as $paso => $timeout) {
                $this->paso($paso, fn () => $this->correrFase($paso, $timeout));
            }

            $this->paso('crear_base', fn () => $this->crearBaseRealtime());
            $revision = $this->paso('migrar_esquema', fn () => $this->migrarEsquema());
            $this->paso('config',       fn () => $this->escribirConfiguracion());
            $this->paso('credenciales', fn () => $this->generarCredenciales());
            $this->paso('siembra',      fn () => $this->sembrar());
            $this->paso('arrancar',     fn () => $this->arrancarServicio());
            $this->paso('validar',      fn () => $this->validarFinal());
        } catch (RuntimeException $e) {
            $this->di('FALLÓ: ' . $e->getMessage());

            return [
                'ok'       => false,
                'uuid'     => $this->uuid,
                'pasos'    => $this->resumen(),
                'revision' => null,
                'mensaje'  => $e->getMessage(),
            ];
        }

        $rev = is_array($revision) ? ($revision['revision'] ?? null) : null;

        return [
            'ok'       => true,
            'uuid'     => $this->uuid,
            'pasos'    => $this->resumen(),
            'revision' => $rev,
            'mensaje'  => $this->modoDescubrimiento && $rev
                ? "Provisión completa. Fija esquema_realtime = '{$rev}' en config/requisitos-voip.php."
                : 'Provisión completa.',
        ];
    }

    /**
     * Ejecuta un paso, lo asienta, y decide si puede saltárselo.
     *
     * Un paso `completado` por una versión ANTERIOR no se reutiliza: su criterio
     * de "completado" pudo cambiar, y darlo por bueno sería confiar en un estado
     * que ya no corresponde a la lógica actual. Cuando eso pasa, el paso se
     * vuelve a ejecutar — y es la comprobación del servidor que hace cada fase,
     * no la tabla, la que evita repetir el trabajo caro.
     */
    private function paso(string $nombre, callable $fn): mixed
    {
        $previo = ProvisionEstado::deEjecucion($this->uuid)->where('paso', $nombre)->first();

        if ($previo && $previo->reutilizablePor($this->version)) {
            $this->di("  [{$nombre}] ya completado por v{$previo->version_provisionador} — se reutiliza");

            return $previo->detalle;
        }

        $registro = ProvisionEstado::updateOrCreate(
            ['ejecucion_uuid' => $this->uuid, 'paso' => $nombre],
            [
                'estado'                => 'en_progreso',
                'version_provisionador' => $this->version,
                'version_asterisk'      => $this->manifiesto['version'] ?? null,
                'iniciado_at'           => now(),
                'intentos'              => ($previo->intentos ?? 0) + 1,
            ]
        );

        $this->di("  [{$nombre}] " . (self::PASOS[$nombre] ?? ''));

        try {
            $detalle = $fn();

            $registro->update([
                // Un paso que no tuvo nada que hacer se asienta como `omitido`,
                // no como `completado`: en el reporte no es lo mismo «se
                // compiló» que «ya estaba compilado», y confundirlos es cómo se
                // pierde de vista qué hizo realmente una corrida.
                'estado'           => (is_array($detalle) && ($detalle['omitido'] ?? false)) ? 'omitido' : 'completado',
                'detalle'          => is_array($detalle) ? $detalle : ['resultado' => $detalle],
                'esquema_revision' => is_array($detalle) ? ($detalle['revision'] ?? null) : null,
                'terminado_at'     => now(),
            ]);

            return $detalle;
        } catch (\Throwable $e) {
            $registro->update([
                'estado'       => 'fallido',
                // El mensaje sí; nunca el valor de una credencial.
                'error'        => Str::limit($e->getMessage(), 2000),
                'terminado_at' => now(),
            ]);

            Log::channel('single')->error('voip-provision-paso-fallido', [
                'uuid' => $this->uuid, 'paso' => $nombre, 'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("El paso «{$nombre}» falló: " . $e->getMessage(), 0, $e);
        }
    }

    // ── Los pasos ────────────────────────────────────────────────────────

    /**
     * Contrato completo, antes de que nada cueste tiempo.
     *
     * Dos mitades que se comprueban juntas a propósito:
     *
     *   · Lo que ESTA clase necesita para armar los parámetros (el manifiesto y
     *     la conexión `asterisk_rt`).
     *   · Lo que el SCRIPT exige para trabajar, preguntándoselo a él con
     *     `--fase=contrato` en vez de replicar aquí sus reglas.
     *
     * Preguntarle al script es lo que impide que emisor y consumidor discrepen
     * en silencio: una variable que este lado no manda y aquel lado sí consume
     * aparece ahora en el segundo cero, y ya no a los treinta minutos.
     */
    private function verificarContratoYEstado(): array
    {
        $faltan = [];

        foreach (['version', 'origen', 'archivo', 'sha256'] as $clave) {
            if (blank($this->manifiesto[$clave] ?? null)) {
                $faltan[] = "config/requisitos-voip.php → asterisk.{$clave}";
            }
        }

        $rt = (array) config('database.connections.asterisk_rt');

        if (! $rt) {
            $faltan[] = "la conexión 'asterisk_rt' no existe en config/database.php";
        } else {
            foreach (['host', 'port', 'database', 'username', 'password'] as $clave) {
                if (blank($rt[$clave] ?? null)) {
                    $faltan[] = "database.connections.asterisk_rt.{$clave} (del .env, ASTERISK_RT_DB_*)";
                }
            }
        }

        if ($faltan) {
            throw new RuntimeException(
                "el contrato de provisión está incompleto y no se empieza a trabajar sin él:\n        · "
                . implode("\n        · ", $faltan)
            );
        }

        // Ahora que el script confirme, con SUS reglas, que no le falta nada.
        $this->correrFase('contrato', 120);

        $estado = $this->estadoDelServidor();

        $this->di(sprintf(
            '    instalada: %s · pedida: %s · alembic preservado: %s',
            $estado['instalada'] ?? '(ninguna)',
            $estado['pedida'] ?? '?',
            $estado['hay_alembic'] ? 'sí' : 'no'
        ));

        return $estado;
    }

    /** Lo que hay realmente en el disco de este servidor. */
    private function estadoDelServidor(): array
    {
        $binario   = '/usr/sbin/asterisk';
        $instalada = null;

        if (is_executable($binario)) {
            $p = new Process([$binario, '-V']);
            $p->run();
            if ($p->isSuccessful() && preg_match('/Asterisk\s+(\S+)/', $p->getOutput(), $m)) {
                $instalada = $m[1];
            }
        }

        $pedida  = $this->manifiesto['version'] ?? null;
        $soporte = $this->manifiesto['soporte_dir'] ?? '/usr/share/megaisp-asterisk';

        return [
            'instalada'   => $instalada,
            'pedida'      => $pedida,
            'cumple'      => $instalada !== null && $instalada === $pedida,
            'hay_alembic' => is_dir($soporte . '/alembic/config/versions'),
        ];
    }

    /**
     * Invoca UNA fase del script. Es lo único que necesita root.
     *
     * Una invocación por fase, y no una sola para todo: así el estado que se
     * asienta corresponde a un tramo real de trabajo, y reintentar retoma donde
     * se quedó en vez de repetir desde el principio.
     */
    private function correrFase(string $fase, int $timeout): array
    {
        $script = base_path('app/Modules/Addons/VoIP/provisioning/provisionar-asterisk.sh');

        if (! is_file($script)) {
            throw new RuntimeException("No encuentro el script de instalación en {$script}.");
        }

        // El entorno NO se pasa por `$env`: entre este proceso y el script hay un
        // `sudo`, y sudo trae `env_reset` por omisión — descarta toda variable que
        // no esté en su `env_keep` antes de ejecutar bash. Se exportaban bien y el
        // script las recibía vacías, así que moría culpando al manifiesto.
        //
        // Van en un archivo 0600 cuya RUTA sí viaja como argumento. No por
        // `sudo env VAR=…`, que dejaría la contraseña de la base realtime a la
        // vista de cualquier `ps`.
        $archivoEntorno = $this->escribirArchivoDeEntorno($this->parametros());

        try {
            $p = Process::fromShellCommandline(
                'sudo -n bash ' . escapeshellarg($script)
                    . ' --fase=' . escapeshellarg($fase)
                    . ' --archivo-entorno=' . escapeshellarg($archivoEntorno),
                base_path(), null, null, $timeout
            );
            $p->run(fn ($tipo, $buf) => $this->emitir($buf));
        } finally {
            $this->vaciarSalida();
            // El archivo lo creó esta clase y lo borra esta clase, falle o no.
            @unlink($archivoEntorno);
        }

        if (! $p->isSuccessful()) {
            throw new RuntimeException("la fase «{$fase}» del script salió con código "
                . $p->getExitCode() . $this->porQue($p, $fase));
        }

        $salida = trim($p->getOutput());

        return [
            'fase'    => $fase,
            // El script dice explícitamente cuándo no tuvo nada que hacer; se
            // recoge para que el reporte distingua «se hizo» de «ya estaba».
            'omitido' => (bool) preg_match('/no hay nada que|ya está instalado|ya está preservado|ya presente|nada que hacer|no se compila/i', $salida),
            'ultimas' => Str::limit($salida, 500),
        ];
    }

    /**
     * Los parámetros que consume el script, completos.
     *
     * La lista tiene que cubrir TODO lo que `validar_contrato()` exige del otro
     * lado. Faltaba `ASTERISK_DB_DRIVER` y el script caía a su valor por
     * omisión sin decirlo, hasta que un bloque de Python murió con
     * `KeyError: 'ASTERISK_DB_DRIVER'` al final de una compilación de media hora.
     * Hoy esa discrepancia la caza `--fase=contrato` en el primer paso.
     */
    private function parametros(): array
    {
        return [
            'ASTERISK_VERSION'             => (string) ($this->manifiesto['version'] ?? ''),
            'ASTERISK_ORIGEN'              => (string) ($this->manifiesto['origen'] ?? ''),
            'ASTERISK_ARCHIVO'             => (string) ($this->manifiesto['archivo'] ?? ''),
            'ASTERISK_SHA256'              => (string) ($this->manifiesto['sha256'] ?? ''),
            'ASTERISK_IDIOMA'              => (string) ($this->manifiesto['idioma'] ?? 'es'),
            'ASTERISK_ESPACIO_MIN_MB'      => (string) ($this->manifiesto['espacio_minimo_mb'] ?? 3072),
            'ASTERISK_SOPORTE_DIR'         => (string) ($this->manifiesto['soporte_dir'] ?? '/usr/share/megaisp-asterisk'),
            'ASTERISK_TRABAJO'             => (string) ($this->manifiesto['trabajo_dir'] ?? '/var/cache/megaisp-asterisk'),
            'ASTERISK_LOG_DIR'             => (string) ($this->manifiesto['log_dir'] ?? '/var/log/megaisp'),
            'ASTERISK_ESQUEMA_REALTIME'    => (string) ($this->manifiesto['esquema_realtime'] ?? ''),
            'ASTERISK_MODO_DESCUBRIMIENTO' => $this->modoDescubrimiento ? '1' : '0',
            'ASTERISK_DB_HOST'             => (string) config('database.connections.asterisk_rt.host'),
            'ASTERISK_DB_PORT'             => (string) config('database.connections.asterisk_rt.port'),
            'ASTERISK_DB_NAME'             => (string) config('database.connections.asterisk_rt.database'),
            'ASTERISK_DB_USER'             => (string) config('database.connections.asterisk_rt.username'),
            'ASTERISK_DB_PASSWORD'         => (string) config('database.connections.asterisk_rt.password'),
            // El driver de Python con que Alembic abre la conexión. Tiene que ser
            // uno de los que instala la fase `dependencias`.
            'ASTERISK_DB_DRIVER'           => (string) ($this->manifiesto['db_driver'] ?? 'pymysql'),
            // El nombre del DSN viaja explícito aunque los dos lados tengan hoy
            // el mismo valor por omisión. Si se dejara que cada uno lo resolviera
            // por su cuenta, definir ASTERISK_ODBC_DSN en el .env cambiaría el que
            // escribe res_odbc.conf y NO el que registra unixODBC: Asterisk pediría
            // un DSN inexistente y el realtime quedaría mudo sin un solo error.
            // Una sola fuente, y es la del generador de configuración.
            'ASTERISK_ODBC_DSN'            => (string) app(GeneradorConfigAsterisk::class)
                ->valoresDelServidor()['ODBC_DSN'],
            // Misma razón que el DSN: el script crea el directorio y el archivo
            // que extensions.conf incluye, así que los dos lados tienen que
            // nombrar la misma ruta o el #include apuntaría al aire.
            'ASTERISK_GENERADOS_DIR'       => (string) app(GeneradorConfigAsterisk::class)
                ->valoresDelServidor()['GENERADOS_DIR'],
        ];
    }

    /**
     * Deja los parámetros en un archivo que solo su dueño puede leer.
     *
     * Fuera del árbol de la aplicación web y con nombre irrepetible: lleva la
     * contraseña de la base realtime dentro, aunque viva unos segundos.
     */
    private function escribirArchivoDeEntorno(array $env): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'megaisp-voip-entorno-');

        if ($ruta === false) {
            throw new RuntimeException('no se pudo crear el archivo de parámetros para el script.');
        }

        // tempnam ya crea 0600; se reafirma porque de eso depende que el script
        // acepte el archivo (y que la contraseña no se lea desde otra cuenta).
        chmod($ruta, 0600);

        $lineas = [];
        foreach ($env as $clave => $valor) {
            // Comilla simple: el shell no interpreta NADA dentro, ni $ ni ` ni \.
            $lineas[] = $clave . "='" . str_replace("'", "'\\''", (string) $valor) . "'";
        }

        if (file_put_contents($ruta, implode("\n", $lineas) . "\n") === false) {
            @unlink($ruta);
            throw new RuntimeException("no se pudo escribir el archivo de parámetros {$ruta}.");
        }

        return $ruta;
    }

    /**
     * Por qué falló, con las últimas líneas que dijo el script.
     *
     * Antes este mensaje remitía siempre a /var/log/megaisp/, y eso es cierto solo
     * si el script llegó a crear el log: las validaciones de parámetros corren
     * ANTES, así que un fallo ahí mandaba a leer un archivo que no existía y
     * escondía la única línea que explicaba el problema.
     */
    private function porQue(Process $p, string $fase): string
    {
        $lineas = preg_split('/\R/', trim($p->getErrorOutput()) ?: trim($p->getOutput())) ?: [];
        $lineas = array_values(array_filter(array_map('trim', $lineas), fn ($l) => $l !== ''));
        $cola   = implode(' | ', array_slice($lineas, -3));

        $log    = (string) ($this->manifiesto['log_dir'] ?? '/var/log/megaisp');
        $hayLog = is_dir($log) && glob("{$log}/provision-asterisk-{$fase}-*.log");

        return ($cola !== '' ? ': ' . Str::limit(rtrim($cola, '. '), 400) : '')
            . ($hayLog
                ? ". El log completo de la fase queda en {$log}/provision-asterisk-{$fase}-*.log."
                : '. No alcanzó a escribir log: falló antes de crearlo.');
    }

    /**
     * La base realtime, antes de migrarle el esquema.
     *
     * Era el hueco entre «Asterisk instalado» y «alembic upgrade»: nadie creaba
     * la base, así que en un servidor nuevo Alembic fallaba con un «unknown
     * database» que no decía a quién le tocaba crearla.
     *
     * Se conecta SIN base seleccionada, porque la que se va a crear todavía no
     * existe y seleccionarla haría fallar la propia conexión.
     */
    private function crearBaseRealtime(): array
    {
        $rt     = (array) config('database.connections.asterisk_rt');
        $nombre = (string) ($rt['database'] ?? '');

        // No se puede parametrizar el nombre de una base en un CREATE, así que se
        // valida antes de interpolarlo. El script hace la misma comprobación por
        // su lado; aquí se repite porque este camino no pasa por él.
        if (! preg_match('/^[A-Za-z0-9_]+$/', $nombre)) {
            throw new RuntimeException("el nombre de base '{$nombre}' no es un identificador válido");
        }

        // Conexión efímera sin base seleccionada.
        config(['database.connections.asterisk_rt_sin_base' => ['database' => null] + $rt]);
        DB::purge('asterisk_rt_sin_base');

        try {
            $existia = DB::connection('asterisk_rt_sin_base')
                ->select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$nombre]);

            if ($existia) {
                return ['base' => $nombre, 'creada' => false, 'nota' => 'ya existía'];
            }

            DB::connection('asterisk_rt_sin_base')->statement(
                "CREATE DATABASE `{$nombre}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "no se pudo crear la base realtime '{$nombre}': " . $e->getMessage()
                . " — el usuario '{$rt['username']}' necesita CREATE sobre ella, o créala a mano."
            );
        } finally {
            DB::purge('asterisk_rt_sin_base');
        }

        return ['base' => $nombre, 'creada' => true];
    }

    private function migrarEsquema(): array
    {
        $soporte = $this->manifiesto['soporte_dir'] ?? '/usr/share/megaisp-asterisk';
        $dbm     = $soporte . '/alembic';
        $ini     = $dbm . '/config.ini';

        if (! is_dir($dbm)) {
            throw new RuntimeException("no está el árbol de Alembic en {$dbm}: sin él no hay esquema");
        }
        if (! is_file($ini)) {
            throw new RuntimeException("no está {$ini}: lo genera la fase generar_config con las credenciales");
        }

        $p = Process::fromShellCommandline(
            $this->comandoAlembic() . ' -c ' . escapeshellarg($ini) . ' upgrade head',
            $dbm, null, null, 900
        );
        $p->run(fn ($t, $b) => $this->emitir($b));
        $this->vaciarSalida();

        if (! $p->isSuccessful()) {
            throw new RuntimeException('alembic upgrade head falló: ' . Str::limit($p->getErrorOutput(), 400)
                . ' — el esquema NO se parchea a mano: si el módulo necesita una columna que Alembic no da, se corrige el módulo.');
        }

        $revision = $this->revisionAplicada();
        $esperada = $this->manifiesto['esquema_realtime'] ?? null;

        if (! $this->modoDescubrimiento && $esperada && $revision !== $esperada) {
            throw new RuntimeException("la revisión aplicada ({$revision}) no es la que declara el manifiesto ({$esperada})");
        }

        return ['revision' => $revision, 'esperada' => $esperada, 'descubrimiento' => $this->modoDescubrimiento];
    }

    /**
     * Con qué se invoca a Alembic en ESTE servidor.
     *
     * Decía `sudo -n alembic` a secas y eso no corre: `sudo` reemplaza el PATH
     * por su `secure_path` (/usr/local/sbin:…:/bin), donde no está el `alembic`
     * que pip instala en ~/.local/bin. La provisión moría con «sudo: alembic:
     * command not found» en el paso 10, después de haber creado la base.
     *
     * Se resuelve con la MISMA regla que la fase `dependencias` del script —el
     * ejecutable si está en el PATH, y si no `python3 -m alembic`—, porque tener
     * dos reglas distintas para lo mismo es cómo se llega a que el pre-flight
     * apruebe una cadena que luego no corre.
     *
     * `sudo` solo se antepone si hace falta: cuando la provisión ya viene de
     * root —que es como la invoca el proceso de actualización— anteponerlo lo
     * único que hace es volver a atravesar `secure_path` sin necesidad.
     */
    private function comandoAlembic(): string
    {
        $sudo = (function_exists('posix_geteuid') && posix_geteuid() === 0) ? '' : 'sudo -n ';

        foreach (['alembic --version', 'python3 -m alembic --version'] as $candidato) {
            $p = Process::fromShellCommandline($sudo . $candidato, null, null, null, 60);
            $p->run();

            if ($p->isSuccessful()) {
                return $sudo . substr($candidato, 0, -strlen(' --version'));
            }
        }

        throw new RuntimeException(
            'no hay un comando «alembic» utilizable (ni el ejecutable ni «python3 -m alembic»), '
            . 'pese a que la fase «dependencias» lo dio por bueno — sin él el árbol preservado no sirve'
        );
    }

    /**
     * El árbol de Alembic cuyo esquema necesita el módulo: `config`, el de las
     * tablas `ps_*` del realtime de PJSIP.
     *
     * Asterisk publica CUATRO árboles independientes en `contrib/ast-db-manage`
     * —`config`, `cdr`, `voicemail`, `queue_log`— y los cuatro pueden convivir
     * en la misma base. El provisionador solo aplica éste; los otros tres son
     * de funciones que el módulo no usa hoy.
     */
    private const ARBOL_ALEMBIC = 'config';

    /**
     * La revisión que quedó aplicada, leída de donde Asterisk de verdad la deja.
     *
     * ⚠️ NO es la tabla `alembic_version` a secas, y esto se ve mal a primera
     * vista: un proyecto Alembic cualquiera usa ese nombre, así que leerlo
     * parece «lo estándar». Para Asterisk no lo es.
     *
     * Su `env.py` de upstream configura `version_table='alembic_version_' +
     * script_location` a propósito, porque los cuatro árboles comparten base y
     * una sola tabla no podría guardar cuatro revisiones a la vez. Aplicar el
     * árbol `config` deja la revisión en **`alembic_version_config`**, y la
     * tabla sin sufijo no llega a existir nunca.
     *
     * El código anterior leía `alembic_version` llamándola «la tabla estándar»:
     * el `upgrade head` corría entero y correcto, y el paso moría después con un
     * «table doesn't exist» que parecía un fallo de la migración cuando en
     * realidad era de la lectura. Por eso el nombre se deriva del árbol y no se
     * escribe a mano.
     */
    private function revisionAplicada(): ?string
    {
        $tabla = 'alembic_version_' . self::ARBOL_ALEMBIC;

        try {
            $r = DB::connection('asterisk_rt')->select("SELECT version_num FROM `{$tabla}` LIMIT 1");

            return $r[0]->version_num ?? null;
        } catch (\Throwable $e) {
            throw new RuntimeException("no se pudo leer {$tabla}, que es donde Asterisk registra la "
                . 'revisión del árbol ' . self::ARBOL_ALEMBIC . ': ' . $e->getMessage());
        }
    }

    /**
     * Escribe la configuración, y DICE cuánta no se aplicó.
     *
     * Lo que se propone como `.nuevo` en vez de aplicarse no es un fallo —es la
     * regla de no pisar lo que un operador ajustó— pero tampoco es un éxito
     * silencioso. Este paso llegó a asentarse como «completado» habiendo escrito
     * CERO de nueve archivos, y la central quedó corriendo con los ejemplos de
     * Asterisk sin que nada lo dijera. Quien lea la salida tiene que poder ver la
     * diferencia entre «configurado» y «propuesto y no aplicado».
     *
     * Que la central sirva o no con lo que hay lo decide el paso `validar`,
     * preguntándole a Asterisk — no este paso contando archivos.
     */
    private function escribirConfiguracion(): array
    {
        $r = app(GeneradorConfigAsterisk::class)->generar();

        $this->di(sprintf('    %d escritos · %d sin cambio · %d propuestos como .nuevo',
            count($r['escritos']), count($r['sin_cambio']), count($r['propuestos_nuevo'])));

        if ($r['propuestos_nuevo']) {
            $this->di('    NO se aplicaron (difieren de lo que dejamos y de los ejemplos): '
                . implode(', ', $r['propuestos_nuevo']));
            $this->di('    Están al lado como .nuevo. Alguien los editó a mano y no se pisan.');
        }

        return $r;
    }

    private function generarCredenciales(): array
    {
        return app(GeneradorCredenciales::class)->generarYPersistir($this->uuid, $this->version);
    }

    /**
     * Siembra el plan de numeración y las extensiones, Y las publica al realtime.
     *
     * Las dos mitades son un solo paso a propósito. Sembrar sin publicar deja 30
     * extensiones que existen para MegaISP y no existen para Asterisk: la pantalla
     * las muestra, el teléfono no registra, y nada en el sistema dice por qué.
     *
     * Las tablas son distintas y de bases distintas —`voip_extensiones` es de
     * MegaISP; `ps_endpoints`, `ps_auths` y `ps_aors` son de Asterisk, y las crea
     * Alembic— así que sembrar en una no pone nada en la otra. Antes esto pasaba
     * inadvertido porque en dev las `ps_*` ya venían pobladas de una instalación a
     * mano anterior; al recrear la base realtime desde cero quedaron vacías y el
     * hueco se hizo visible.
     */
    private function sembrar(): array
    {
        Artisan::call('db:seed', ['--class' => \App\Modules\Addons\VoIP\Seeders\PlanNumeracionSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => \App\Modules\Addons\VoIP\Seeders\ExtensionesArranqueSeeder::class, '--force' => true]);

        $provisionador = app(AsteriskProvisioningService::class);
        $publicadas    = 0;
        $fallidas      = [];

        foreach (\App\Modules\Addons\VoIP\Models\Extension::all() as $ext) {
            try {
                $provisionador->provisionarExtension($ext);
                $publicadas++;
            } catch (\Throwable $e) {
                // Una extensión que no se pueda publicar no debe impedir que se
                // publiquen las otras 29: se anota y se sigue. El paso reporta
                // cuáles quedaron fuera, que es lo que hace falta para arreglarlo.
                $fallidas[$ext->numero] = Str::limit($e->getMessage(), 200);
            }
        }

        $detalle = [
            'rangos'      => \App\Modules\Addons\VoIP\Models\RangoNumeracion::count(),
            'perfiles'    => \App\Modules\Addons\VoIP\Models\PerfilExtension::count(),
            'extensiones' => \App\Modules\Addons\VoIP\Models\Extension::count(),
            'publicadas_realtime' => $publicadas,
        ];

        if ($fallidas) {
            $detalle['fallidas'] = $fallidas;
        }

        $this->di(sprintf('    %d extensiones publicadas al realtime%s',
            $publicadas,
            $fallidas ? ' — ' . count($fallidas) . ' fallaron: ' . implode(', ', array_keys($fallidas)) : ''
        ));

        return $detalle;
    }

    private function arrancarServicio(): array
    {
        foreach ([['enable', 'asterisk'], ['restart', 'asterisk']] as [$accion, $unidad]) {
            $p = new Process(['sudo', '-n', 'systemctl', $accion, $unidad], null, null, null, 120);
            $p->run();
            if (! $p->isSuccessful()) {
                throw new RuntimeException("systemctl {$accion} {$unidad} falló: " . Str::limit($p->getErrorOutput(), 300));
            }
        }

        sleep(3);

        return ['activo' => trim((new Process(['systemctl', 'is-active', 'asterisk']))->mustRun()->getOutput())];
    }

    private function validarFinal(): array
    {
        $cli = fn (string $cmd) => tap(Process::fromShellCommandline(
            'sudo -n /usr/sbin/asterisk -rx ' . escapeshellarg($cmd), null, null, null, 60
        ))->run()->getOutput();

        $odbc = $cli('odbc show all');
        if (! str_contains($odbc, 'Number of active connections')) {
            throw new RuntimeException('ODBC no reporta conexión activa: la base realtime no está cableada');
        }

        return [
            'odbc'       => 'conectado',
            'modulos'    => substr_count($cli('module show like res_pjsip'), 'res_pjsip'),
            'transports' => trim($cli('pjsip show transports')) !== '' ? 'ok' : 'sin transporte',
        ];
    }

    // ── Utilidades ───────────────────────────────────────────────────────

    private function resumen(): array
    {
        return ProvisionEstado::deEjecucion($this->uuid)->get()
            ->mapWithKeys(fn ($p) => [$p->paso => [
                'estado'  => $p->estado,
                'version' => $p->version_provisionador,
                'error'   => $p->error,
            ]])->all();
    }

    private function di(string $m): void
    {
        ($this->salida)($m);
    }

    /**
     * Reporta la salida de un subproceso en líneas completas.
     *
     * Symfony entrega lo que el sistema le da, en trozos, no en líneas: con
     * `use_pty` activo en sudo, «sudo: a password is required» llegó partido en
     * tres pedazos y se imprimió como tres líneas. En una compilación de media
     * hora eso deja el único registro que el operador ve cortado a media palabra,
     * justo donde hay que leerlo.
     */
    private function emitir(string $buf): void
    {
        $this->restoSalida .= $buf;

        while (($corte = strpos($this->restoSalida, "\n")) !== false) {
            $this->di('    ' . rtrim(substr($this->restoSalida, 0, $corte)));
            $this->restoSalida = substr($this->restoSalida, $corte + 1);
        }
    }

    /** Saca la última línea si el proceso terminó sin cerrarla con \n. */
    private function vaciarSalida(): void
    {
        if ($this->restoSalida !== '') {
            $this->di('    ' . rtrim($this->restoSalida));
            $this->restoSalida = '';
        }
    }
}
