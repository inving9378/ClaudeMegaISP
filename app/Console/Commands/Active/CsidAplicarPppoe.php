<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Item #1139 (#576 Fase 2) — refleja en FreeRADIUS (radcheck Calling-Station-Id == MAC) las zonas
 * en modo 'aplicar'. Solo usuarios con EXACTAMENTE una MAC aprendida; con 0 o más de 1 no se
 * restringe (no se corta a nadie por duda). Zonas fuera de 'aplicar' → se retira la restricción.
 * Por defecto DRY-RUN: sin --force no escribe en RADIUS.
 */
class CsidAplicarPppoe extends Command
{
    protected $signature = 'pppoe:csid-aplicar {--force : Escribir de verdad en radcheck}';

    protected $description = 'Sincroniza las restricciones Calling-Station-Id en RADIUS según el modo de cada zona';

    public function handle()
    {
        $force = (bool) $this->option('force');
        $zonas = DB::table('pppoe_csid_zonas')->get()->keyBy('nas_ip');
        $rad = DB::connection('radius')->table('radcheck');
        $poner = $quitar = $omitidos = 0;

        $filas = DB::table('pppoe_mac_aprendidas')->whereNotNull('nas_ip')->get()->groupBy('username');
        foreach ($filas as $usuario => $macs) {
            $zona = $zonas[$macs->first()->nas_ip] ?? null;
            $aplicar = $zona && $zona->modo === 'aplicar';
            $clave = ['username' => $usuario, 'attribute' => 'Calling-Station-Id'];

            if ($aplicar && $macs->count() === 1 && $macs->first()->mac_crudo) {
                $poner++;
                if ($force) {
                    DB::connection('radius')->table('radcheck')->updateOrInsert($clave, ['op' => '==', 'value' => $macs->first()->mac_crudo]);
                }
            } elseif ($aplicar) {
                $omitidos++; // 0 o varias MAC: no se restringe
            } else {
                $quitar++;
                if ($force) {
                    DB::connection('radius')->table('radcheck')->where($clave)->delete();
                }
            }
        }
        $this->info(($force ? 'APLICADO' : 'DRY-RUN') . ": restringir {$poner}, retirar {$quitar}, omitidos (varias MAC) {$omitidos}.");
        return self::SUCCESS;
    }
}
