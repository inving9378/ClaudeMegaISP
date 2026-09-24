<?php

namespace App\Modules\Addons\VoIP\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retención de grabaciones de la cola (MegaVoz Fase 3).
 *
 * Borra por ANTIGÜEDAD (mtime) — a diferencia de backups:purge-test (keep-last-N
 * por conteo), aquí la cadencia es una llamada tras otra y lo que importa es
 * cuántos días conservar audio, no cuántos archivos.
 *
 * Borra el ARCHIVO, no la fila de voip_llamadas: el registro de la llamada
 * (quién, cuándo, cuánto duró) es un dato de negocio que sobrevive a la
 * retención de audio; solo se limpia su columna `grabacion` para que deje de
 * apuntar a un archivo que ya no existe.
 *
 * SEGURO POR DEFECTO: sin --force solo simula. Guard de path duro: el
 * directorio configurado tiene que ser una ruta absoluta y no puede ser la
 * raíz ni uno de los directorios grandes del sistema — un valor mal puesto en
 * .env no debe poder purgar cualquier cosa.
 *
 * voip_llamadas vive en la conexión `asterisk_rt` (misma base física que
 * escribe Asterisk vía cdr_adaptive_odbc), no en la base del app.
 */
class PurgarGrabacionesCommand extends Command
{
    protected $signature = 'megavoz:purgar-grabaciones
                            {--dias= : Días de retención (default: config voip.grabaciones.retencion_dias)}
                            {--force : Ejecutar el borrado real (sin esto, solo simula)}';

    protected $description = 'Retención de grabaciones de llamadas de la cola MegaVoz — borra por antigüedad, conserva la fila de voip_llamadas';

    private const DIRS_PROHIBIDOS = ['/', '', '/root', '/home', '/var', '/etc', '/usr', '/bin', '/tmp'];

    public function handle(): int
    {
        $dir = rtrim((string) config('voip.grabaciones.dir'), '/');

        if ($dir === '' || ! str_starts_with($dir, '/') || in_array($dir, self::DIRS_PROHIBIDOS, true)) {
            $this->error("[megavoz:purgar-grabaciones] Guard de path duro: '{$dir}' no es un directorio seguro para purgar — abortando sin tocar nada.");
            Log::warning("[megavoz:purgar-grabaciones] Guard de path duro abortó: dir='{$dir}'");
            return self::FAILURE;
        }

        if (! is_dir($dir)) {
            $this->info("[megavoz:purgar-grabaciones] No existe {$dir} — nada que hacer.");
            return self::SUCCESS;
        }

        $dias  = (int) ($this->option('dias') ?? config('voip.grabaciones.retencion_dias', 90));
        $corte = now()->subDays($dias)->getTimestamp();
        $apply = (bool) $this->option('force');

        $candidatos = [];
        foreach (glob($dir . '/*.wav') as $archivo) {
            $mtime = filemtime($archivo);
            if ($mtime !== false && $mtime < $corte) {
                $candidatos[] = ['archivo' => $archivo, 'mtime' => $mtime, 'size' => filesize($archivo) ?: 0];
            }
        }

        $modo = $apply ? 'BORRADO REAL (--force)' : 'DRY-RUN (simulación)';
        $this->line("[megavoz:purgar-grabaciones] {$modo} — retención {$dias} días · " . count($candidatos) . ' candidato(s)');

        if (empty($candidatos)) {
            $this->info('[megavoz:purgar-grabaciones] Nada que purgar.');
            return self::SUCCESS;
        }

        $freed = 0;
        $count = 0;
        foreach ($candidatos as $c) {
            $freed += $c['size'];
            $this->line(sprintf(
                '  %s %-40s %6d KB  %s',
                $apply ? '✗' : '✗(simulado)',
                basename($c['archivo']),
                (int) ($c['size'] / 1024),
                date('Y-m-d H:i', $c['mtime'])
            ));

            if (! $apply) {
                continue;
            }

            if (@unlink($c['archivo'])) {
                $count++;
                DB::connection('asterisk_rt')->table('voip_llamadas')
                    ->where('grabacion', basename($c['archivo']))
                    ->update(['grabacion' => null]);
                Log::info('[megavoz:purgar-grabaciones] eliminado ' . basename($c['archivo']));
            } else {
                Log::error('[megavoz:purgar-grabaciones] NO se pudo eliminar ' . $c['archivo']);
                $this->error("    ! no se pudo eliminar {$c['archivo']}");
            }
        }

        $freedMb = round($freed / 1048576, 1);

        if ($apply) {
            $this->info("\n[megavoz:purgar-grabaciones] OK — {$count} grabación(es) eliminada(s), {$freedMb} MB liberados.");
        } else {
            $this->warn("\n[megavoz:purgar-grabaciones] DRY-RUN — borraría " . count($candidatos) . " grabación(es), liberaría {$freedMb} MB.");
            $this->line('[megavoz:purgar-grabaciones] Para ejecutar el borrado real: php artisan megavoz:purgar-grabaciones --force');
        }

        return self::SUCCESS;
    }
}
