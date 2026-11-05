<?php

// F5 Talento: «Mi día» (summaryForHoy) incluye también las tareas generales asignadas al colaborador
// (sin talento_type_id), con la hora de TaskScheduleService y un tipo propio; las órdenes añaden la
// misma hora. Llaves nuevas solo aditivas: la respuesta de la app sigue siendo compatible.
return ['mi_dia_tareas' => (bool) env('UNIFICACION_MI_DIA_TAREAS', false)];
