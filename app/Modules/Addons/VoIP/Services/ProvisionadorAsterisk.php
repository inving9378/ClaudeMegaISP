<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\ProvisionEstado;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Orquesta la provisión completa de Asterisk (#9990718 §3).
 *
 * Diez pasos, en este orden y no en otro: **paquete → esquema → configuración →
 * arranque**. Invertir cualquiera deja la central en un estado inconsistente —
 * arrancar antes de configurar levanta un Asterisk que no sabe dónde está su
 * base; configurar antes de crear el esquema escribe un mapeo a tablas que no
 * existen.
 *
 * Cada paso se asienta en `voip_provision_estado` con **la versión del
 * provisionador que lo ejecutó**, para poder reintentar desde donde se quedó sin
 * adivinar si lo ya hecho sigue valiendo (ver el modelo).
 *
 * ─── QUÉ NO HACE ──────────────────────────────────────────────────────────
 *
 * No compila: eso lo hace `provisioning/provisionar-asterisk.sh`, que necesita
 * root. Esta clase lo invoca y lee su resultado. La frontera está ahí a propósito:
 * PHP corre como www-data y no debe instalar paquetes ni tocar systemd.
 *
 * No escribe credenciales a mano: usa `EscritorEnv`, que respalda, escribe atómico
 * y valida.
 */
class ProvisionadorAsterisk
{
    /** Los diez pasos, en orden de ejecución. El orden ES el contrato. */
    public const PASOS = [
        'verificar'    => 'Estado actual del servidor',
        'descargar'    => 'Descarga y verificación del tarball',
        'dependencias' => 'Dependencias de compilación',
        'compilar'     => 'Compilación e instalación',
        'esquema'      => 'Base realtime y alembic upgrade',
        'config'       => 'Configuración desde plantillas',
        'credenciales' => 'Generación de credenciales AMI y ARI',
        'siembra'      => 'Plan de numeración y extensiones',
        'arrancar'     => 'Arranque del servicio',
        'validar'      => 'Validación final',
    ];

    private string $uuid;
    private string $version;
    private array  $manifiesto;
    private bool   $modoDescubrimiento;
    private $salida;   // callable(string) para reportar en vivo

    public function __construct(bool $modoDescubrimiento = false, ?callable $salida = null)
    {
        $this->uuid               = (string) Str::uuid();
        $this->version            = (string) config('requisitos-voip.provisionador_version', '0.0.0');
        $this->manifiesto         = (array) config('requisitos-voip.asterisk', []);
        $this->modoDescubrimiento = $modoDescubrimiento;
        $this->salida             = $salida ?? fn (string $m) => null;
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

        if ($this->modoDescubrimiento) {
            $this->di('MODO DESCUBRIMIENTO: la revisión de Alembic se reportará al final.');
        }

        $revision = null;

        try {
            $this->paso('verificar',    fn () => $this->verificarEstado());
            $this->paso('descargar',    fn () => $this->instalarBinario('descargar'));
            $this->paso('dependencias', fn () => ['nota' => 'lo hace el script de instalación']);
            $this->paso('compilar',     fn () => ['nota' => 'lo hace el script de instalación']);
            $revision = $this->paso('esquema', fn () => $this->aplicarEsquema());
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
     * Un paso `completado` por una versión ANTERIOR no se reutiliza: su criterio de
     * "completado" pudo cambiar, y darlo por bueno sería confiar en un estado que
     * ya no corresponde a la lógica actual.
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
                'estado'           => 'completado',
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

    private function verificarEstado(): array
    {
        $binario  = '/usr/sbin/asterisk';
        $instalada = null;

        if (is_executable($binario)) {
            $p = new Process([$binario, '-V']);
            $p->run();
            if ($p->isSuccessful() && preg_match('/Asterisk\s+(\S+)/', $p->getOutput(), $m)) {
                $instalada = $m[1];
            }
        }

        $pedida = $this->manifiesto['version'] ?? null;

        return [
            'instalada'  => $instalada,
            'pedida'     => $pedida,
            'cumple'     => $instalada !== null && $instalada === $pedida,
            'hay_alembic'=> is_dir(($this->manifiesto['soporte_dir'] ?? '') . '/alembic'),
        ];
    }

    /** Invoca el script que compila. Es lo único que necesita root. */
    private function instalarBinario(string $paso): array
    {
        $estado = $this->verificarEstado();

        if ($estado['cumple'] && $estado['hay_alembic']) {
            return ['omitido' => true, 'motivo' => 'la versión pedida ya está instalada y Alembic preservado'];
        }

        $script = base_path('app/Modules/Addons/VoIP/provisioning/provisionar-asterisk.sh');
        if (! is_file($script)) {
            throw new RuntimeException("No encuentro el script de instalación en {$script}.");
        }

        $env = [
            'ASTERISK_VERSION'             => $this->manifiesto['version'] ?? '',
            'ASTERISK_ORIGEN'              => $this->manifiesto['origen'] ?? '',
            'ASTERISK_ARCHIVO'             => $this->manifiesto['archivo'] ?? '',
            'ASTERISK_SHA256'              => $this->manifiesto['sha256'] ?? '',
            'ASTERISK_IDIOMA'              => $this->manifiesto['idioma'] ?? 'es',
            'ASTERISK_ESPACIO_MIN_MB'      => (string) ($this->manifiesto['espacio_minimo_mb'] ?? 3072),
            'ASTERISK_SOPORTE_DIR'         => $this->manifiesto['soporte_dir'] ?? '/usr/share/megaisp-asterisk',
            'ASTERISK_ESQUEMA_REALTIME'    => (string) ($this->manifiesto['esquema_realtime'] ?? ''),
            'ASTERISK_MODO_DESCUBRIMIENTO' => $this->modoDescubrimiento ? '1' : '0',
            'ASTERISK_DB_HOST'             => (string) config('database.connections.asterisk_rt.host'),
            'ASTERISK_DB_PORT'             => (string) config('database.connections.asterisk_rt.port'),
            'ASTERISK_DB_NAME'             => (string) config('database.connections.asterisk_rt.database'),
            'ASTERISK_DB_USER'             => (string) config('database.connections.asterisk_rt.username'),
            'ASTERISK_DB_PASSWORD'         => (string) config('database.connections.asterisk_rt.password'),
        ];

        // Timeout amplio: compilar Asterisk pasa de media hora en una VM modesta.
        $p = Process::fromShellCommandline('sudo -n bash ' . escapeshellarg($script), base_path(), $env, null, 5400);
        $p->run(fn ($tipo, $buf) => $this->di('    ' . rtrim($buf)));

        if (! $p->isSuccessful()) {
            throw new RuntimeException('el script de instalación salió con código ' . $p->getExitCode()
                . '. El log completo queda en /var/log/megaisp/.');
        }

        return ['script' => 'ok', 'salida_final' => Str::limit(trim($p->getOutput()), 500)];
    }

    private function aplicarEsquema(): array
    {
        $soporte = $this->manifiesto['soporte_dir'] ?? '/usr/share/megaisp-asterisk';
        $dbm     = $soporte . '/alembic';
        $ini     = $dbm . '/config.ini';

        if (! is_dir($dbm)) {
            throw new RuntimeException("no está el árbol de Alembic en {$dbm}: sin él no hay esquema");
        }
        if (! is_file($ini)) {
            throw new RuntimeException("no está {$ini}: lo genera el script de instalación con las credenciales");
        }

        $p = Process::fromShellCommandline('sudo -n alembic -c ' . escapeshellarg($ini) . ' upgrade head', $dbm, null, null, 900);
        $p->run(fn ($t, $b) => $this->di('    ' . rtrim($b)));

        if (! $p->isSuccessful()) {
            throw new RuntimeException('alembic upgrade head falló: ' . Str::limit($p->getErrorOutput(), 400)
                . ' — el esquema NO se parchea a mano: si el módulo necesita una columna que Alembic no da, se corrige el módulo.');
        }

        $revision = $this->revisionAplicada();
        $esperada = $this->manifiesto['esquema_realtime'] ?? null;

        if (! $this->modoDescubrimiento && $esperada && $revision !== $esperada) {
            throw new RuntimeException("la revisión aplicada ({$revision}) no es la que declara el manifiesto ({$esperada})");
        }

        @file_put_contents($soporte . '/VERSION-ESQUEMA', $revision . "\n");

        return ['revision' => $revision, 'esperada' => $esperada, 'descubrimiento' => $this->modoDescubrimiento];
    }

    private function revisionAplicada(): ?string
    {
        try {
            $r = \DB::connection('asterisk_rt')->select('SELECT version_num FROM alembic_version LIMIT 1');

            return $r[0]->version_num ?? null;
        } catch (\Throwable $e) {
            throw new RuntimeException('no se pudo leer la tabla estándar alembic_version: ' . $e->getMessage());
        }
    }

    private function escribirConfiguracion(): array
    {
        return app(GeneradorConfigAsterisk::class)->generar();
    }

    private function generarCredenciales(): array
    {
        return app(GeneradorCredenciales::class)->generarYPersistir($this->uuid, $this->version);
    }

    private function sembrar(): array
    {
        Artisan::call('db:seed', ['--class' => \App\Modules\Addons\VoIP\Seeders\PlanNumeracionSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => \App\Modules\Addons\VoIP\Seeders\ExtensionesArranqueSeeder::class, '--force' => true]);

        return [
            'rangos'      => \App\Modules\Addons\VoIP\Models\RangoNumeracion::count(),
            'perfiles'    => \App\Modules\Addons\VoIP\Models\PerfilExtension::count(),
            'extensiones' => \App\Modules\Addons\VoIP\Models\Extension::count(),
        ];
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
}
