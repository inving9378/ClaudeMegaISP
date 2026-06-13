<?php

return [
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
