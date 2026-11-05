<?php

// H2 Talento: «Mi dinero» (portal web, pestaña Compensación y app móvil) comparte un solo cálculo
// (CompensacionService) y el dBm promedio de la app cuenta solo las mediciones del colaborador
// (sus órdenes y las tareas que tiene asignadas), no las de cualquier tarea. Apagado = comportamiento actual.
return ['mi_dinero_h2' => (bool) env('UNIFICACION_MI_DINERO_H2', false)];
