<?php

return [
    /*
     * Fase 4 del item #843 (roadmap #850) — checks explícitos de permiso por
     * ACCIÓN en controladores de módulos de alto riesgo (dinero directo:
     * Medussa/facturación, pagos/OpenPay, comisiones/Embajadores, nómina —
     * decisión q1 de Irving), como defensa en profundidad adicional al
     * permiso de RUTA que ya cubre `check_route_permission`.
     *
     * Rollout en dos pasadas (decisión q4 de Irving): arranca en modo
     * LOG-ONLY — se registra el hueco de permiso en el canal dedicado sin
     * bloquear la acción — para detectar huecos de mapping rol→permiso sin
     * tumbar operación real. Cuando la revisión de logs confirme que los
     * roles que de verdad usan cada acción ya tienen el permiso nuevo
     * asignado, cambiar este flag a true (sin redeploy, vía env) activa el
     * abort(403) real en el MISMO call site — no hay que tocar controladores
     * de nuevo para pasar de log-only a enforcement.
     */
    'enforce' => env('PERMISSION_ACTION_CHECKS_ENFORCE', false),

    'log_channel' => 'permisos_accion',
];
