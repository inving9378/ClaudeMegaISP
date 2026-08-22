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
];
