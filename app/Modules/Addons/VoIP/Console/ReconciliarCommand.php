<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Models\Troncal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconcilia las tablas megaisp con el estado real de Asterisk.
 *
 * Ejecutar tras un restore parcial de BD que deje gaps entre
 * voip_* (megaisp) y ps_* (asterisk realtime) / archivos .conf.
 *
 * NUNCA toca la BD asterisk ni llama a AsteriskProvisioningService.
 */
class ReconciliarCommand extends Command
{
    protected $signature = 'voip:reconciliar {--dry-run : Muestra los cambios sin aplicarlos}';
    protected $description = 'Reconcilia tablas voip_* de megaisp con el estado real de Asterisk';

    private bool $dry;
    private int $fixes = 0;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');

        $this->info($this->dry
            ? '🔍  Modo DRY-RUN — sin cambios en BD'
            : '🔧  Aplicando reconciliación en megaisp...'
        );
        $this->newLine();

        $this->checkTroncalGrupo();
        $this->checkPivote();
        $this->checkDestinofallback();
        $this->checkAutoIncrement();

        $this->newLine();
        if ($this->fixes === 0) {
            $this->info('✅  Sin gaps — megaisp ya está sincronizado con Asterisk.');
        } elseif ($this->dry) {
            $this->warn("⚠️   {$this->fixes} gap(s) detectado(s). Corre sin --dry-run para aplicar.");
        } else {
            $this->info("✅  {$this->fixes} gap(s) corregido(s) en megaisp.");
        }

        return Command::SUCCESS;
    }

    // ─── Troncal id=2: grupo_entrante_id debe ser 1 ──────────────────────────

    private function checkTroncalGrupo(): void
    {
        $troncal = Troncal::find(2);
        $grupo   = GrupoTimbrado::find(1);

        if (! $troncal) {
            $this->error('  ❌  [FATAL] voip_troncales id=2 no existe — verificar manualmente.');
            return;
        }
        if (! $grupo) {
            $this->error('  ❌  [FATAL] voip_grupos_timbrado id=1 no existe — verificar manualmente.');
            return;
        }

        if ($troncal->grupo_entrante_id === $grupo->id) {
            $this->line("  ✅  Troncal id=2 → grupo_entrante_id={$troncal->grupo_entrante_id} (OK)");
            return;
        }

        $this->warn(
            "  ⚠️   Troncal id=2 tiene grupo_entrante_id=" . ($troncal->grupo_entrante_id ?? 'NULL')
            . "  →  debe ser 1 (\"Atención a Clientes\")"
        );
        $this->warn(
            '       Sin esto, regenerar dialplan desde UI resetearía context de trunk_2'
            . ' a from-trunk, rompiendo enrutamiento entrante.'
        );
        $this->fixes++;

        if (! $this->dry) {
            DB::table('voip_troncales')->where('id', 2)->update(['grupo_entrante_id' => 1]);
            $this->info('       → Aplicado: voip_troncales.grupo_entrante_id = 1');
        }
    }

    // ─── Pivote: grupo 1 debe tener extensiones 1001-1004 ────────────────────

    private function checkPivote(): void
    {
        $grupo = GrupoTimbrado::find(1);
        if (! $grupo) {
            return;
        }

        $extensiones = Extension::whereIn('numero', ['1001', '1002', '1003', '1004'])
            ->orderBy('numero')
            ->get()
            ->keyBy('numero');

        if ($extensiones->count() !== 4) {
            $found = $extensiones->keys()->implode(', ');
            $this->error("  ❌  No se encontraron las 4 extensiones en megaisp (encontradas: {$found})");
            return;
        }

        $vinculadas = $grupo->extensiones()->pluck('numero')->sort()->values();
        $esperadas  = collect(['1001', '1002', '1003', '1004']);
        $faltantes  = $esperadas->diff($vinculadas);

        if ($faltantes->isEmpty()) {
            $this->line('  ✅  Pivote voip_grupo_extension: las 4 extensiones vinculadas (OK)');
            return;
        }

        $this->warn(
            '  ⚠️   Pivote grupo_id=1 incompleto. '
            . 'Vinculadas: [' . $vinculadas->implode(', ') . ']  '
            . 'Faltan: [' . $faltantes->implode(', ') . ']'
        );
        $this->warn(
            '       El grupo en Asterisk ya llama a 1001+1002+1003+1004; '
            . 'la UI solo muestra los vinculados en megaisp.'
        );
        $this->fixes++;

        if (! $this->dry) {
            $sync = [];
            foreach (['1001' => 1, '1002' => 2, '1003' => 3, '1004' => 4] as $numero => $orden) {
                if (isset($extensiones[$numero])) {
                    $sync[$extensiones[$numero]->id] = ['orden' => $orden];
                }
            }
            $grupo->extensiones()->sync($sync);
            $this->info('       → Aplicado: sync pivote [' . implode(', ', array_keys($extensiones->all())) . ']');
        }
    }

    // ─── Grupo id=1: destino_fallback debe ser 'repetir' (según .conf vivo) ──

    private function checkDestinofallback(): void
    {
        $grupo = GrupoTimbrado::find(1);
        if (! $grupo) {
            return;
        }

        if ($grupo->destino_fallback === 'repetir') {
            $this->line("  ✅  Grupo id=1 destino_fallback='repetir' (OK)");
            return;
        }

        $this->warn(
            "  ⚠️   Grupo id=1 destino_fallback='{$grupo->destino_fallback}' en megaisp "
            . "pero megaisp_grupos.conf activo tiene Goto(grupo-1,s,1) = 'repetir'."
        );
        $this->warn(
            "       Si se regenera el dialplan desde la UI, el fallback cambiará a Hangup() "
            . "(colgar al no contestar), divergiendo del comportamiento actual en Asterisk."
        );
        $this->fixes++;

        if (! $this->dry) {
            DB::table('voip_grupos_timbrado')->where('id', 1)->update(['destino_fallback' => 'repetir']);
            $this->info("       → Aplicado: voip_grupos_timbrado.destino_fallback = 'repetir'");
        }
    }

    // ─── AUTO_INCREMENT ───────────────────────────────────────────────────────

    private function checkAutoIncrement(): void
    {
        // Pares tabla => valor mínimo seguro de AUTO_INCREMENT
        $tablas = [
            'voip_grupos_timbrado' => 2,
            'voip_extensiones'     => 5,
        ];

        foreach ($tablas as $tabla => $minSafe) {
            // Usar SHOW CREATE TABLE — es la fuente más fiable; information_schema
            // tiene lag de caché en InnoDB y puede mostrar valores obsoletos.
            $ddl     = DB::selectOne("SHOW CREATE TABLE `{$tabla}`");
            $create  = $ddl->{'Create Table'};
            preg_match('/AUTO_INCREMENT=(\d+)/', $create, $m);
            $autoInc = $m ? (int) $m[1] : 1;

            if ($autoInc >= $minSafe) {
                $this->line("  ✅  {$tabla} AUTO_INCREMENT={$autoInc} (OK)");
                continue;
            }

            $maxId = (int) DB::table($tabla)->max('id');
            $this->warn(
                "  ⚠️   {$tabla} AUTO_INCREMENT={$autoInc} "
                . "(max id={$maxId}, mínimo seguro={$minSafe}) — próximo INSERT colisionaría."
            );
            $this->fixes++;

            if (! $this->dry) {
                DB::statement("ALTER TABLE `{$tabla}` AUTO_INCREMENT = {$minSafe}");
                $this->info("       → Aplicado: ALTER TABLE {$tabla} AUTO_INCREMENT = {$minSafe}");
            }
        }
    }
}
