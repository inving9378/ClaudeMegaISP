<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\Talento\Models\TalentoCompensationRule;
use App\Modules\Addons\Talento\Models\TalentoMeta;
use App\Modules\Addons\Talento\Services\MetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * UNI-T M2c (#1021) — Proyecta a talento_metas la cuota de talento_compensation_rules (vía cuotaSemanal())
 * y los mínimos de talento_weekly_rules. Una meta por concepto+alcance. No modifica las tablas origen ni pagos.
 * Sin --aplicar solo simula. Con --aplicar (solo DEV, interruptor unificacion.metas_m2) respalda talento_metas
 * y guarda los ids creados para revertir. Reversión: --revertir=<archivo json>.
 * Cuota y mínimo que divergen se SEÑALAN, no se resuelven.
 */
class TalentoMetasBackfillCommand extends Command
{
    protected $signature = 'talento:metas-backfill
                            {--aplicar : escribe las metas (sin esto es dry-run)}
                            {--revertir= : ruta del json de reversión generado por --aplicar}
                            {--dir=/var/backups/mysql/preservados/uni : carpeta de respaldos}';

    protected $description = 'UNI-T M2c: backfill de talento_metas desde reglas existentes (dry-run por defecto), con respaldo y reversión.';

    public function handle(MetaService $svc): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Solo DEV.');

            return 1;
        }
        if ($f = $this->option('revertir')) {
            return $this->revertir($f);
        }

        [$metas, $diverge] = $this->proyectar();

        $nuevas = array_values(array_filter($metas, fn ($m) => ! TalentoMeta::where('concepto', $m['concepto'])
            ->where('alcance_tipo', $m['alcance_tipo'])->where('alcance_clave', $m['alcance_clave'])->exists()));

        $this->info('Metas proyectadas: '.count($metas).' · nuevas: '.count($nuevas).' · ya existentes (omitidas): '.(count($metas) - count($nuevas)));
        foreach ($nuevas as $m) {
            $this->line(sprintf('  + %s [%s:%s] %s %s (origen %s #%d)', $m['concepto'], $m['alcance_tipo'], $m['alcance_clave'] ?? '-', $m['valor'], $m['periodo'], $m['origen'], $m['origen_id']));
        }
        $this->info('Divergencias cuota vs mínimo (solo señaladas): '.count($diverge));
        foreach ($diverge as $d) {
            $this->warn('  ! '.$d);
        }

        if (! $this->option('aplicar')) {
            $this->comment('Dry-run: no se escribió nada. Usa --aplicar tras la entrada en docs/bitacora/.');

            return 0;
        }
        if (! config('unificacion.metas_m2')) {
            $this->error('UNIFICACION_METAS_M2 está apagado; --aplicar abortado.');

            return 1;
        }
        if (! $nuevas) {
            $this->info('Nada que escribir.');

            return 0;
        }

        $dir = rtrim((string) $this->option('dir'), '/');
        if (! is_dir($dir) || ! is_writable($dir)) {
            $this->error("No puedo escribir en $dir; abortado antes de modificar.");

            return 1;
        }
        $sello = date('Ymd-His');
        $sql = "$dir/uni-metas-$sello.sql";
        $c = config('database.connections.mysql');
        $p = new Process(['mysqldump', '--single-transaction', '-h', $c['host'], '-u', $c['username'], $c['database'], 'talento_metas'],
            null, ['MYSQL_PWD' => $c['password']]);
        $p->setTimeout(300)->run();
        if (! $p->isSuccessful() || strlen($p->getOutput()) < 300) {
            $this->error('Respaldo falló; abortado antes de modificar.');

            return 1;
        }
        file_put_contents($sql, $p->getOutput());
        $rev = "$dir/uni-metas-$sello-revertir.json";

        $ids = [];
        DB::transaction(function () use ($svc, $nuevas, &$ids) {
            foreach ($nuevas as $m) {
                $ids[] = $svc->crear($m + ['motivo' => 'Backfill UNI-T M2c', 'vigente_desde' => today()->toDateString()])->id;
            }
        });
        file_put_contents($rev, json_encode(['metas_creadas' => $ids]));
        $this->info(count($ids)." metas creadas. Respaldo: $sql. Reversión: php artisan talento:metas-backfill --revertir=$rev");

        return 0;
    }

    /** @return array{0: array<int,array>, 1: array<int,string>} */
    private function proyectar(): array
    {
        $metas = [];
        $cuotas = [];

        foreach (TalentoCompensationRule::where('active', true)->orderBy('id')->get() as $r) {
            $cuota = TalentoCompensationRule::cuotaSemanal($r->only(["period", "weekly_quota_units"]));
            if ($cuota <= 0) {
                continue;
            }
            $metas[] = $this->meta('cuota_unidades', 'rol', 'tipo:'.$r->target_type, $cuota, 'unidades', 'regla_compensacion', $r->id, true);
            $cuotas[$r->target_type] = [$cuota, $r->id];
        }

        $diverge = [];
        foreach (DB::table('talento_weekly_rules')->where('active', 1)->orderBy('id')->get() as $w) {
            $roles = json_decode((string) $w->roles, true) ?: [];
            sort($roles);
            $clave = $roles ? implode('+', $roles) : null;
            $tipo = $clave ? 'rol' : 'global';
            if ($w->min_sales !== null) {
                $metas[] = $this->meta('minimo_semanal_'.$w->metric_type, $tipo, $clave, $w->min_sales, 'unidades', 'regla_semanal', $w->id, false);
            }
            if ($w->min_prospects !== null) {
                $metas[] = $this->meta('minimo_prospectos_'.$w->metric_type, $tipo, $clave, $w->min_prospects, 'prospectos', 'regla_semanal', $w->id, false);
            }
            if ($w->metric_type === 'tecnico' && $w->min_sales !== null && isset($cuotas['technician']) && (int) $w->min_sales !== $cuotas['technician'][0]) {
                $diverge[] = "Regla semanal #{$w->id} ({$w->name}) mínimo {$w->min_sales} ≠ cuota técnica {$cuotas['technician'][0]} (regla de compensación #{$cuotas['technician'][1]})";
            }
        }

        // Una meta por concepto+alcance: si dos reglas dan la misma, gana la primera y se señala.
        $vistas = [];
        $unicas = [];
        foreach ($metas as $m) {
            $k = $m['concepto'].'|'.$m['alcance_tipo'].'|'.$m['alcance_clave'];
            if (isset($vistas[$k])) {
                if ((float) $vistas[$k]['valor'] !== (float) $m['valor']) {
                    $diverge[] = "{$m['concepto']} [{$m['alcance_clave']}]: {$m['origen']} #{$m['origen_id']} ({$m['valor']}) difiere de #{$vistas[$k]['origen_id']} ({$vistas[$k]['valor']}); se conserva la primera";
                }
                continue;
            }
            $vistas[$k] = $m;
            $unicas[] = $m;
        }

        return [$unicas, $diverge];
    }

    private function meta(string $concepto, string $tipo, ?string $clave, $valor, string $unidad, string $origen, int $origenId, bool $afectaPago): array
    {
        return [
            'concepto' => $concepto, 'alcance_tipo' => $tipo, 'alcance_clave' => $clave, 'periodo' => 'semanal',
            'valor' => $valor, 'unidad' => $unidad, 'afecta_pago' => $afectaPago, 'origen' => $origen, 'origen_id' => $origenId,
        ];
    }

    private function revertir(string $f): int
    {
        $json = json_decode((string) @file_get_contents($f), true);
        if (! is_array($json) || ! isset($json['metas_creadas'])) {
            $this->error('Archivo de reversión inválido.');

            return 1;
        }
        $n = TalentoMeta::withTrashed()->whereIn('id', $json['metas_creadas'])->where('origen', '!=', 'manual')->forceDelete();
        $this->info("Revertidas $n metas.");

        return 0;
    }
}
