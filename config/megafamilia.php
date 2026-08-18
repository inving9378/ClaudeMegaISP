<?php

return [
    /*
     * Kill-switch de los datos DEMO financieros de la API móvil MegaFamilia
     * (facturas/pagos/cuenta) cuando el usuario no tiene cliente ISP ligado.
     * Esos montos ($450 fijo) eran indistinguibles de datos reales para el
     * cliente móvil — item roadmap #255.
     *
     * false (default) → sin cliente ligado, la API responde datos vacíos/
     *                    reales (demo:false), nunca montos inventados.
     * true             → conserva el comportamiento demo anterior (solo para
     *                     pruebas manuales de UI, nunca activar en prod).
     */
    'financial_demo_enabled' => (bool) env('MEGAFAMILIA_FINANCIAL_DEMO_ENABLED', false),

    /*
     * Toggle de conexión real a MikroTik desde MikrotikController (item #793).
     * Sin default forzado a bool: se preserva tal cual el valor de env()
     * (bool true/false si .env trae "true"/"false", o el string crudo en
     * cualquier otro caso) porque el controller compara contra AMBAS formas
     * (=== false y === 'false') — no simplificar el cast aquí sin revisar
     * los dos call sites en MikrotikController.
     */
    'conexion_mikrotik' => env('CONECTION_MIKROTIK', true),
];
