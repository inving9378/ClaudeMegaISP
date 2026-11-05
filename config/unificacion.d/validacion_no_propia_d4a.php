<?php

// D4a Talento: nadie valida lo propio. Encendido = validarAdmin rechaza (422) si quien valida es
// asignado, participante o ejecutor de la orden. Apagado = comportamiento actual.
return ['validacion_no_propia_d4a' => (bool) env('UNIFICACION_VALIDACION_NO_PROPIA_D4A', false)];
