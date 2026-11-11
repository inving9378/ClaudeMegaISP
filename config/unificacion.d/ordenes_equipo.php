<?php

// #1325 Talento: una orden se asigna a un responsable y a sus auxiliares (equipos de 2 a 6). Los
// auxiliares la ven en su app y quedan en task_user. Necesita participantes_d8 encendido para saber
// quién es el responsable. OJO: mientras el reparto por rol (porcentajes_rol_d8b) esté apagado, cada
// integrante recibe los puntos completos de la orden. Apagado = una sola persona por orden, como hoy.
return ['ordenes_equipo' => (bool) env('UNIFICACION_ORDENES_EQUIPO', false)];
