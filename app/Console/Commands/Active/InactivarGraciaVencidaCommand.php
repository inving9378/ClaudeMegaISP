<?php

namespace App\Console\Commands\Active;

use App\Models\TypeBilling;
use App\Modules\Core\Clientes\Models\Client;
use App\Services\ClientMainInformationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Regla del 2026-10-14 (recurrentes): al vencer el periodo de gracia el cliente pasa a INACTIVO
 * (lo que lo agrega a la address list y detiene la facturación).
 *
 * Seguridad por diseño: NO está programado en Kernel y NO actúa sin una lista explícita. Sin
 * `--apply` solo lista (simulación); con `--apply` exige `--ids=` con los clientes aprobados, así
 * nunca inactiva en masa por accidente. Antes de usar `--apply` en producción: bitácora previa en
 * docs/bitacora/ y respaldo (regla permanente de CLAUDE.md).
 *
 *   php artisan clientes:inactivar-gracia-vencida                        # lista (simulación)
 *   php artisan clientes:inactivar-gracia-vencida --csv=/ruta/lista.csv  # lista + CSV
 *   php artisan clientes:inactivar-gracia-vencida --apply --ids=12,34    # inactiva SOLO esos
 */
class InactivarGraciaVencidaCommand extends Command
{
    protected $signature = 'clientes:inactivar-gracia-vencida
        {--apply : Inactiva de verdad (requiere --ids)}
        {--ids= : IDs de cliente aprobados, separados por coma}
        {--csv= : Ruta de un CSV donde guardar la lista}';

    protected $description = 'Recurrentes con la gracia vencida (Activo/Bloqueado): lista o inactiva solo los IDs aprobados';

    public function handle(): int
    {
        $ahora = Carbon::now();
        $filas = DB::table('clients as c')
            ->join('billing_configurations as b', 'b.client_id', '=', 'c.id')
            ->join('client_main_information as m', 'm.client_id', '=', 'c.id')
            ->where('b.type_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT)
            ->whereNotNull('c.fecha_fin_periodo_gracia')
            ->where('c.fecha_fin_periodo_gracia', '<', $ahora)
            ->whereIn('m.estado', ['Activo', 'Bloqueado'])
            ->orderBy('c.fecha_fin_periodo_gracia')
            ->get(['c.id', 'm.name', 'm.estado', 'c.fecha_fin_periodo_gracia', 'c.fecha_corte', 'c.fecha_suspension']);

        $saldos = DB::table('balances')->where('balanceable_type', 'like', '%Client')
            ->whereIn('balanceable_id', $filas->pluck('id'))->pluck('amount', 'balanceable_id');

        $lista = $filas->map(fn ($f) => [
            'cliente' => $f->id,
            'nombre' => $f->name,
            'estado' => $f->estado,
            'gracia_vencio' => substr($f->fecha_fin_periodo_gracia, 0, 10),
            'dias_vencida' => (int) Carbon::parse($f->fecha_fin_periodo_gracia)->diffInDays($ahora),
            'saldo' => (float) ($saldos[$f->id] ?? 0),
            'fecha_corte' => $f->fecha_corte ? substr($f->fecha_corte, 0, 10) : '',
            'bloqueado_desde' => $f->fecha_suspension ? substr($f->fecha_suspension, 0, 10) : '',
        ]);

        $this->info('Recurrentes con gracia vencida (Activo/Bloqueado): ' . $lista->count());

        if ($csv = $this->option('csv')) {
            $h = fopen($csv, 'w');
            fputcsv($h, array_keys($lista->first() ?? ['cliente' => 0]));
            foreach ($lista as $fila) {
                fputcsv($h, $fila);
            }
            fclose($h);
            $this->info("CSV: {$csv}");
        }

        if (!$this->option('apply')) {
            $this->table(array_keys($lista->first() ?? ['cliente' => 0]), $lista->take(15)->all());
            $this->warn('Simulación: no se cambió nada. Para inactivar use --apply --ids=<aprobados>.');

            return self::SUCCESS;
        }

        $ids = collect(explode(',', (string) $this->option('ids')))->map(fn ($v) => (int) trim($v))->filter()->unique();
        if ($ids->isEmpty()) {
            $this->error('--apply exige --ids= con los clientes aprobados (no se inactiva en masa).');

            return self::FAILURE;
        }

        $permitidos = $lista->pluck('cliente');
        $fuera = $ids->diff($permitidos);
        if ($fuera->isNotEmpty()) {
            $this->error('Estos IDs no están en la lista de gracia vencida y NO se tocan: ' . $fuera->implode(', '));
        }

        $hechos = 0;
        foreach ($ids->intersect($permitidos) as $id) {
            $client = Client::find($id);
            if (!$client) {
                continue;
            }
            (new ClientMainInformationService($id))->setStateInactive();
            $client->client_grace_period()->delete();
            activity()->tap(function ($a) use ($id) {
                $a->client_id = $id;
            })->log("Cliente #{$id} pasado a inactivo por periodo de gracia vencido (clientes:inactivar-gracia-vencida)");
            $hechos++;
        }

        $this->info("Inactivados: {$hechos}");

        return self::SUCCESS;
    }
}
