<?php

// H4 Talento: el check-in/check-out de la app móvil pasa por AttendanceService (mismas reglas que el portal).
return ['asistencia_app_servicio' => (bool) env('UNIFICACION_ASISTENCIA_APP_SERVICIO', false)];
