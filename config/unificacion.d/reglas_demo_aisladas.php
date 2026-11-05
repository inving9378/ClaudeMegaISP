<?php

// UNI-T J5 (#1009): las reglas semanales DEMO (ids en 'ids') solo aplican a cuentas demo_*/prueba_*.
// Apagado = comportamiento de hoy (resolveRule las considera para cualquiera).
return [
    'reglas_demo_aisladas' => (bool) env('UNIFICACION_REGLAS_DEMO_AISLADAS', false),
];
