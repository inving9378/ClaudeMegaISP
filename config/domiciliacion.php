<?php

return [
    /*
     * Kill-switch explícito para cobros REALES de domiciliación (OpenPay).
     * Independiente de OPENPAY_SANDBOX (config/openpay.php) — sandbox es
     * únicamente la señal de entorno de OpenPay (real vs pruebas), NO un
     * permiso operativo para cobrar. Acoplarlos fue el bug de origen: con
     * OPENPAY_SANDBOX=false (como pide el checklist de go-live) el comando
     * `domiciliacion:cobrar` se bloqueaba también en producción.
     *
     * false → domiciliacion:cobrar aborta antes de intentar cualquier cargo
     *         real (dry-run sigue funcionando siempre, para simular).
     * true  → procede a cobrar (sujeto también al interruptor operativo
     *         Setting 'domiciliacion_habilitada').
     *
     * Flip a true SOLO por decisión explícita de Irving, documentada en el
     * checklist pre-deploy.
     */
    'cobro_live_enabled' => (bool) env('DOMICILIACION_COBRO_LIVE_ENABLED', false),
];
