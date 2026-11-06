<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Item #1140 (de #576): PLAN de rotación de contraseñas PPPoE adivinables. SOLO LECTURA.
 *
 * Decisión de Irving (q4): el circuito no ejecuta la rotación real. Este comando solo clasifica las
 * cuentas adivinables en (a) rotables por ONU vía SmartOLT (olt_onus.wan_mode = pppoe) y
 * (b) lista de visita técnica (el resto). No escribe nada, no llama a SmartOLT/RADIUS y NUNCA
 * imprime contraseñas. El prefijo publicado no vive en el repo: se pasa en --prefijo al correrlo.
 */
class PppoeRotacionPlanCommand extends Command
{
    protected $signature = 'pppoe:rotacion-plan
                            {--prefijo= : Prefijo publicado de contraseña (prefijo + número de cliente)}
                            {--csv= : Ruta donde guardar el plan SIN contraseñas (id de servicio, cliente, grupo)}';

    protected $description = 'Plan de rotación de PPPoE adivinables: ONU pppoe (noche, lotes chicos) vs visita técnica (solo lectura)';

    public function handle(): int
    {
        $prefijo = (string) $this->option('prefijo');
        $servicios = DB::table('client_internet_services as s')
            ->leftJoin('olt_onus as o', 'o.service_id', '=', 's.id')
            ->whereNotNull('s.password')->where('s.password', '!=', '')
            ->select('s.id', 's.client_id', 's.user', 's.password', 'o.id as onu_id', 'o.wan_mode')
            ->orderBy('s.id')->get();

        $plan = ['onu_pppoe' => [], 'visita_tecnica' => []];
        foreach ($servicios as $s) {
            $cid = (string) $s->client_id;
            $candidatas = [(string) $s->user];
            if ($prefijo !== '') {
                $candidatas[] = $prefijo . $cid;
                $candidatas[] = $prefijo . ltrim($cid, '0');
                $candidatas[] = $prefijo . str_pad(ltrim($cid, '0'), 6, '0', STR_PAD_LEFT);
            }
            if (! in_array((string) $s->password, array_filter($candidatas), true)) {
                continue;
            }
            $grupo = ($s->onu_id && strtolower((string) $s->wan_mode) === 'pppoe') ? 'onu_pppoe' : 'visita_tecnica';
            $plan[$grupo][] = [$s->id, $s->client_id, $grupo];
        }

        $this->info('Adivinables: ' . (count($plan['onu_pppoe']) + count($plan['visita_tecnica'])));
        $this->line(' · Rotables por ONU (SmartOLT, de noche, lotes chicos): ' . count($plan['onu_pppoe']));
        $this->line(' · Visita técnica: ' . count($plan['visita_tecnica']));
        if ($prefijo === '') {
            $this->warn('Sin --prefijo solo se detecta password == usuario; pásalo para el criterio completo.');
        }

        if ($ruta = $this->option('csv')) {
            $fh = fopen($ruta, 'w');
            fputcsv($fh, ['service_id', 'client_id', 'grupo']);
            foreach (array_merge($plan['onu_pppoe'], $plan['visita_tecnica']) as $fila) {
                fputcsv($fh, $fila);
            }
            fclose($fh);
            $this->info("Plan guardado en $ruta (sin contraseñas).");
        }

        return self::SUCCESS;
    }
}
