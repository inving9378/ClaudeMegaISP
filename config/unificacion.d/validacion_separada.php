<?php

// D3a Talento: completar una orden de tarea (tasks.talento_type_id) deja de validarla en el mismo acto.
// Encendido = OrdenTrabajoUnifiedService::completar() pone Done SIN validated_at (no cuenta unidades
// pagables hasta que alguien la valide). Apagado = comportamiento actual (Done + validated_at = now()).
return ['validacion_separada' => (bool) env('UNIFICACION_VALIDACION_SEPARADA', false)];
