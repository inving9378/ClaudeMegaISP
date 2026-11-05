<?php

// E1 Talento: las órdenes se muestran y se abren por una referencia con origen (T-<id> tarea, L-<id> orden antigua).
return ['referencia_orden' => (bool) env('UNIFICACION_REFERENCIA_ORDEN', false)];
