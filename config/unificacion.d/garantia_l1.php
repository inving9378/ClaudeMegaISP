<?php

// L1 Talento: un ticket nuevo de un cliente con una instalación validada dentro de la ventana de garantía
// queda ligado a la orden original (referencia E1), al cliente y a sus participantes (D8) en
// talento_warranty_events. Sin efecto económico. Apagado = comportamiento actual (no se registra nada).
// 'garantia_l1_meses' = duración de la ventana (6 por defecto) cuando el interruptor está encendido.
return [
    'garantia_l1'       => (bool) env('UNIFICACION_GARANTIA_L1', false),
    'garantia_l1_meses' => (int) env('UNIFICACION_GARANTIA_L1_MESES', 6),
];
