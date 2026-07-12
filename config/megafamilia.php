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
];
