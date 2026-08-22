<?php

return [
    /*
     * Kill-switch global de la regla de firewall MgNet_INPUT_DROPEA_EL_RESTO
     * (item roadmap #983 — MikrotikRulesJob::addRulesInputDorpRest() existía
     * pero nunca se invocaba, así que la regla accept-solo-MegaISP no
     * restringía nada en la práctica).
     *
     * false (default) → MikrotikRulesJob NUNCA agrega el drop final del chain
     *                    input, sin importar el flag por-router. Esto
     *                    preserva el comportamiento actual (chain input con
     *                    política implícita accept) para TODOS los routers.
     * true             → habilita el mecanismo; el drop del resto solo se
     *                     aplica en los routers cuyo mikrotik_configs.
     *                     enforce_input_drop_rest también esté en true
     *                     (rollout gradual por router, decisión q2 del
     *                     item), y solo si el precheck de IPs críticas pasa
     *                     (decisión q3).
     *
     * Requiere, ANTES de activarse en cualquier router real: inventariar los
     * servicios de gestión (Winbox/SSH/etc.) que necesitan pasar por el
     * chain input y agregar sus reglas accept — ver docs/runbook-mikrotik-
     * restriccion-api-address.md sección 5. NO activar en prod sin
     * aprobación explícita de Irving (mismo criterio que el item #979).
     */
    'enforce_input_drop_rest' => (bool) env('MIKROTIK_ENFORCE_INPUT_DROP_REST', false),

    /*
     * Inventario de servicios de gestión (item roadmap #1037) — las IPs/rangos que
     * deben quedar en accept dentro del chain input ANTES de que MikrotikRulesJob
     * instale MgNet_INPUT_DROPEA_EL_RESTO. Vacío por default: hasta que Irving
     * entregue la lista real (Winbox, SSH, su IP de administración, rangos de
     * monitoreo/NOC) y se llene aquí vía las variables .env de abajo, el precheck
     * en MikrotikRulesJob::shouldEnforceInputDropRest() sigue bloqueando el
     * drop-resto para 'winbox'/'ssh' (marcados 'critical') aunque el flag
     * por-router esté en true — así que esta lista vacía NO cambia el
     * comportamiento de ningún router real todavía.
     *
     * Formato .env: IPs/CIDR separadas por coma, ej.
     *   MIKROTIK_MANAGEMENT_ALLOWLIST_WINBOX=203.0.113.10,203.0.113.0/28
     */
    'management_services' => [
        'winbox' => [
            'port' => 8291,
            'protocol' => 'tcp',
            'critical' => true,
            'allowed_addresses' => array_filter(array_map('trim', explode(',', (string) env('MIKROTIK_MANAGEMENT_ALLOWLIST_WINBOX', '')))),
        ],
        'ssh' => [
            'port' => 22,
            'protocol' => 'tcp',
            'critical' => true,
            'allowed_addresses' => array_filter(array_map('trim', explode(',', (string) env('MIKROTIK_MANAGEMENT_ALLOWLIST_SSH', '')))),
        ],
        'monitoreo' => [
            // Rangos de NOC/monitoreo (SNMP, ping, etc.) — sin puerto fijo, solo IP.
            'port' => null,
            'protocol' => null,
            'critical' => false,
            'allowed_addresses' => array_filter(array_map('trim', explode(',', (string) env('MIKROTIK_MANAGEMENT_ALLOWLIST_MONITOREO', '')))),
        ],
    ],
];
