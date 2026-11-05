<?php

// G3a Tickets: la tabla tickets guarda first_responded_at (primera respuesta de staff) y closed_at
// (cuando pasa a 'Cerrado'). Apagado = no se escriben (las columnas existen, nullable). Sin backfill.
return ['tickets_fechas_g3a' => (bool) env('UNIFICACION_TICKETS_FECHAS_G3A', false)];
