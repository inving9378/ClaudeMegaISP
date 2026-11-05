<?php

// G3c Tickets: plazos de atención por tipo/prioridad (tabla ticket_plazos, reloj corrido 24/7). Apagado =
// el ticket no muestra tiempo transcurrido frente a plazo. Sin fila configurada = «sin plazo», nada se muestra.
return ['tickets_plazos_g3c' => (bool) env('UNIFICACION_TICKETS_PLAZOS_G3C', false)];
