<?php

// D4b Talento: validar una orden (validarAdmin) exige ser administración global (super admin) o supervisar
// al ejecutor por talento_colaboradores.supervisor_id; ajenas fuera de su equipo se rechazan (403).
// Apagado = comportamiento actual (quien tenga el permiso valida cualquiera).
return ['validacion_equipo_d4b' => (bool) env('UNIFICACION_VALIDACION_EQUIPO_D4B', false)];
