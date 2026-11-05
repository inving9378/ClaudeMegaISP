<?php

// D8 Talento: cada orden muestra un responsable principal y sus participantes (con rol y aporte opcional).
// Solo datos y visualización: no cambia cómo se acredita ni se paga. Apagado = no aparece nada.
return ['participantes_d8' => (bool) env('UNIFICACION_PARTICIPANTES_D8', false)];
