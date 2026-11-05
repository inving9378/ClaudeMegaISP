<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Item #1139 (#576 Fase 2) — interruptor por zona (NAS): apagado | aprender | aplicar,
 * y re-aprender un usuario. NO toca RADIUS: solo la tabla local. Aplicar a RADIUS lo hace
 * pppoe:csid-aplicar. 'aplicar' exige un periodo de aprendizaje completo (config días).
 */
class CsidZonaPppoe extends Command
{
    protected $signature = 'pppoe:csid-zona {nas_ip? : IP del NAS/router} {modo? : apagado|aprender|aplicar}
        {--dias-minimos=7 : Días mínimos en "aprender" antes de permitir "aplicar"}
        {--reaprender= : Usuario PPPoE cuya MAC aprendida se olvida (cambio de equipo)}
        {--por=consola : Quién lo cambia (bitácora)}';

    protected $description = 'Cambia el modo Calling-Station-Id de una zona (apagado/aprender/aplicar) o re-aprende un usuario';

    public function handle()
    {
        if ($u = $this->option('reaprender')) {
            $n = app(\App\Services\PppoeCsidReaprenderService::class)->reaprender($u, $this->option('por'));
            $this->info("Re-aprender {$u}: {$n} MAC olvidadas y restricción RADIUS retirada.");
            return self::SUCCESS;
        }

        $nas = $this->argument('nas_ip');
        $modo = $this->argument('modo');
        if (!$nas || !$modo) {
            foreach (DB::table('pppoe_csid_zonas')->orderBy('nas_ip')->get() as $z) {
                $this->line("{$z->nas_ip}\t{$z->modo}\taprendiendo_desde={$z->aprendiendo_desde}\taplicando_desde={$z->aplicando_desde}");
            }
            return self::SUCCESS;
        }
        if (!in_array($modo, ['apagado', 'aprender', 'aplicar'], true)) {
            $this->error('Modo inválido: apagado|aprender|aplicar');
            return self::INVALID;
        }

        $z = DB::table('pppoe_csid_zonas')->where('nas_ip', $nas)->first();
        $upd = ['modo' => $modo, 'cambiado_por' => $this->option('por'), 'updated_at' => now()];
        if ($modo === 'aprender' && (!$z || $z->modo !== 'aprender')) {
            $upd['aprendiendo_desde'] = now();
        }
        if ($modo === 'aplicar') {
            $min = (int) $this->option('dias-minimos');
            if (!$z || !$z->aprendiendo_desde || $z->modo === 'apagado'
                || \Carbon\Carbon::parse($z->aprendiendo_desde)->gt(now()->subDays($min))) {
                $this->error("Rechazado: la zona {$nas} no completó {$min} días en 'aprender'. Nada se aplica sin aprendizaje completo.");
                return self::FAILURE;
            }
            $upd['aplicando_desde'] = now();
        }
        DB::table('pppoe_csid_zonas')->updateOrInsert(['nas_ip' => $nas], $upd + ['created_at' => now()]);
        $this->info("Zona {$nas} → {$modo}. (Para reflejarlo en RADIUS: pppoe:csid-aplicar)");
        return self::SUCCESS;
    }
}
