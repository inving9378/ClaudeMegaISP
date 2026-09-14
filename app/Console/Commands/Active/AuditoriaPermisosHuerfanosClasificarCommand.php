<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

/**
 * Item roadmap #9991121 (sub-item de #9990721, decisión de Irving en su pregunta q1 =
 * "generar reporte clasificado antes de borrar nada").
 *
 * SOLO LECTURA: toma los huérfanos ya detectados por `auditoria:permisos-reporte`
 * (`docs/auditoria/permisos-punto1-2.csv`, columna estado=huerfano) y los clasifica para que
 * Irving decida caso por caso, sin retirar ni tocar ningún permiso/rol. Ejes de clasificación:
 *   - módulo/origen (heredado del CSV base)
 *   - roles a los que está asignado + si alguno de esos roles tiene usuarios `activo`
 *   - si pertenece al grupo de facturación/pagos ya delegado a #9991119 (no duplicar decisión ahí)
 *
 * No repite la detección de falsos positivos por construcción dinámica (`can('modulo.'.$x)`) de
 * forma automática — esa verificación semántica quedó hecha a mano para el item #9991121 y su
 * resultado vive en `docs/auditoria/permisos-huerfanos-clasificacion-item-9991121.md`, no en este
 * comando (automatizarla de forma fiable requeriría un parser AST, ya descartado como
 * sobre-ingeniería en el propio #9990745).
 */
class AuditoriaPermisosHuerfanosClasificarCommand extends Command
{
    protected $signature = 'auditoria:permisos-huerfanos-clasificar
                            {--csv-in= : CSV de entrada con la columna estado (default: docs/auditoria/permisos-punto1-2.csv)}
                            {--csv-out= : ruta del CSV de salida (default: docs/auditoria/permisos-huerfanos-clasificacion.csv)}';

    protected $description = 'SOLO LECTURA: clasifica los huérfanos de auditoria:permisos-reporte por roles/usuarios activos (item #9991121)';

    /**
     * Los 5 nombres de la sección 3.4 del documento final (#9990746): están asignados en
     * role_has_permissions de 8 roles (27 asignaciones) pero, al ser huérfanos, no dan capacidad
     * real. Su destino ya se delega al item hermano #9991119 — aquí solo se marcan, no se deciden.
     */
    private const GRUPO_FACTURACION_PAGOS = [
        'billing_payment_view_clients',
        'billing_view_detail_payments',
        'billing_payment_sellers',
        'invoice_view_invoice',
        'invoice_edit_invoice',
    ];

    public function handle(): int
    {
        $csvIn = $this->option('csv-in') ?: base_path('docs/auditoria/permisos-punto1-2.csv');

        if (! File::exists($csvIn)) {
            $this->error("No existe el CSV de entrada: {$csvIn}. Corre primero `auditoria:permisos-reporte`.");

            return self::FAILURE;
        }

        $rows = array_map('str_getcsv', file($csvIn));
        $header = array_shift($rows);
        $idx = array_flip($header);

        $huerfanos = array_values(array_filter($rows, fn ($r) => ($r[$idx['estado']] ?? null) === 'huerfano'));

        if (! $huerfanos) {
            $this->info('No hay huérfanos en el CSV de entrada.');

            return self::SUCCESS;
        }

        $clasificados = [];
        foreach ($huerfanos as $r) {
            $name = $r[$idx['name']];
            $permiso = Permission::where('name', $name)->first();

            $roles = $permiso ? $permiso->roles()->pluck('name')->all() : [];
            $usuariosActivos = 0;
            foreach ($roles as $roleName) {
                $usuariosActivos += \App\Models\User::role($roleName)->where('estado', 'activo')->count();
            }

            $clasificados[] = [
                'name' => $name,
                'guard' => $r[$idx['guard_name']],
                'modulo' => $r[$idx['modulo']],
                'roles_asignados' => implode('|', $roles),
                'usuarios_activos_via_rol' => $usuariosActivos,
                'grupo_facturacion_pagos' => in_array($name, self::GRUPO_FACTURACION_PAGOS, true) ? 'si-ver-9991119' : '',
            ];
        }

        $this->info('Huérfanos clasificados: ' . count($clasificados));
        $this->line('');
        $this->table(
            ['name', 'roles_asignados', 'usuarios_activos_via_rol', 'grupo_facturacion_pagos'],
            collect($clasificados)->map(fn ($c) => [$c['name'], $c['roles_asignados'], $c['usuarios_activos_via_rol'], $c['grupo_facturacion_pagos']])
        );

        $csvOut = $this->option('csv-out') ?: base_path('docs/auditoria/permisos-huerfanos-clasificacion.csv');
        File::ensureDirectoryExists(dirname($csvOut));
        $fh = fopen($csvOut, 'w');
        fputcsv($fh, ['name', 'guard_name', 'modulo', 'roles_asignados', 'usuarios_activos_via_rol', 'grupo_facturacion_pagos']);
        foreach ($clasificados as $c) {
            fputcsv($fh, [$c['name'], $c['guard'], $c['modulo'], $c['roles_asignados'], $c['usuarios_activos_via_rol'], $c['grupo_facturacion_pagos']]);
        }
        fclose($fh);

        $this->line('');
        $this->info("CSV escrito en: {$csvOut}");
        $this->comment('⚠ Clasificación semántica (falsos positivos por construcción dinámica, legacy '
            . 'superado, cruft de pruebas) NO está automatizada aquí — ver '
            . 'docs/auditoria/permisos-huerfanos-clasificacion-item-9991121.md para el detalle caso por caso.');

        return self::SUCCESS;
    }
}
