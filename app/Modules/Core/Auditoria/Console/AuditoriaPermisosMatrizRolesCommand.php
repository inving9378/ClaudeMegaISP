<?php

namespace App\Modules\Core\Auditoria\Console;

use App\Modules\Core\Auditoria\Services\PermisosMatrizRolesService;
use Illuminate\Console\Command;

/**
 * Fase 4c del inventario de permisos (item #9990765, sub-item de #9990745).
 *
 * SOLO LECTURA: matriz por rol de permisos asignados (role_has_permissions) cruzada contra
 * cuáles surten efecto de verdad (usado/huérfano, Fase 4a #9990763). Reporta también roles
 * "inflados" (más huérfanos que efectivos) y permisos de alto riesgo asignados donde no
 * surten efecto. No modifica roles, permisos ni asignaciones.
 */
class AuditoriaPermisosMatrizRolesCommand extends Command
{
    protected $signature = 'auditoria:permisos-matriz-roles
                            {--csv= : ruta del CSV de salida (default: docs/auditoria/permisos-matriz-roles.csv)}';

    protected $description = 'SOLO LECTURA: matriz por rol de permisos asignados vs. efectivos (Fase 4c, item #9990765)';

    public function handle(PermisosMatrizRolesService $service): int
    {
        $datos = $service->build();

        if (! $datos['fuente_csv']) {
            $this->warn('docs/auditoria/permisos-punto1-2.csv no existe (Fase 4a) — recalculando en vivo permiso por permiso (más lento).');
        }

        $this->info('=== Matriz de permisos por rol — asignados vs. efectivos (Fase 4c, item #9990765) ===');
        $this->line('');

        $this->table(
            ['Rol', 'Usuarios', 'Total permisos', 'Efectivos', 'Huérfanos', 'Desconocidos', 'Inflado'],
            collect($datos['resumen_roles'])->map(fn ($r) => [
                $r['rol'], $r['usuarios'], $r['total'], $r['efectivos'], $r['huerfanos'],
                $r['desconocidos'], $r['inflado'] ? 'SÍ' : '',
            ])
        );

        $this->line('');
        if ($datos['roles_inflados']) {
            $this->warn('Roles INFLADOS (más permisos huérfanos que efectivos — falsa sensación de control):');
            foreach ($datos['roles_inflados'] as $r) {
                $this->line("  - {$r['rol']}: {$r['huerfanos']} huérfanos vs. {$r['efectivos']} efectivos (de {$r['total']} totales)");
            }
        } else {
            $this->info('Sin roles inflados.');
        }

        $this->line('');
        if ($datos['alto_riesgo_sin_efecto']) {
            $this->error('Permisos de ALTO RIESGO asignados donde NO surten efecto (falsa protección):');
            foreach ($datos['alto_riesgo_sin_efecto'] as $x) {
                $this->line("  - rol \"{$x['rol']}\" → {$x['permiso']}");
            }
        } else {
            $this->info('Sin permisos de alto riesgo huérfanos en ningún rol.');
        }

        $csvPath = $this->option('csv') ?: base_path(PermisosMatrizRolesService::CSV_SALIDA);
        $service->escribirCsv($datos['filas'], $csvPath);
        $this->line('');
        $this->info("CSV de trabajo escrito en: {$csvPath}");

        return self::SUCCESS;
    }
}
