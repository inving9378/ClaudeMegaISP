<?php

// H3 Talento: «Mi dinero» (portal web y app móvil) agrega `etapas` — pendiente de validar, validado,
// liquidado y pagado — con sus totales. Cada importe de la semana cae en UNA sola etapa. Apagado = sin cambio.
return ['mi_dinero_etapas_h3' => (bool) env('UNIFICACION_MI_DINERO_ETAPAS_H3', false)];
