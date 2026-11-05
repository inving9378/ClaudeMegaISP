<?php

// C4c Talento: supervisor y equipo editables en la ficha, con historial de cambios (quién/cuándo/anterior).
// Apagado = el campo equipo se ignora y no se registra historial.
return ['supervision_ficha_c4c' => (bool) env('UNIFICACION_SUPERVISION_FICHA_C4C', false)];
