<?php

// UNI-T D8b: porcentaje de reconocimiento por rol (responsable / participante) en el pago de órdenes con varios asignados.
// Apagado = cada asignado recibe la unidad completa (comportamiento de hoy). Los porcentajes (100/100 por default) solo aplican encendido.
return [
    'porcentajes_rol_d8b'               => (bool) env('UNIFICACION_PORCENTAJES_ROL_D8B', false),
    'porcentajes_rol_d8b_responsable'   => (float) env('UNIFICACION_PORCENTAJE_RESPONSABLE_D8B', 100),
    'porcentajes_rol_d8b_participante'  => (float) env('UNIFICACION_PORCENTAJE_PARTICIPANTE_D8B', 100),
];
