<?php

// D2 Talento: cada transición de orden (asignar, iniciar, completar, validar, cancelar, incidencia)
// pasa por OrdenTrabajoUnifiedService::transicionar(), que escribe el evento (E2) y quién la hizo
// (assigned_by/completed_by/validated_by). Apagado = no registra nada, comportamiento actual.
return ['transiciones_d2' => (bool) env('UNIFICACION_TRANSICIONES_D2', false)];
