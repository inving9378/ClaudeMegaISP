<?php

return [
    /*
     * Convención de nombres para el árbol jerárquico de permisos (item #537, Fase 1).
     *
     * Decisión de Irving (q1, 2026-08-08): dot-notation SOLO para permisos NUEVOS
     * de jerarquía módulo→sección/tarjeta→acción. Los 359 permisos legacy
     * snake_case (dashboard_view_dashboard, etc.) NO se migran — conviven
     * indefinidamente. Ver App\Modules\Core\Security\Support\PermissionNaming
     * para los builders/parser de esta convención.
     *
     * Formas válidas:
     *   {modulo}.view
     *   {modulo}.section.{slug}.view
     *   {modulo}.card.{slug}.view
     *   {modulo}.action.{slug}
     */
    'name_pattern' => '/^[a-z][a-z0-9_]*\.(view|section\.[a-z][a-z0-9_]*\.view|card\.[a-z][a-z0-9_]*\.view|action\.[a-z][a-z0-9_]*)$/',

    /*
     * Política de denegación silenciosa a nivel de RUTA (item #537, Fase 1,
     * decisión q2 de Irving): navegar por URL directa a algo sin permiso ya
     * NO muestra la pantalla 403 — redirige en silencio al destino de abajo.
     *
     * Solo aplica a navegación de página completa (Accept: text/html); las
     * llamadas AJAX/JSON conservan el 403 real intacto (resources/js/helpers/
     * Form.js atrapa error.response.status===403 en varios flujos existentes
     * — cambiarles el código de respuesta los rompería en silencio).
     *
     * Kill switch sin redeploy: PERMISSION_HIERARCHY_SILENT_DENIAL=false en
     * .env restaura el 403 clásico para navegación de página completa también.
     */
    'silent_denial_enabled' => env('PERMISSION_HIERARCHY_SILENT_DENIAL', true),

    /*
     * Destino de la redirección silenciosa (q2: Dashboard). Si la ruta pedida
     * YA es este destino y también se deniega, CheckRoutePermission cae al
     * 403 clásico ahí (evita loop de redirección infinito).
     */
    'silent_denial_redirect' => '/',
];
