<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Item #1144 (#576 Fase 2b) — olvida la MAC aprendida de un usuario PPPoE (cambio de equipo)
 * y retira su restricción Calling-Station-Id de RADIUS. Punto único: lo usan el comando
 * pppoe:csid-zona --reaprender, la pantalla de Configuración y el cierre de OT de Talento.
 */
class PppoeCsidReaprenderService
{
    public function reaprender(string $username, string $por = 'consola'): int
    {
        $n = DB::table('pppoe_mac_aprendidas')->where('username', $username)->delete();
        DB::connection('radius')->table('radcheck')
            ->where(['username' => $username, 'attribute' => 'Calling-Station-Id'])->delete();
        Log::info('pppoe.csid.reaprender', ['username' => $username, 'macs_olvidadas' => $n, 'por' => $por]);
        return $n;
    }

    /** Cierre de OT de cambio de equipo: solo con el flag encendido; nunca bloquea el cierre. */
    public function alCerrarOtCambioEquipo($task): void
    {
        if (! config('unificacion.interruptores.csid_reaprender_al_cerrar_ot')) {
            return;
        }
        try {
            $username = optional($task->client_main_information)->user;
            if (! $username) {
                throw new \RuntimeException('La OT no tiene cliente con usuario PPPoE');
            }
            $this->reaprender($username, 'talento:ot-' . $task->id);
        } catch (\Throwable $e) {
            Log::warning('pppoe.csid.reaprender_fallo', ['task_id' => $task->id ?? null, 'error' => $e->getMessage()]);
            try {
                DB::table('pppoe_csid_reaprender_pendientes')->updateOrInsert(
                    ['task_id' => $task->id],
                    ['username' => optional($task->client_main_information)->user, 'error' => mb_substr($e->getMessage(), 0, 250),
                     'resuelto_at' => null, 'updated_at' => now(), 'created_at' => now()]
                );
            } catch (\Throwable $e2) {
                Log::warning('pppoe.csid.pendiente_no_guardado', ['error' => $e2->getMessage()]);
            }
        }
    }
}
