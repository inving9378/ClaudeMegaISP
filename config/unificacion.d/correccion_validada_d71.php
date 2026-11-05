<?php

// D7.1 Talento: servicio de corrección de una orden ya validada (tipo, puntos, participantes) mientras su semana
// de pago siga abierta. Exige permiso propio y motivo; registra evento antes/después. Apagado = comportamiento actual.
return [
    'correccion_validada_d71' => (bool) env('UNIFICACION_CORRECCION_VALIDADA_D71', false),
];
