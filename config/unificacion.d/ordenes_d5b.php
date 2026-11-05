<?php

// D5b Talento: el cierre desde administración (cambiarEstadoAdmin → completed) pasa por la misma compuerta de
// evidencias y dBm que completar(), salvo excepción con permiso 'talento.ordenes.cierre_excepcion' y motivo
// (queda en el historial); una orden validada no cambia tipo ni responsable; cancelar archiva la tarea de verdad.
// Apagado = comportamiento actual.
return ['ordenes_d5b' => (bool) env('UNIFICACION_ORDENES_D5B', false)];
