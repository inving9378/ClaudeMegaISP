<?php

namespace App\Console\Commands\Active;

use App\Services\IdentidadConsistencyService;
use Illuminate\Console\Command;

/**
 * Fase 3c de #9990778 (Identidad unificada) — decisión de Irving en q4 de
 * #9990878. Reporta, sin escribir nada, qué tan consistente está el bridge
 * `seller_id` (=users.id) → `colaborador_id` en `client_main_information` y
 * `whatsapp_conversations` (ver BackfillColaboradorIdBridgeCommand, Fase 2):
 *
 * - Huérfanas: seller_id sin colaborador_id. Se desglosan las que SÍ tienen un
 *   colaborador real disponible hoy (pendientes de correr el backfill) de las
 *   que son huérfanas ESTRUCTURALES (seller_id sin talento_colaboradores
 *   correspondiente — cuentas admin, espejos, etc.). Estas últimas NO se
 *   "arreglan": son el piso esperado documentado en #9990962.
 * - Drift: colaborador_id poblado que ya NO corresponde al mapeo actual
 *   (seller_id sin match hoy, o apuntando a un colaborador_id distinto).
 *
 * Solo lectura. No escribe nada en ninguna tabla.
 */
class VerificarConsistenciaIdentidadCommand extends Command
{
    protected $signature = 'identidad:verificar-consistencia';

    protected $description = 'Reporta consistencia del bridge seller_id -> colaborador_id (solo lectura, #9990778 Fase 3c)';

    public function handle(IdentidadConsistencyService $service): int
    {
        $resumen = $service->resumen();

        foreach ($resumen as $tabla => $datos) {
            $this->line("");
            $this->info("=== {$tabla} ===");
            $this->line("Total de filas: {$datos['total']}");
            $this->line("Con seller_id: {$datos['con_seller_id']}");
            $this->line("Con colaborador_id poblado: {$datos['poblado']}");
            $this->line("Resolubles hoy (seller_id con colaborador real disponible): {$datos['resoluble_total']}");

            $coberturaTexto = $datos['cobertura_pct'] !== null
                ? "{$datos['cobertura_pct']}%"
                : 'N/D (0 filas resolubles)';
            $this->line("Cobertura resoluble (poblado / resoluble): {$coberturaTexto}");

            if ($datos['drift'] > 0) {
                $this->warn("Drift detectado: {$datos['drift']} fila(s) con colaborador_id que ya NO corresponde al mapeo actual.");
            } else {
                $this->info('Drift: 0 (el mapeo actual coincide con lo poblado).');
            }

            $this->line("Huérfanas (seller_id sin colaborador_id): {$datos['huerfanas']}");
            if ($datos['huerfanas_resolubles'] > 0) {
                $this->warn("  De esas, {$datos['huerfanas_resolubles']} SÍ tienen colaborador real disponible hoy (pendientes de backfill).");
            }

            if (!empty($datos['huerfanas_sin_match'])) {
                $this->line('  Huérfanas ESTRUCTURALES por seller_id (users.id) sin talento_colaboradores correspondiente:');
                foreach ($datos['huerfanas_sin_match'] as $userId => $count) {
                    $this->line("    users.id={$userId} → {$count} fila(s)");
                }
            }
        }

        $this->line('');

        return self::SUCCESS;
    }
}
