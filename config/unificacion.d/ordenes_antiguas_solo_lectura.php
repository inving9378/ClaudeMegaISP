<?php

// E1 Fase 3 Talento: las órdenes antiguas (talento_work_orders, L-) quedan en solo lectura para el admin.
return ['ordenes_antiguas_solo_lectura' => (bool) env('UNIFICACION_ORDENES_ANTIGUAS_SOLO_LECTURA', false)];
