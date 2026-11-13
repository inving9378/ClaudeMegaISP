<?php

// UNI-T D8b: porcentaje de reconocimiento por rol (responsable / participante) en el pago de órdenes con varios asignados.
// Apagado = cada asignado recibe la unidad completa (comportamiento de hoy). Encendido, una orden reparte el 100 %:
// sola, la persona se lleva todo; en equipo, el responsable su porcentaje y el de participantes se divide en partes
// iguales entre los auxiliares. Regla de Irving (#1325, 11-nov): 70 % responsable, 30 % los auxiliares entre todos.
return [
    'porcentajes_rol_d8b'               => (bool) env('UNIFICACION_PORCENTAJES_ROL_D8B', false),
    'porcentajes_rol_d8b_responsable'   => (float) env('UNIFICACION_PORCENTAJE_RESPONSABLE_D8B', 70),
    'porcentajes_rol_d8b_participante'  => (float) env('UNIFICACION_PORCENTAJE_PARTICIPANTE_D8B', 30),
];
