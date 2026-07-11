<?php

return [
    /*
     * Auto-otorgar el permiso .view de cada permiso nuevo a TODOS los roles base
     * (además de super-administrator/DESARROLLADOR, que SIEMPRE lo reciben).
     *
     * Default FALSE (item #309): la reforma de permisos usa el rol como única
     * fuente de verdad; el auto-grant re-ensanchaba los roles base podados en
     * cada install/update de módulo o cada permissions:sync-roles. Con este flag
     * en false, los roles base ya NO reciben .view automáticamente — se asignan
     * de forma explícita. super-administrator/DESARROLLADOR NO se ven afectados.
     *
     * Ponerlo en true restaura el comportamiento previo (decisión 2026-06-04).
     */
    'auto_grant_view_base_roles' => env('PERMISSION_SYNC_AUTO_GRANT_VIEW', false),

    /*
     * Roles que NO reciben permisos .view automáticamente en el reparto de PermissionSyncService.
     * Usados para roles de portal-cliente o roles legacy sin usuarios activos.
     * Editar aquí para agregar/quitar; no tocar PermissionSyncService directamente.
     */
    'view_excluded_roles' => [
        'client',      // clientes ISP — solo deben ver su portal (MegaFamilia/referidos)
        'conductor',   // sin usuarios activos
        'PUBLICADOR',  // sin usuarios activos
        'Socio',       // sin usuarios activos
    ],
];
