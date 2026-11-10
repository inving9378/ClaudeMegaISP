<?php

/*
| Descomisión de comisiones de vendedores (#1216 F1a, política en
| docs/unificacion/12-decisiones-economicas.md §6). Sin lógica de motor aún:
| solo la configuración, APAGADA por defecto.
*/
return [
    // Interruptor maestro. Solo Irving lo prende.
    'enabled' => (bool) env('DESCOMISION_ENABLED', false),

    // Ventana de permanencia móvil, en días desde la activación del cliente.
    'ventana_dias' => (int) env('DESCOMISION_VENTANA_DIAS', 30),

    // Nombre del status en seller_status al que pasa el vendedor bloqueado
    // (se busca por nombre, no por id: los ids difieren entre dev y prod).
    'status_bloqueado' => env('DESCOMISION_STATUS_BLOQUEADO', 'Bloqueado'),
];
