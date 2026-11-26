<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Services\CompresorGrabaciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pasa a MP3 las grabaciones de llamadas que ya terminaron (26-nov, decisión de Irving).
 *
 * Asterisk sigue grabando en WAV durante la llamada; este comando, cada 5 minutos, toma
 * los WAV que ya se cerraron y los convierte con `CompresorGrabaciones` (75% menos de
 * espacio, se oye igual). Primero los más nuevos: las viejas de la cola se van
 * convirtiendo en las vueltas siguientes.
 *
 * Solo toca un WAV si:
 *   - lleva más de --edad segundos sin cambiar (la llamada ya colgó y MixMonitor lo cerró);
 *   - su llamada ya está en `voip_llamadas` (Asterisk escribe la fila al colgar).
 * Un WAV sin fila se deja como está.
 *
 * Si una conversión falla, el WAV se queda y esa grabación no se reintenta en 24 h (para
 * no llenar el log cada 5 minutos con la misma).
 *
 * SEGURO POR DEFECTO, como la purga: sin --force solo dice qué haría.
 */
class ComprimirGrabacionesCommand extends Command
{
    protected $signature = 'megavoz:comprimir-grabaciones
                            {--lote=200 : Máximo de grabaciones por vuelta}
                            {--edad=120 : Segundos que el WAV debe llevar sin cambiar}
                            {--max-segundos=240 : Deja de tomar grabaciones nuevas pasado este tiempo}
                            {--force : Convertir de verdad (sin esto, solo simula)}';

    protected $description = 'Pasa a MP3 las grabaciones de llamadas ya terminadas (75% menos de espacio); el WAV se quita solo cuando el MP3 quedó bien';

    private const FALLO_CACHE = 'megavoz:comprimir:fallo:';

    public function handle(CompresorGrabaciones $compresor): int
    {
        $dir = rtrim((string) config('voip.grabaciones.dir'), '/');

        if ($dir === '' || ! str_starts_with($dir, '/') || in_array($dir, PurgarGrabacionesCommand::DIRS_PROHIBIDOS, true)) {
            $this->error("[megavoz:comprimir-grabaciones] '{$dir}' no es una carpeta de grabaciones válida: no se toca nada.");
            return self::FAILURE;
        }
        if (! is_dir($dir)) {
            $this->info("[megavoz:comprimir-grabaciones] No existe {$dir}: nada que hacer.");
            return self::SUCCESS;
        }
        if (! CompresorGrabaciones::hayFfmpeg()) {
            $this->warn('[megavoz:comprimir-grabaciones] Falta ffmpeg: las grabaciones se quedan en WAV. Lo instala «Instalar dependencias» de VoIP.');
            if (Cache::add('megavoz:comprimir:sin-ffmpeg', 1, now()->addDay())) {
                Log::warning('[megavoz:comprimir-grabaciones] Falta ffmpeg; las grabaciones se quedan en WAV hasta correr «Instalar dependencias» de VoIP.');
            }
            return self::SUCCESS;
        }

        $compresor->limpiarTemporales($dir);

        $lote       = max(1, (int) $this->option('lote'));
        $candidatos = $this->candidatos($dir, max(0, (int) $this->option('edad')), $lote);
        $apply      = (bool) $this->option('force');

        if ($candidatos === []) {
            $this->info('[megavoz:comprimir-grabaciones] Nada que convertir.');
            return self::SUCCESS;
        }

        if (! $apply) {
            $mb = array_sum(array_map('filesize', $candidatos)) / 1048576;
            $this->warn(sprintf('[megavoz:comprimir-grabaciones] SIMULACIÓN: convertiría %d grabación(es), %.1f MB en WAV (quedarían en ~%.1f MB).',
                count($candidatos), $mb, $mb * 0.25));
            $this->line('Para convertir de verdad: php artisan megavoz:comprimir-grabaciones --force');
            return self::SUCCESS;
        }

        $limite = microtime(true) + max(10, (int) $this->option('max-segundos'));
        $ok = $fallas = $antes = $despues = 0;

        foreach ($candidatos as $wav) {
            if (microtime(true) > $limite) {
                break;
            }

            $r = $compresor->comprimir($wav, function (string $wavNombre, string $mp3Nombre): void {
                $n = DB::connection('asterisk_rt')->table('voip_llamadas')
                    ->where('grabacion', $wavNombre)
                    ->update(['grabacion' => $mp3Nombre]);
                if ($n === 0) {
                    throw new RuntimeException('ninguna llamada apunta a ' . $wavNombre);
                }
            });

            if ($r['ok']) {
                $ok++;
                $antes   += $r['antes'];
                $despues += $r['despues'];
                if ($r['aviso']) {
                    Log::warning('[megavoz:comprimir-grabaciones] ' . basename($wav) . ': ' . $r['aviso']);
                }
                continue;
            }

            $fallas++;
            Cache::put(self::FALLO_CACHE . basename($wav), 1, now()->addDay());
            Log::warning('[megavoz:comprimir-grabaciones] ' . basename($wav) . ' se queda en WAV: ' . $r['error']);
            $this->line('  ! ' . basename($wav) . ': ' . $r['error']);
        }

        $msg = sprintf('[megavoz:comprimir-grabaciones] %d convertida(s): %.1f MB → %.1f MB.%s',
            $ok, $antes / 1048576, $despues / 1048576, $fallas ? " {$fallas} se quedaron en WAV (ver el log)." : '');
        $this->info($msg);
        if ($ok > 0 || $fallas > 0) {
            Log::info($msg);
        }

        return self::SUCCESS;
    }

    /**
     * WAV ya cerrados, del más nuevo al más viejo, que tienen su fila en voip_llamadas y no
     * fallaron en las últimas 24 h.
     *
     * @return array<int, string> rutas completas
     */
    private function candidatos(string $dir, int $edad, int $lote): array
    {
        $corte    = time() - $edad;
        $archivos = [];
        foreach (glob($dir . '/*.wav') ?: [] as $f) {
            $m = @filemtime($f);
            if ($m !== false && $m <= $corte && ! Cache::has(self::FALLO_CACHE . basename($f))) {
                $archivos[basename($f)] = $m;
            }
        }
        arsort($archivos);

        $elegidos = [];
        foreach (array_chunk(array_keys($archivos), 500) as $nombres) {
            $conFila = DB::connection('asterisk_rt')->table('voip_llamadas')
                ->whereIn('grabacion', $nombres)
                ->pluck('grabacion')
                ->flip();
            foreach ($nombres as $n) {
                if (isset($conFila[$n])) {
                    $elegidos[] = $dir . '/' . $n;
                    if (count($elegidos) >= $lote) {
                        return $elegidos;
                    }
                }
            }
        }

        return $elegidos;
    }
}
