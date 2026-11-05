<?php

// I5 Talento (#1056): el bono semanal de desempeño consulta IncentiveCompatibilityService (unificacion.compatibilidad_incentivos).
// Apagado = el bono se escribe como hoy. Además exige compatibilidad_incentivos.activo para tener efecto.
return ['bono_semanal_compat_i5' => (bool) env('UNIFICACION_BONO_SEMANAL_COMPAT_I5', false)];
