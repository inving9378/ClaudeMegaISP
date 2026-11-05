<?php

namespace App\Console\Commands\Active;

use App\Models\Referrals\ReferralCommission;
use App\Models\Seller;
use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\CompositeScoreService;
use App\Modules\Addons\Talento\Services\DashboardService;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Support\PayWeek;
use App\Services\CalculateBalanceSellerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

/**
 * UNI-T A2 — Línea base de cálculos (SOLO LECTURA). Guarda en storage/app/uni/linea-base/<fecha>.json
 * lo que hoy calculan el pago, el desglose, los tableros, el escalafón, Vendedores y Embajadores,
 * para poder comparar después de cada cambio (--comparar=<archivo>).
 * No escribe en la BD: no usa simular()/calculate() ni transacciones; solo el servicio de lectura
 * countBillableUnits/breakdown y los previews. El JSON puede traer ids/montos por persona: vive
 * solo en storage (gitignored), nunca en el repo.
 */
class UniLineaBaseCommand extends Command
{
    protected $signature = 'uni:linea-base {--semanas=8 : Semanas PayWeek hacia atrás} {--comparar= : Archivo JSON previo contra el cual comparar} {--salida= : Nombre de archivo (default fecha-hora)}';

    protected $description = 'UNI-T A2: línea base de cálculos (solo lectura) para comparar antes/después de unificar';

    public function handle(LiquidationService $liq, DashboardService $dash, CompositeScoreService $score): int
    {
        $semanas = max(1, (int) $this->option('semanas'));
        $escrituras = 0;
        DB::listen(function ($q) use (&$escrituras) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $q->sql)) {
                $escrituras++;
            }
        });

        $ventanas = $this->ventanas($semanas);
        $datos = [
            'generado'          => now()->toIso8601String(),
            'semanas'           => array_map(fn ($w) => [$w['period_start'], $w['period_end']], $ventanas),
            'colaboradores'     => $this->colaboradores($liq, $dash, $ventanas),
            'escalafon_actual'  => $this->seguro(fn () => $score->scoreAll()),
            'vendedores'        => $this->vendedores($ventanas),
            'embajadores'       => $this->embajadores(),
            'tareas'            => $this->tareas(),
            'permisos'          => $this->permisos(),
        ];
        $datos['casos_representativos'] = $this->casos($datos);
        $datos['escrituras_detectadas'] = $escrituras;

        $dir = storage_path('app/uni/linea-base');
        File::ensureDirectoryExists($dir);
        $nombre = $this->option('salida') ?: now()->format('Y-m-d_His') . '.json';
        $ruta = $dir . '/' . basename($nombre);
        File::put($ruta, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Línea base guardada: {$ruta} (escrituras SQL detectadas: {$escrituras})");

        if ($cmp = $this->option('comparar')) {
            return $this->comparar($datos, $cmp);
        }
        return $escrituras === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function ventanas(int $n): array
    {
        $w = PayWeek::lastCompleted();
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $w;
            $w = PayWeek::boundsFor($w['start_instant']->copy()->subSecond());
        }
        return array_reverse($out);
    }

    private function seguro(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return ['error' => class_basename($e) . ': ' . $e->getMessage()];
        }
    }

    private function colaboradores($liq, $dash, array $ventanas): array
    {
        $out = [];
        foreach (TalentoColaborador::activos()->get() as $c) {
            $semanas = [];
            foreach ($ventanas as $w) {
                $pago = $liq->breakdown($c->id, $w); // mismo conteo que la liquidación
                $tablero = $this->seguro(fn () => $dash->tecnicoPreview($c->id, Carbon::parse($w['period_start'])->toDateString()));
                $semanas[$w['period_start']] = [
                    'desglose' => $pago,
                    'tablero'  => is_array($tablero) ? array_intersect_key($tablero, array_flip(['total_units', 'units', 'internal_units', 'external_points', 'gross_pay', 'error'])) : $tablero,
                ];
            }
            $out[$c->id] = $semanas;
        }
        return $out;
    }

    private function vendedores(array $ventanas): array
    {
        $svc = new CalculateBalanceSellerService();
        $out = [];
        foreach (Seller::with('user')->get() as $s) {
            if (!$s->user) {
                continue;
            }
            foreach ($ventanas as $w) {
                $out[$s->id][$w['period_start']] = $this->seguro(
                    fn () => $svc->getSalary('week', $s->user, $w['period_start'], $w['period_end'])
                );
            }
        }
        return $out;
    }

    private function embajadores(): array
    {
        return ReferralCommission::query()
            ->selectRaw('beneficiary_id, status, COUNT(*) n, ROUND(SUM(commission_amount),2) monto')
            ->groupBy('beneficiary_id', 'status')->get()->toArray();
    }

    private function tareas(): array
    {
        return [
            'por_estado' => Task::query()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->toArray(),
            'por_fecha'  => Task::query()->selectRaw('DATE(COALESCE(validated_at, finish_at, created_at)) d, COUNT(*) n')
                ->groupBy('d')->orderByDesc('d')->limit(60)->pluck('n', 'd')->toArray(),
            'talento'    => Task::whereNotNull('talento_type_id')->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->toArray(),
        ];
    }

    private function permisos(): array
    {
        $out = [];
        foreach (Role::withCount('users')->get() as $r) {
            $out[$r->name] = ['usuarios' => $r->users_count, 'permisos' => $r->permissions()->count()];
        }
        return $out;
    }

    /** Un caso por clase: con producción, sin producción, y el de mayor diferencia pago↔tablero. */
    private function casos(array $d): array
    {
        $mayor = ['id' => null, 'dif' => -1];
        $con = $sin = null;
        foreach ($d['colaboradores'] as $id => $sem) {
            $tot = 0;
            foreach ($sem as $s) {
                $tot += $s['desglose']['units'] ?? 0;
                $t = $s['tablero']['total_units'] ?? $s['tablero']['units'] ?? null;
                if ($t !== null && abs(($s['desglose']['units'] ?? 0) - $t) > $mayor['dif']) {
                    $mayor = ['id' => $id, 'dif' => abs(($s['desglose']['units'] ?? 0) - $t)];
                }
            }
            $tot > 0 ? $con ??= $id : $sin ??= $id;
        }
        return ['colaborador_con_unidades' => $con, 'colaborador_sin_unidades' => $sin, 'mayor_diferencia_pago_tablero' => $mayor['id']];
    }

    private function comparar(array $nuevo, string $archivo): int
    {
        $ruta = is_file($archivo) ? $archivo : storage_path('app/uni/linea-base/' . basename($archivo));
        if (!is_file($ruta)) {
            $this->error("No existe {$archivo}");
            return self::FAILURE;
        }
        $viejo = json_decode(File::get($ruta), true);
        $plano = function (array $a) {
            unset($a['generado'], $a['escrituras_detectadas']);
            $o = [];
            $f = function ($v, $p) use (&$f, &$o) {
                if (is_array($v)) { foreach ($v as $k => $x) { $f($x, $p . '/' . $k); } } else { $o[$p] = $v; }
            };
            $f($a, '');
            return $o;
        };
        $a = $plano($viejo);
        $b = $plano(json_decode(json_encode($nuevo), true));
        $difs = 0;
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
            if (($a[$k] ?? null) !== ($b[$k] ?? null)) {
                $difs++;
                if ($difs <= 50) {
                    $this->line("{$k}: " . json_encode($a[$k] ?? null) . ' → ' . json_encode($b[$k] ?? null));
                }
            }
        }
        $this->info($difs === 0 ? 'Sin diferencias.' : "{$difs} diferencia(s).");
        return $difs === 0 ? self::SUCCESS : self::FAILURE;
    }
}
