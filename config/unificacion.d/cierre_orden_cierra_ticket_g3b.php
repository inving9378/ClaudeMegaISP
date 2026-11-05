<?php

// G3b Tickets: los estados de ticket y orden son independientes. Completar una orden NO cierra el ticket
// al que está ligada (tasks.ticket_id). Encendido = regla explícita: al completar la ÚLTIMA orden abierta
// del ticket, el ticket pasa a «Cerrado». Apagado (default) = comportamiento actual: nunca se cierra solo.
return ['cierre_orden_cierra_ticket_g3b' => (bool) env('UNIFICACION_CIERRE_ORDEN_CIERRA_TICKET_G3B', false)];
